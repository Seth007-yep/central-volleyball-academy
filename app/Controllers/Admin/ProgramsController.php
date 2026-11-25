<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\CoachRepository;
use Cva\Repositories\ProgramRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\Session;
use Cva\Support\Url;

/** Maintain program details, schedules, and approved coach assignments. */
final class ProgramsController
{
    private const PAGE_SIZE = 20;
    private const STATUSES = ['draft', 'published', 'archived'];

    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $repository = new ProgramRepository(Database::connect($settings));
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
        $programs = $repository->pageForAdminList($status, $search, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        $paginationPages = range(max(1, $page - 2), min($totalPages, $page + 2));
        $notice = Flash::pull();
        $adminPage = 'programs';

        require dirname(__DIR__, 2) . '/Views/admin/programs.php';
    }

    public function edit(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $rawId = $_GET['id'] ?? null;
        $id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        if ($rawId !== null && ($id === false || $id < 1)) {
            Flash::add('error', 'That program profile link is not valid.');
            header('Location: ' . Url::path('/admin/programs'), true, 303);
            exit;
        }
        $repository = new ProgramRepository($database);
        $program = $id !== false && $id > 0 ? $repository->findForAdmin($id) : null;
        if ($id !== false && $id !== null && $program === null) {
            Flash::add('error', 'That program could not be found.');
            header('Location: ' . Url::path('/admin/programs'), true, 303);
            exit;
        }
        $program ??= [
            'id' => null,
            'title' => '',
            'category' => '',
            'age_group' => '',
            'description' => '',
            'fee_details' => '',
            'capacity' => null,
            'display_order' => 0,
            'status' => 'draft',
        ];
        $program['schedule_lines'] = $id !== false && $id > 0
            ? implode("\n", $repository->scheduleLines($id))
            : '';
        $selectedCoachIds = $id !== false && $id > 0 ? $repository->assignedCoachIds($id) : [];
        $coachOptions = (new CoachRepository($database))->publishedForProgramOptions();
        $errors = [];
        $notice = Flash::pull();
        $csrfToken = Csrf::token();
        $adminPage = 'programs';

        require dirname(__DIR__, 2) . '/Views/admin/program-edit.php';
    }

    public function save(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $rawProgramId = $_POST['program_id'] ?? '';
        $programId = $rawProgramId === '' ? null : $this->requestedId($rawProgramId);
        if ($rawProgramId !== '' && $programId === null) {
            Flash::add('error', 'That program profile link is not valid.');
            header('Location: ' . Url::path('/admin/programs'), true, 303);
            exit;
        }
        $repository = new ProgramRepository($database);
        $existing = $programId === null ? null : $repository->findForAdmin($programId);
        if ($programId !== null && $existing === null) {
            Flash::add('error', 'That program could not be found.');
            header('Location: ' . Url::path('/admin/programs'), true, 303);
            exit;
        }

        // Validate the submitted plain text and numeric fields before writing anything.
        $limits = [
            'title' => 120,
            'category' => 80,
            'age_group' => 120,
            'description' => 4000,
            'fee_details' => 255,
        ];
        $values = [];
        $errors = [];
        foreach ($limits as $field => $maximumBytes) {
            $value = $this->boundedText($_POST[$field] ?? '', $maximumBytes);
            if ($value === null) {
                $errors[$field] = 'Enter plain text within the displayed limit.';
                $value = '';
            }
            $values[$field] = $value;
        }
        foreach (['title', 'category', 'description'] as $requiredField) {
            if ($values[$requiredField] === '') {
                $errors[$requiredField] = 'This field is required.';
            }
        }
        foreach (['title', 'category', 'age_group'] as $singleLineField) {
            if (preg_match('/[\r\n\t]/', $values[$singleLineField]) === 1) {
                $errors[$singleLineField] = 'Use one line of plain text for this field.';
            }
        }
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'This form expired. Reload the page and try again.';
        }

        $rawCapacity = $_POST['capacity'] ?? '';
        $capacity = $rawCapacity === '' ? null : (is_string($rawCapacity) ? filter_var($rawCapacity, FILTER_VALIDATE_INT) : false);
        if ($capacity !== null && ($capacity === false || $capacity < 1 || $capacity > 100000)) {
            $errors['capacity'] = 'Enter a capacity from 1 to 100000, or leave it blank.';
            $capacity = null;
        }
        $rawOrder = $_POST['display_order'] ?? '0';
        $displayOrder = is_string($rawOrder) ? filter_var($rawOrder, FILTER_VALIDATE_INT) : false;
        if ($displayOrder === false || $displayOrder < 0 || $displayOrder > 9999) {
            $errors['display_order'] = 'Enter a display order from 0 to 9999.';
            $displayOrder = 0;
        }
        $status = $_POST['status'] ?? null;
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose a valid program status.';
            $status = 'draft';
        }
        if ($status === 'published' && ($_POST['academy_approval'] ?? null) !== '1') {
            $errors['academy_approval'] = 'Confirm that the program information is academy-approved before publishing.';
        }

        $rawScheduleLines = $_POST['schedule_lines'] ?? '';
        $scheduleLines = [];
        if (
            !is_string($rawScheduleLines)
            || strlen($rawScheduleLines) > 2550
            || preg_match('//u', $rawScheduleLines) !== 1
            || preg_match('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', $rawScheduleLines) === 1
        ) {
            $errors['schedule_lines'] = 'Enter up to ten plain-text schedule lines, each no longer than 255 characters.';
            $rawScheduleLines = '';
        } else {
            foreach (preg_split('/\R/u', $rawScheduleLines) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (strlen($line) > 255) {
                    $errors['schedule_lines'] = 'Each schedule line must be 255 characters or fewer.';
                }
                $scheduleLines[] = $line;
            }
            if (count($scheduleLines) > 10) {
                $errors['schedule_lines'] = 'Enter no more than ten schedule lines.';
            }
        }

        $rawCoachIds = $_POST['coach_ids'] ?? [];
        $coachIds = [];
        if (!is_array($rawCoachIds) || count($rawCoachIds) > 20) {
            $errors['coach_ids'] = 'Choose up to twenty published coaches.';
        } else {
            foreach ($rawCoachIds as $rawCoachId) {
                $coachId = $this->requestedId($rawCoachId);
                if ($coachId === null) {
                    $errors['coach_ids'] = 'Choose only coaches listed in the published-coach selector.';
                    continue;
                }
                $coachIds[] = $coachId;
            }
            $coachIds = array_values(array_unique($coachIds));
        }
        // The published-coach list is the allowlist; a forged POST cannot assign a draft profile.
        $coachOptions = (new CoachRepository($database))->publishedForProgramOptions();
        $availableCoachIds = array_map(static fn (array $coach): int => (int) $coach['id'], $coachOptions);
        if (array_diff($coachIds, $availableCoachIds) !== []) {
            $errors['coach_ids'] = 'Only currently published coach profiles can be assigned to a public program.';
        }

        $values['age_group'] = $values['age_group'] === '' ? null : $values['age_group'];
        $values['fee_details'] = $values['fee_details'] === '' ? null : $values['fee_details'];
        $values['capacity'] = $capacity;
        $values['display_order'] = $displayOrder;
        $values['status'] = $status;
        $program = array_merge($existing ?? ['id' => null], $values, ['schedule_lines' => $rawScheduleLines]);
        $selectedCoachIds = $coachIds;

        if ($errors !== []) {
            http_response_code(422);
            $notice = null;
            $csrfToken = Csrf::token();
            $adminPage = 'programs';
            require dirname(__DIR__, 2) . '/Views/admin/program-edit.php';
            return;
        }

        $actor = Session::authenticatedUser();
        $database->beginTransaction();
        try {
            // Keep the program, its schedule, its coach links, and audit row in one atomic change.
            $savedId = $repository->save($values, $programId);
            $repository->replaceScheduleLines($savedId, $scheduleLines);
            $repository->replaceCoachAssignments($savedId, $coachIds);
            $wasPublished = ($existing['status'] ?? null) === 'published';
            $action = $programId === null ? 'program.created' : 'program.updated';
            if ($status === 'published' && !$wasPublished) {
                $action = 'program.published';
            } elseif ($wasPublished && $status !== 'published') {
                $action = 'program.status.changed';
            }
            (new AuditLogRepository($database))->record($actor['id'] ?? null, $action, (string) $savedId, 'program');
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }

        Flash::add('success', 'Program saved.');
        header('Location: ' . Url::path('/admin/programs'), true, 303);
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
