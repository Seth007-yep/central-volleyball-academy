<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\CoachRepository;
use Cva\Repositories\GalleryRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\Session;
use Cva\Support\Url;

/** Let staff maintain coach profiles, with explicit approval before public display. */
final class CoachesController
{
    private const PAGE_SIZE = 25;
    private const STATUSES = ['draft', 'published', 'archived'];

    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $repository = new CoachRepository(Database::connect($settings));
        $statusValue = $_GET['status'] ?? 'all';
        $status = is_string($statusValue) && in_array($statusValue, self::STATUSES, true)
            ? $statusValue
            : null;
        $search = $this->boundedText($_GET['q'] ?? '', 120) ?? '';
        $totalRows = $repository->countForAdminList($status, $search);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $pageValue = is_string($_GET['page'] ?? null) || is_int($_GET['page'] ?? null)
            ? filter_var($_GET['page'], FILTER_VALIDATE_INT)
            : false;
        $page = max(1, min($totalPages, $pageValue === false ? 1 : $pageValue));
        $coaches = $repository->pageForAdminList(
            $status,
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );
        $paginationPages = range(max(1, $page - 2), min($totalPages, $page + 2));
        $notice = Flash::pull();
        $adminPage = 'coaches';

        require dirname(__DIR__, 2) . '/Views/admin/coaches.php';
    }

    public function edit(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $rawId = $_GET['id'] ?? null;
        $id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        if ($rawId !== null && ($id === false || $id < 1)) {
            Flash::add('error', 'That coach profile link is not valid.');
            header('Location: ' . Url::path('/admin/coaches'), true, 303);
            exit;
        }
        $coach = $id !== false && $id > 0
            ? (new CoachRepository($database))->findForAdmin($id)
            : null;

        if ($id !== false && $id !== null && $coach === null) {
            Flash::add('error', 'That coach profile could not be found.');
            header('Location: ' . Url::path('/admin/coaches'), true, 303);
            exit;
        }

        // Never offer unpublished photos as profile images.
        $photoOptions = (new GalleryRepository($database))->publishedImages();
        $coach ??= [
            'id' => null,
            'first_name' => '',
            'last_name' => '',
            'role_title' => '',
            'biography' => '',
            'experience' => '',
            'qualifications' => '',
            'photo_gallery_item_id' => null,
            'display_order' => 0,
            'status' => 'draft',
        ];
        $errors = [];
        $notice = Flash::pull();
        $csrfToken = Csrf::token();
        $adminPage = 'coaches';

        require dirname(__DIR__, 2) . '/Views/admin/coach-edit.php';
    }

    public function save(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $rawCoachId = $_POST['coach_id'] ?? '';
        $coachId = $rawCoachId === '' ? null : $this->requestedId($rawCoachId);
        if ($rawCoachId !== '' && $coachId === null) {
            Flash::add('error', 'That coach profile link is not valid.');
            header('Location: ' . Url::path('/admin/coaches'), true, 303);
            exit;
        }
        $repository = new CoachRepository($database);
        $existing = $coachId === null ? null : $repository->findForAdmin($coachId);
        if ($coachId !== null && $existing === null) {
            Flash::add('error', 'That coach profile could not be found.');
            header('Location: ' . Url::path('/admin/coaches'), true, 303);
            exit;
        }

        $values = [];
        $errors = [];
        $limits = [
            'first_name' => 80,
            'last_name' => 80,
            'role_title' => 120,
            'biography' => 4000,
            'experience' => 2000,
            'qualifications' => 2000,
        ];
        foreach ($limits as $field => $maximumBytes) {
            $value = $this->boundedText($_POST[$field] ?? null, $maximumBytes);
            if ($value === null) {
                $errors[$field] = 'Enter plain text within the displayed character limit.';
                $value = '';
            }
            $values[$field] = $value;
        }
        foreach (['first_name', 'last_name', 'role_title', 'biography'] as $requiredField) {
            if ($values[$requiredField] === '') {
                $errors[$requiredField] = 'This field is required.';
            }
        }
        foreach (['first_name', 'last_name', 'role_title'] as $singleLineField) {
            if (preg_match('/[\r\n\t]/', $values[$singleLineField]) === 1) {
                $errors[$singleLineField] = 'Use one line of plain text for this field.';
            }
        }

        $status = $_POST['status'] ?? null;
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose a valid profile status.';
            $status = 'draft';
        }
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'This form expired. Reload the page and try again.';
        }
        if ($status === 'published' && ($_POST['academy_approval'] ?? null) !== '1') {
            $errors['academy_approval'] = 'Confirm that the profile details and photograph are academy-approved before publishing.';
        }

        $rawOrder = $_POST['display_order'] ?? '0';
        $displayOrder = is_string($rawOrder) ? filter_var($rawOrder, FILTER_VALIDATE_INT) : false;
        if ($displayOrder === false || $displayOrder < 0 || $displayOrder > 9999) {
            $errors['display_order'] = 'Enter a display order from 0 to 9999.';
            $displayOrder = 0;
        }

        $rawPhotoId = $_POST['photo_gallery_item_id'] ?? '';
        $photoId = $rawPhotoId === '' ? null : $this->requestedId($rawPhotoId);
        if ($rawPhotoId !== '' && $photoId === null) {
            $errors['photo_gallery_item_id'] = 'Choose an available approved photo or leave this field blank.';
        }
        $photoOptions = (new GalleryRepository($database))->publishedImages();
        if ($photoId !== null) {
            $approvedImageIds = array_map(static fn (array $image): int => (int) $image['id'], $photoOptions);
            if (!in_array($photoId, $approvedImageIds, true)) {
                $errors['photo_gallery_item_id'] = 'Choose an image that is currently published in the gallery.';
            }
        }

        $values['qualifications'] = $values['qualifications'] === '' ? null : $values['qualifications'];
        $values['experience'] = $values['experience'] === '' ? null : $values['experience'];
        $values['photo_gallery_item_id'] = $photoId;
        $values['display_order'] = $displayOrder;
        $values['status'] = $status;
        $coach = array_merge($existing ?? ['id' => null], $values);

        if ($errors !== []) {
            http_response_code(422);
            $notice = null;
            $csrfToken = Csrf::token();
            $adminPage = 'coaches';
            require dirname(__DIR__, 2) . '/Views/admin/coach-edit.php';
            return;
        }

        // Save the profile and its audit record together so both succeed or neither does.
        $actor = Session::authenticatedUser();
        $database->beginTransaction();
        try {
            $savedId = $repository->save($values, $coachId);
            $wasPublished = ($existing['status'] ?? null) === 'published';
            $action = $coachId === null ? 'coach.created' : 'coach.updated';
            if ($status === 'published' && !$wasPublished) {
                $action = 'coach.published';
            } elseif ($wasPublished && $status !== 'published') {
                $action = 'coach.status.changed';
            }
            (new AuditLogRepository($database))->record($actor['id'] ?? null, $action, (string) $savedId, 'coach');
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }

        Flash::add('success', 'Coach profile saved.');
        header('Location: ' . Url::path('/admin/coaches'), true, 303);
        exit;
    }

    private function boundedText($value, int $maximumBytes): ?string
    {
        if (!is_string($value) || strlen($value) > $maximumBytes || preg_match('//u', $value) !== 1) {
            return null;
        }
        if (preg_match('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            return null;
        }

        return trim($value);
    }

    private function requestedId($value): ?int
    {
        $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        return $id !== false && $id > 0 ? $id : null;
    }
}
