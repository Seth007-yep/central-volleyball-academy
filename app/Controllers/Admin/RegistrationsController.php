<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\RegistrationRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\Session;
use Cva\Support\Url;

/** Search applications and move them through the staff review workflow. */
final class RegistrationsController
{
    private const PAGE_SIZE = 20;
    private const STATUSES = ['pending', 'under_review', 'approved', 'rejected', 'needs_information', 'cancelled'];
    private const REVIEW_CONFIRMATION_TTL_SECONDS = 600;

    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $repository = new RegistrationRepository(Database::connect($settings));
        $status = $this->requestedStatus($_GET['status'] ?? 'all');
        $search = $this->requestedSearch($_GET['q'] ?? '');
        $phone = $this->requestedPhone($_GET['phone'] ?? '');
        $program = $this->requestedSearch($_GET['program'] ?? '');
        $dateFromInput = $this->requestedDateInput($_GET['date_from'] ?? '');
        $dateToInput = $this->requestedDateInput($_GET['date_to'] ?? '');
        $dateFrom = $this->requestedDate($dateFromInput);
        $dateTo = $this->requestedDate($dateToInput);
        $filterNotice = null;
        if ($dateFromInput !== '' && $dateFrom === null || $dateToInput !== '' && $dateTo === null) {
            $filterNotice = 'Enter valid dates for the received-date range.';
        } elseif ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $filterNotice = 'The received-date start must be on or before the end date.';
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
        $registrations = $repository->pageForAdminList(
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
        $adminPage = 'registrations';
        require dirname(__DIR__, 2) . '/Views/admin/registrations.php';
    }

    public function show(): void
    {
        $id = $this->requestedId($_GET['id'] ?? null);
        if ($id === null) {
            Flash::add('error', 'Choose a valid application to review.');
            header('Location: ' . Url::path('/admin/registrations'), true, 303);
            exit;
        }

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $registration = (new RegistrationRepository(Database::connect($settings)))->findForAdmin($id);
        if ($registration === null) {
            Flash::add('error', 'That application could not be found.');
            header('Location: ' . Url::path('/admin/registrations'), true, 303);
            exit;
        }

        $csrfToken = Csrf::token();
        $notice = Flash::pull();
        $adminPage = 'registrations';
        require dirname(__DIR__, 2) . '/Views/admin/registration-detail.php';
    }

    /** Validate the chosen outcome and show a separate, non-mutating confirmation page. */
    public function prepareReview(): void
    {
        $id = $this->requestedId($_POST['registration_id'] ?? null);
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : null;
        $notes = $_POST['admin_notes'] ?? '';
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)
            || $id === null
            || $status === null
            || !in_array($status, self::STATUSES, true)
            || !is_string($notes)
            || strlen($notes) > 2000
            || preg_match('//u', $notes) !== 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $notes) === 1) {
            http_response_code(400);
            Flash::add('error', 'The review form was invalid. Reload the application and try again.');
            header('Location: ' . Url::path('/admin/registrations'), true, 303);
            exit;
        }

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $registration = (new RegistrationRepository(Database::connect($settings)))->findForAdmin($id);
        if ($registration === null) {
            Flash::add('error', 'That application could not be found.');
            header('Location: ' . Url::path('/admin/registrations'), true, 303);
            exit;
        }

        // Keep the pending action server-side so a forged confirm POST cannot choose its outcome.
        Session::start();
        $pendingReview = [
            'id' => $id,
            'status' => $status,
            'notes' => trim($notes) === '' ? null : trim($notes),
            'source_status' => (string) $registration['status'],
            'token' => bin2hex(random_bytes(32)),
            'created_at' => time(),
        ];
        $_SESSION['pending_registration_review'] = $pendingReview;
        $statusLabels = [
            'pending' => 'Pending', 'under_review' => 'Under review', 'approved' => 'Approved',
            'rejected' => 'Rejected', 'needs_information' => 'Needs information', 'cancelled' => 'Cancelled',
        ];
        $csrfToken = Csrf::token();
        $adminPage = 'registrations';
        require dirname(__DIR__, 2) . '/Views/admin/review-confirmation.php';
    }

    /** Apply only the exact action shown on the second confirmation screen. */
    public function confirmReview(): void
    {
        Session::start();
        $pendingReview = $_SESSION['pending_registration_review'] ?? null;
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'This confirmation could not be verified. Return to the application and try again.';
            return;
        }

        $submittedToken = $_POST['confirmation_token'] ?? null;
        if (!is_array($pendingReview)
            || !is_string($submittedToken)
            || !is_string($pendingReview['token'] ?? null)
            || !hash_equals($pendingReview['token'], $submittedToken)
            || !is_int($pendingReview['created_at'] ?? null)
            || time() - $pendingReview['created_at'] > self::REVIEW_CONFIRMATION_TTL_SECONDS) {
            unset($_SESSION['pending_registration_review']);
            Flash::add('error', 'That review confirmation expired. Open the application and start again.');
            header('Location: ' . Url::path('/admin/registrations'), true, 303);
            return;
        }

        $id = (int) $pendingReview['id'];
        $status = (string) $pendingReview['status'];
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $repository = new RegistrationRepository(Database::connect($settings));
        $actor = Session::authenticatedUser();
        try {
            $repository->review(
                $id,
                $status,
                is_string($pendingReview['notes'] ?? null) ? $pendingReview['notes'] : null,
                $actor['id'] ?? null,
                (string) $pendingReview['source_status']
            );
        } catch (\RuntimeException $error) {
            unset($_SESSION['pending_registration_review']);
            Flash::add('error', 'The application changed while you were reviewing it. Reload the application before making another decision.');
            header('Location: ' . Url::path('/admin/registrations/view') . '?id=' . $id, true, 303);
            return;
        }
        unset($_SESSION['pending_registration_review']);

        Flash::add('success', $status === 'approved'
            ? 'Application approved and player record created.'
            : 'Application review status saved.');
        header('Location: ' . Url::path('/admin/registrations/view') . '?id=' . $id, true, 303);
    }

    /** Discard a staged action without changing the registration. */
    public function cancelReview(): void
    {
        Session::start();
        $pendingReview = $_SESSION['pending_registration_review'] ?? null;
        $submittedToken = $_POST['confirmation_token'] ?? null;
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)
            || !is_array($pendingReview)
            || !is_string($submittedToken)
            || !is_string($pendingReview['token'] ?? null)
            || !hash_equals($pendingReview['token'], $submittedToken)) {
            http_response_code(403);
            echo 'This review confirmation could not be cancelled. Return to the application and try again.';
            return;
        }

        $id = (int) $pendingReview['id'];
        unset($_SESSION['pending_registration_review']);
        header('Location: ' . Url::path('/admin/registrations/view') . '?id=' . $id, true, 303);
    }

    private function requestedStatus($value): ?string
    {
        return is_string($value) && in_array($value, self::STATUSES, true) ? $value : null;
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
