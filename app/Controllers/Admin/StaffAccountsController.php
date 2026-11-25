<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\UserRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\Session;
use Cva\Support\Url;
use PDOException;

/** Let administrators provision accounts and assign the supported staff roles. */
final class StaffAccountsController
{
    private const PAGE_SIZE = 25;

    public function index(): void
    {
        $this->renderPage();
    }

    public function save(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $users = new UserRepository($database);
        $actor = Session::authenticatedUser();
        $rawId = $_POST['user_id'] ?? '';
        if (!is_string($rawId)) {
            Flash::add('error', 'That staff account could not be identified.');
            header('Location: ' . Url::path('/admin/users'), true, 303);
            return;
        }
        $userId = $rawId === '' ? null : $this->requestedId($rawId);

        if ($rawId !== '' && $userId === null) {
            Flash::add('error', 'That staff account could not be identified.');
            header('Location: ' . Url::path('/admin/users'), true, 303);
            return;
        }

        $existing = $userId === null ? null : $users->findForAdmin($userId);
        if ($userId !== null && $existing === null) {
            Flash::add('error', 'That staff account could not be found.');
            header('Location: ' . Url::path('/admin/users'), true, 303);
            return;
        }

        // Bound and normalize every field before using it in SQL or HTML.
        $name = $this->boundedText($_POST['display_name'] ?? null, 160);
        $emailInput = $this->boundedText($_POST['email'] ?? null, 254);
        $email = $emailInput === null ? '' : strtolower(trim($emailInput));
        $role = $this->postedText('role_slug');
        $status = $this->postedText('status');
        $password = $this->postedText('new_password');
        $errors = [];

        if ($name === null || trim($name) === '') {
            $errors[] = 'Enter a display name of up to 160 characters.';
            $name = '';
        } else {
            $name = trim($name);
        }
        if ($emailInput === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
            $email = '';
        }
        if (!in_array($role, ['admin', 'staff'], true)) {
            $errors[] = 'Choose Administrator or Academy Staff as the role.';
            $role = 'staff';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Choose Active or Inactive as the account status.';
            $status = 'active';
        }
        if ($userId === null && ($password === '' || strlen($password) < 12 || strlen($password) > 72)) {
            $errors[] = 'A new account needs an initial password between 12 and 72 bytes.';
        } elseif ($password !== '' && (strlen($password) < 12 || strlen($password) > 72)) {
            $errors[] = 'A replacement password must be between 12 and 72 bytes.';
        }
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            $errors[] = 'This form expired. Reload the page and try again.';
        }

        $formState = [
            'display_name' => $name,
            'email' => $email,
            'role_slug' => $role,
            'status' => $status,
        ];

        if ($errors !== []) {
            http_response_code(422);
            $this->renderPage($formState, $errors, $userId);
            return;
        }

        $actorId = (int) ($actor['id'] ?? 0);
        $isSelf = $userId !== null && $userId === $actorId;
        if ($isSelf && (
            $role !== (string) $existing['role_slug']
            || $status !== (string) $existing['status']
        )) {
            http_response_code(422);
            $this->renderPage($formState, ['You cannot change your own role or deactivate your own account.'], $userId);
            return;
        }

        $account = $formState;
        if ($password !== '') {
            $account['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        // Do not keep the submitted password in this controller after hashing it.
        $password = '';

        $database->beginTransaction();
        try {
            // This shared lock serializes changes that could remove the final active admin.
            $users->lockAdministratorRole();

            if ($userId === null) {
                $savedId = $users->createAccount($account);
                (new AuditLogRepository($database))->record(
                    $actorId,
                    'staff.account.created',
                    (string) $savedId,
                    'user'
                );
                $successMessage = 'Staff account created. Share its initial password with the staff member through a private channel.';
            } else {
                $lockedAccount = $users->findForAdmin($userId, true);
                if ($lockedAccount === null) {
                    $database->rollBack();
                    Flash::add('error', 'That staff account could not be found.');
                    header('Location: ' . Url::path('/admin/users'), true, 303);
                    return;
                }

                $isSelf = $userId === $actorId;
                if ($isSelf && (
                    $role !== (string) $lockedAccount['role_slug']
                    || $status !== (string) $lockedAccount['status']
                )) {
                    $database->rollBack();
                    http_response_code(422);
                    $this->renderPage($formState, ['You cannot change your own role or deactivate your own account.'], $userId);
                    return;
                }

                $removesLastAdministrator = $lockedAccount['role_slug'] === 'admin'
                    && $lockedAccount['status'] === 'active'
                    && ($role !== 'admin' || $status !== 'active')
                    && $users->activeAdministratorCount() <= 1;
                if ($removesLastAdministrator) {
                    $database->rollBack();
                    http_response_code(422);
                    $this->renderPage($formState, ['At least one active administrator must remain. Create or activate another administrator first.'], $userId);
                    return;
                }

                $users->updateAccount($userId, $account);
                $audit = new AuditLogRepository($database);
                $audit->record($actorId, 'staff.account.updated', (string) $userId, 'user');
                if ($lockedAccount['role_slug'] !== $role) {
                    $audit->record($actorId, 'staff.account.role.changed', (string) $userId, 'user');
                }
                if ($lockedAccount['status'] !== $status) {
                    $audit->record($actorId, 'staff.account.status.changed', (string) $userId, 'user');
                }
                if (isset($account['password_hash'])) {
                    $audit->record($actorId, 'staff.account.password.reset', (string) $userId, 'user');
                }
                $successMessage = 'Staff account updated.';
            }

            $database->commit();
        } catch (PDOException $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ((string) $error->getCode() === '23000') {
                http_response_code(422);
                $this->renderPage($formState, ['That email address is already assigned to an account.'], $userId);
                return;
            }
            throw $error;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }

        Flash::add('success', $successMessage);
        header('Location: ' . Url::path('/admin/users'), true, 303);
    }

    /** Render the filtered staff list plus create/edit controls. */
    private function renderPage(array $formState = [], array $errors = [], ?int $editingUserId = null): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $users = new UserRepository(Database::connect($settings));
        $search = $this->requestedSearch($_GET['q'] ?? '');
        $status = is_string($_GET['status'] ?? null)
            && in_array($_GET['status'], ['all', 'active', 'inactive'], true)
                ? $_GET['status']
                : 'all';
        $filterStatus = $status === 'all' ? null : $status;
        $totalRows = $users->countForAdminList($filterStatus, $search);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $requestedPage = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT);
        $page = max(1, min($totalPages, $requestedPage === false ? 1 : $requestedPage));
        $staffAccounts = $users->pageForAdminList(
            $filterStatus,
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );
        if ($editingUserId !== null) {
            foreach ($staffAccounts as &$staffAccount) {
                if ((int) $staffAccount['id'] === $editingUserId) {
                    $staffAccount = array_merge($staffAccount, $formState);
                    break;
                }
            }
            unset($staffAccount);
        }

        $firstPage = max(1, $page - 2);
        $lastPage = min($totalPages, $firstPage + 4);
        $firstPage = max(1, $lastPage - 4);
        $paginationPages = range($firstPage, $lastPage);
        $createAccount = [
            'display_name' => '',
            'email' => '',
            'role_slug' => 'staff',
            'status' => 'active',
        ];
        if ($editingUserId === null && $formState !== []) {
            $createAccount = array_merge($createAccount, $formState);
        }
        $roles = $users->manageableRoles();
        $actor = Session::authenticatedUser();
        $currentUserId = (int) ($actor['id'] ?? 0);
        $notice = Flash::pull();
        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/Views/admin/staff-accounts.php';
    }

    private function postedText(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function boundedText($value, int $maximumBytes): ?string
    {
        if (!is_string($value) || strlen($value) > $maximumBytes || preg_match('//u', $value) !== 1) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            return null;
        }

        return trim($value);
    }

    private function requestedSearch($value): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            return '';
        }
        if (preg_match('/\A.{0,120}/us', $value, $matches) !== 1) {
            return '';
        }

        return trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $matches[0]));
    }

    private function requestedId(string $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id !== false && $id > 0 ? $id : null;
    }
}
