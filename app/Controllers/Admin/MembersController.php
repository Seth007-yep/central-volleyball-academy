<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\MemberRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\RegistrationValidator;
use Cva\Support\Session;
use Cva\Support\Url;

/** Allow staff to view, update, and deactivate approved player records. */
final class MembersController
{
    private const PAGE_SIZE = 25;
    private const STATUSES = ['active', 'inactive', 'suspended'];

    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $repository = new MemberRepository(Database::connect($settings));
        $statusValue = $_GET['status'] ?? 'all';
        $status = is_string($statusValue) && in_array($statusValue, self::STATUSES, true)
            ? $statusValue
            : null;
        $search = $this->requestedSearch($_GET['q'] ?? '');
        $phone = $this->requestedPhone($_GET['phone'] ?? '');
        $program = $this->requestedSearch($_GET['program'] ?? '');
        $dateFromInput = $this->requestedDateInput($_GET['date_from'] ?? '');
        $dateToInput = $this->requestedDateInput($_GET['date_to'] ?? '');
        $dateFrom = $this->requestedDate($dateFromInput);
        $dateTo = $this->requestedDate($dateToInput);
        $filterNotice = null;
        if ($dateFromInput !== '' && $dateFrom === null || $dateToInput !== '' && $dateTo === null) {
            $filterNotice = 'Enter valid dates for the date-of-birth range.';
        } elseif ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $filterNotice = 'The date-of-birth start must be on or before the end date.';
            $dateFrom = null;
            $dateTo = null;
        }
        $totalRows = $repository->countForAdminList($status, $search, $phone, $program, $dateFrom, $dateTo);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $rawPage = $_GET['page'] ?? '1';
        $pageValue = is_string($rawPage) || is_int($rawPage)
            ? filter_var($rawPage, FILTER_VALIDATE_INT)
            : false;
        $page = max(1, min($totalPages, $pageValue === false ? 1 : $pageValue));
        $members = $repository->pageForAdminList(
            $status,
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE,
            $phone,
            $program,
            $dateFrom,
            $dateTo
        );
        $firstPage = max(1, $page - 2);
        $lastPage = min($totalPages, $firstPage + 4);
        $firstPage = max(1, $lastPage - 4);
        $paginationPages = range($firstPage, $lastPage);
        $notice = Flash::pull();
        $csrfToken = Csrf::token();
        $adminPage = 'players';
        require dirname(__DIR__, 2) . '/Views/admin/members.php';
    }

    public function edit(): void
    {
        $id = $this->requestedId($_GET['id'] ?? null);
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $member = $id === null ? null : (new MemberRepository(Database::connect($settings)))->findForAdmin($id);
        if ($member === null) {
            Flash::add('error', 'That player record could not be found.');
            header('Location: ' . Url::path('/admin/players'), true, 303);
            exit;
        }

        $errors = [];
        $notice = Flash::pull();
        $csrfToken = Csrf::token();
        $adminPage = 'players';
        require dirname(__DIR__, 2) . '/Views/admin/member-edit.php';
    }

    public function update(): void
    {
        $id = $this->requestedId($_POST['member_id'] ?? null);
        $status = $_POST['status'] ?? null;
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $repository = new MemberRepository($database);
        $member = $id === null ? null : $repository->findForAdmin($id);
        if ($member === null) {
            Flash::add('error', 'That player record could not be found.');
            header('Location: ' . Url::path('/admin/players'), true, 303);
            exit;
        }

        $formInput = array_merge($_POST, ['contact_consent' => '1']);
        $result = RegistrationValidator::validate($formInput);
        $errors = $result['errors'];
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'This form expired. Reload the player record and try again.';
        }
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose a valid player status.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $member = array_merge($member, $result['values']);
            if (is_string($status)) {
                $member['status'] = $status;
            }
            $notice = null;
            $csrfToken = Csrf::token();
            $adminPage = 'players';
            require dirname(__DIR__, 2) . '/Views/admin/member-edit.php';
            return;
        }

        $values = $result['values'];
        $values['status'] = $status;
        $actor = Session::authenticatedUser();
        $database->beginTransaction();
        try {
            $repository->update($id, $values);
            (new AuditLogRepository($database))->record(
                $actor['id'] ?? null,
                'member.updated',
                (string) $id,
                'member'
            );
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
        Flash::add('success', 'Player record saved.');
        header('Location: ' . Url::path('/admin/players'), true, 303);
        exit;
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

    private function requestedPhone($value): string
    {
        if (!is_string($value) || strlen($value) > 80) {
            return '';
        }

        return substr(preg_replace('/[^0-9]/', '', $value) ?? '', 0, 40);
    }

    private function requestedDateInput($value): string
    {
        return is_string($value) && strlen($value) <= 10 ? trim($value) : '';
    }

    private function requestedDate(string $value): ?string
    {
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    private function requestedId($value): ?int
    {
        $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        return $id !== false && $id > 0 ? $id : null;
    }
}
