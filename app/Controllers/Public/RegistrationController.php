<?php

declare(strict_types=1);

namespace Cva\Controllers\Public;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\RegistrationRepository;
use Cva\Repositories\RegistrationRateLimitRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\RegistrationValidator;
use Cva\Support\Url;

/** Accept a minimal online application and leave it pending for staff review. */
final class RegistrationController
{
    public function showForm(): void
    {
        $registrationValues = [];
        $errors = [];
        $csrfToken = Csrf::token();
        $adultAge = RegistrationValidator::adultAgeThreshold();
        require dirname(__DIR__, 2) . '/Views/public/registration.php';
    }

    public function submit(): void
    {
        $adultAge = RegistrationValidator::adultAgeThreshold();
        $csrfToken = Csrf::token();
        $registrationValues = $_POST;

        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $errors = ['form' => 'This form expired. Reload the page and submit it again.'];
            require dirname(__DIR__, 2) . '/Views/public/registration.php';
            return;
        }

        $result = RegistrationValidator::validate($_POST);
        $registrationValues = $result['values'];
        $errors = $result['errors'];
        if ($errors !== []) {
            http_response_code(422);
            require dirname(__DIR__, 2) . '/Views/public/registration.php';
            return;
        }

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $security = require dirname(__DIR__, 3) . '/config/security.php';
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $networkAddress = is_string($remoteAddress) && filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false
            ? $remoteAddress
            : 'unknown-network';
        $networkHash = hash_hmac('sha256', 'registration-network:' . $networkAddress, $security['app_key']);

        if (!(new RegistrationRateLimitRepository($database))->allowNext($networkHash)) {
            http_response_code(429);
            $registrationValues = $result['values'];
            $errors = ['form' => 'Too many applications were submitted from this network. Wait an hour or contact the academy for help.'];
            require dirname(__DIR__, 2) . '/Views/public/registration.php';
            return;
        }

        $repository = new RegistrationRepository($database);
        $duplicateLock = $repository->acquireDuplicateCheckLock($registrationValues, $security['app_key']);
        if ($duplicateLock === null) {
            http_response_code(503);
            $registrationValues = $result['values'];
            $errors = ['form' => 'We could not check for a previous application right now. Please wait a few minutes and try again.'];
            require dirname(__DIR__, 2) . '/Views/public/registration.php';
            return;
        }

        try {
            $database->beginTransaction();
            if ($repository->hasDuplicateApplicant($registrationValues)) {
                // Return the same confirmation text for new and repeated submissions.
                // This prevents the public form from revealing whether a person is already in the system.
                $database->commit();
            } else {
                $registrationId = $repository->create($registrationValues);
                (new AuditLogRepository($database))->record(
                    null,
                    'registration.submitted',
                    (string) $registrationId,
                    'registration'
                );
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        } finally {
            // Advisory locks are connection-scoped, so always release ours after commit/rollback.
            $repository->releaseDuplicateCheckLock($duplicateLock);
        }
        Flash::add('success', 'Your request was processed. If this is a new application, academy staff have received it for review. If you already submitted the same applicant details, staff will continue with that application.');
        header('Location: ' . Url::path('/registration/received'), true, 303);
        exit;
    }

    public function received(): void
    {
        $message = Flash::pull();
        if (!is_array($message) || ($message['kind'] ?? '') !== 'success') {
            header('Location: ' . Url::path('/registration'), true, 303);
            exit;
        }

        require dirname(__DIR__, 2) . '/Views/public/registration-received.php';
    }
}
