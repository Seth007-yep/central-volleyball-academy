<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use DateTimeImmutable;
use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\EventRepository;
use Cva\Repositories\GalleryRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\Session;
use Cva\Support\Url;

/** Maintain academy activities and moderate whether each event is public. */
final class EventsController
{
    private const PAGE_SIZE = 20;
    private const STATUSES = ['draft', 'published', 'cancelled', 'archived'];
    private const REGISTRATION_STATUSES = ['open', 'closed', 'not_required'];

    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $repository = new EventRepository(Database::connect($settings));
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
        $events = $repository->pageForAdminList($status, $search, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        $paginationPages = range(max(1, $page - 2), min($totalPages, $page + 2));
        $notice = Flash::pull();
        $adminPage = 'events';

        require dirname(__DIR__, 2) . '/Views/admin/events.php';
    }

    public function edit(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $rawId = $_GET['id'] ?? null;
        $id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        if ($rawId !== null && ($id === false || $id < 1)) {
            Flash::add('error', 'That event link is not valid.');
            header('Location: ' . Url::path('/admin/events'), true, 303);
            exit;
        }
        $event = $id !== false && $id > 0 ? (new EventRepository($database))->findForAdmin($id) : null;
        if ($id !== false && $id !== null && $event === null) {
            Flash::add('error', 'That event could not be found.');
            header('Location: ' . Url::path('/admin/events'), true, 303);
            exit;
        }
        $event ??= [
            'id' => null,
            'title' => '',
            'description' => '',
            'starts_at' => '',
            'ends_at' => '',
            'location' => '',
            'registration_status' => 'not_required',
            'photo_gallery_item_id' => null,
            'status' => 'draft',
        ];
        $photoOptions = (new GalleryRepository($database))->publishedImages();
        $errors = [];
        $notice = Flash::pull();
        $csrfToken = Csrf::token();
        $adminPage = 'events';

        require dirname(__DIR__, 2) . '/Views/admin/event-edit.php';
    }

    public function save(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $rawEventId = $_POST['event_id'] ?? '';
        $eventId = $rawEventId === '' ? null : $this->requestedId($rawEventId);
        if ($rawEventId !== '' && $eventId === null) {
            Flash::add('error', 'That event link is not valid.');
            header('Location: ' . Url::path('/admin/events'), true, 303);
            exit;
        }
        $repository = new EventRepository($database);
        $existing = $eventId === null ? null : $repository->findForAdmin($eventId);
        if ($eventId !== null && $existing === null) {
            Flash::add('error', 'That event could not be found.');
            header('Location: ' . Url::path('/admin/events'), true, 303);
            exit;
        }

        $values = [];
        $errors = [];
        foreach (['title' => 160, 'description' => 4000, 'location' => 255] as $field => $limit) {
            $value = $this->boundedText($_POST[$field] ?? '', $limit);
            if ($value === null) {
                $errors[$field] = 'Enter plain text within the displayed limit.';
                $value = '';
            }
            $values[$field] = $value;
        }
        if ($values['title'] === '' || preg_match('/[\r\n\t]/', $values['title']) === 1) {
            $errors['title'] = 'Enter a one-line event title.';
        }
        foreach (['description', 'location'] as $field) {
            if ($values[$field] === '') {
                $errors[$field] = 'This field is required.';
            }
        }
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'This form expired. Reload the page and try again.';
        }

        // datetime-local carries no timezone; store the entered wall time and confirm the
        // academy timezone/server configuration before publishing real event dates.
        $rawStartsAt = $_POST['starts_at'] ?? null;
        $rawEndsAt = $_POST['ends_at'] ?? null;
        $startsAt = $this->parseLocalDateTime($rawStartsAt);
        $endsAt = $this->parseLocalDateTime($rawEndsAt);
        if ($startsAt === null) {
            $errors['starts_at'] = 'Enter a valid event start date and time.';
        }
        if ($endsAt === null) {
            $errors['ends_at'] = 'Enter a valid event end date and time.';
        } elseif ($startsAt !== null && $endsAt <= $startsAt) {
            $errors['ends_at'] = 'The event must end after it starts.';
        }

        $registrationStatus = $_POST['registration_status'] ?? null;
        if (!is_string($registrationStatus) || !in_array($registrationStatus, self::REGISTRATION_STATUSES, true)) {
            $errors['registration_status'] = 'Choose a valid event registration status.';
            $registrationStatus = 'not_required';
        }
        $status = $_POST['status'] ?? null;
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose a valid event status.';
            $status = 'draft';
        }
        if ($status === 'published' && ($_POST['academy_approval'] ?? null) !== '1') {
            $errors['academy_approval'] = 'Confirm that the event details and photo are academy-approved before publishing.';
        }
        if ($status === 'cancelled' && ($_POST['cancel_confirmation'] ?? null) !== '1') {
            $errors['cancel_confirmation'] = 'Confirm that this event should be marked cancelled.';
        }

        $rawPhotoId = $_POST['photo_gallery_item_id'] ?? '';
        $photoId = $rawPhotoId === '' ? null : $this->requestedId($rawPhotoId);
        if ($rawPhotoId !== '' && $photoId === null) {
            $errors['photo_gallery_item_id'] = 'Choose an approved gallery image or leave the photo blank.';
        }
        // Re-check image publication on save so a forged/stale ID cannot expose a draft image.
        $photoOptions = (new GalleryRepository($database))->publishedImages();
        if ($photoId !== null) {
            $publishedImageIds = array_map(static fn (array $image): int => (int) $image['id'], $photoOptions);
            if (!in_array($photoId, $publishedImageIds, true)) {
                $errors['photo_gallery_item_id'] = 'Choose an image that is currently published in the gallery.';
            }
        }

        $values['starts_at'] = $startsAt ?? (is_string($rawStartsAt) ? $rawStartsAt : '');
        $values['ends_at'] = $endsAt ?? (is_string($rawEndsAt) ? $rawEndsAt : '');
        $values['registration_status'] = $registrationStatus;
        $values['photo_gallery_item_id'] = $photoId;
        $values['status'] = $status;
        $values['id'] = $eventId;
        $event = array_merge($existing ?? ['id' => null], $values);

        if ($errors !== []) {
            http_response_code(422);
            $notice = null;
            $csrfToken = Csrf::token();
            $adminPage = 'events';
            require dirname(__DIR__, 2) . '/Views/admin/event-edit.php';
            return;
        }

        $actor = Session::authenticatedUser();
        $database->beginTransaction();
        try {
            // Event details and the audit entry commit together, including cancellation changes.
            $savedId = $repository->save($values, $eventId);
            $wasPublished = ($existing['published_at'] ?? null) !== null;
            if ($eventId === null) {
                $action = $status === 'published' ? 'event.published' : 'event.created';
            } elseif ($status === 'published' && !$wasPublished) {
                $action = 'event.published';
            } elseif (($existing['status'] ?? null) !== $status) {
                $action = 'event.status.changed';
            } else {
                $action = 'event.updated';
            }
            (new AuditLogRepository($database))->record($actor['id'] ?? null, $action, (string) $savedId, 'event');
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }

        Flash::add('success', 'Event saved.');
        header('Location: ' . Url::path('/admin/events'), true, 303);
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

    private function parseLocalDateTime($value): ?string
    {
        if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $issues = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable || (is_array($issues) && ($issues['warning_count'] > 0 || $issues['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function requestedId($value): ?int
    {
        $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        return $id !== false && $id > 0 ? $id : null;
    }
}
