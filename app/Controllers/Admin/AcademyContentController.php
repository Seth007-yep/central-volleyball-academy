<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AcademyContentRepository;
use Cva\Repositories\AuditLogRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\Session;
use Cva\Support\Url;

/** Let authorized staff update the approved public academy information. */
final class AcademyContentController
{
    private const FIELD_LIMITS = [
        'introduction' => 2000,
        'about_text' => 6000,
        'mission' => 2000,
        'vision' => 2000,
        'coaching_philosophy' => 4000,
        'contact_email' => 254,
        'contact_phone' => 40,
        'contact_location' => 255,
    ];

    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $content = (new AcademyContentRepository(Database::connect($settings)))->current();
        $errors = [];
        $notice = Flash::pull();
        $csrfToken = Csrf::token();
        $adminPage = 'academy';
        require dirname(__DIR__, 2) . '/Views/admin/academy-content.php';
    }

    public function save(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $content = [];
        $errors = [];
        foreach (self::FIELD_LIMITS as $field => $limit) {
            $value = $_POST[$field] ?? '';
            if (!is_string($value)) {
                $value = '';
                $errors[$field] = 'Enter this value as plain text.';
            } elseif (strlen($value) > $limit) {
                $value = '';
                $errors[$field] = 'This value is too long.';
            } else {
                $value = trim($value);
                if (preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
                    $errors[$field] = 'Remove invalid control characters from this field.';
                }
            }
            $content[$field] = $value === '' ? null : $value;
        }

        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $errors['form'] = 'This form expired. Reload the page and try again.';
        }
        if ($content['contact_email'] !== null && filter_var($content['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['contact_email'] = 'Enter a valid contact email or leave the field blank.';
        }
        if ($content['contact_phone'] !== null && !preg_match('/\A[0-9+(). \t\-]{5,40}\z/', $content['contact_phone'])) {
            $errors['contact_phone'] = 'Enter a phone number using digits and common phone punctuation.';
        }

        if ($errors !== []) {
            http_response_code(http_response_code() === 400 ? 400 : 422);
            $notice = null;
            $csrfToken = Csrf::token();
            $adminPage = 'academy';
            require dirname(__DIR__, 2) . '/Views/admin/academy-content.php';
            return;
        }

        $actor = Session::authenticatedUser();
        $database->beginTransaction();
        try {
            (new AcademyContentRepository($database))->save($content);
            (new AuditLogRepository($database))->record(
                $actor['id'] ?? null,
                'academy.content.updated',
                '1',
                'academy_content'
            );
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
        Flash::add('success', 'Academy information saved.');
        header('Location: ' . Url::path('/admin/academy'), true, 303);
        exit;
    }
}
