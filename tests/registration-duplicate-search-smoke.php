<?php

declare(strict_types=1);

// Use synthetic records in a transaction, verify duplicate/search behavior, then roll back.
// Run with: C:\xampp\php\php.exe tests\registration-duplicate-search-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Repositories\MemberRepository;
use Cva\Repositories\RegistrationRepository;
use Cva\Support\Database;

function expectRegistrationFeatureCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

$database = null;
$repository = null;
$duplicateLock = null;

try {
    $settings = require $projectRoot . '/config/database.php';
    $database = Database::connect($settings);
    $repository = new RegistrationRepository($database);
    $memberRepository = new MemberRepository($database);
    $marker = bin2hex(random_bytes(10));
    $application = [
        'first_name' => 'Smoke' . $marker,
        'last_name' => 'Applicant',
        'date_of_birth' => '2000-01-15',
        'email' => 'smoke-' . $marker . '@example.test',
        'phone' => '+1 (202) 555-0179',
        'program_interest' => 'Smoke Volleyball ' . $marker,
        'previous_experience' => null,
        'guardian_name' => null,
        'guardian_relationship' => null,
        'guardian_email' => null,
        'guardian_phone' => null,
    ];

    $security = require $projectRoot . '/config/security.php';
    $duplicateLock = $repository->acquireDuplicateCheckLock($application, $security['app_key']);
    expectRegistrationFeatureCheck(is_string($duplicateLock), 'duplicate identity lock acquired');
    $database->beginTransaction();

    expectRegistrationFeatureCheck(!$repository->hasDuplicateApplicant($application), 'new applicant is not treated as a duplicate');
    $registrationId = $repository->create($application);
    expectRegistrationFeatureCheck($repository->hasDuplicateApplicant($application), 'repeat submission is detected');

    $formattedPhoneRepeat = $application;
    $formattedPhoneRepeat['first_name'] = strtolower($formattedPhoneRepeat['first_name']);
    $formattedPhoneRepeat['email'] = 'changed-' . $marker . '@example.test';
    $formattedPhoneRepeat['phone'] = '1-202-555-0179';
    expectRegistrationFeatureCheck($repository->hasDuplicateApplicant($formattedPhoneRepeat), 'name case and phone punctuation are normalized');

    $differentApplicant = $application;
    $differentApplicant['first_name'] = 'Sibling ' . $marker;
    expectRegistrationFeatureCheck(!$repository->hasDuplicateApplicant($differentApplicant), 'shared household contact alone does not block another applicant');

    $currentDay = (string) $database->query('SELECT DATE(CURRENT_TIMESTAMP)')->fetchColumn();
    expectRegistrationFeatureCheck(
        $repository->countForAdminList('pending', '', '12025550179', $marker, $currentDay, $currentDay) >= 1,
        'registration phone, program, and received-date filters combine in SQL'
    );
    $injectionLikeSearch = "' OR 1=1 -- " . $marker;
    expectRegistrationFeatureCheck(
        $repository->countForAdminList(null, $injectionLikeSearch) === 0,
        'SQL-shaped registration search input remains a bound literal'
    );
    expectRegistrationFeatureCheck(
        count($repository->pageForAdminList('pending', '', 10, 0, '12025550179', $marker, $currentDay, $currentDay)) >= 1,
        'registration result pages use the same active filters'
    );

    $insertMember = $database->prepare(
        'INSERT INTO members (
            source_registration_id, first_name, last_name, date_of_birth, email, phone,
            program_interest, previous_experience, guardian_name, guardian_relationship,
            guardian_email, guardian_phone
         ) VALUES (
            :source_registration_id, :first_name, :last_name, :date_of_birth, :email, :phone,
            :program_interest, :previous_experience, :guardian_name, :guardian_relationship,
            :guardian_email, :guardian_phone
         )'
    );
    $insertMember->execute(array_merge($application, ['source_registration_id' => $registrationId]));

    expectRegistrationFeatureCheck(
        $memberRepository->countForAdminList(null, '', '12025550179', $marker, '2000-01-15', '2000-01-15') >= 1,
        'player phone, program, and birth-date filters combine in SQL'
    );
    expectRegistrationFeatureCheck(
        $memberRepository->countForAdminList(null, $injectionLikeSearch) === 0,
        'SQL-shaped player search input remains a bound literal'
    );
    expectRegistrationFeatureCheck(
        $memberRepository->approvedCount() >= 1,
        'approved-member count uses approved-application provenance'
    );

    // Even if a staff member later changes an application status, its member record blocks re-registration.
    $database->exec("UPDATE registrations SET status = 'cancelled' WHERE id = " . (int) $registrationId);
    expectRegistrationFeatureCheck($repository->hasDuplicateApplicant($application), 'existing member blocks a repeat after application status changes');

    $database->rollBack();
    fwrite(STDOUT, "Registration duplicate, filter, and approved-member checks passed. Synthetic rows were rolled back.\n");
} catch (Throwable $error) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    fwrite(STDERR, 'Registration feature check failed (' . get_class($error) . '): ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    if ($repository instanceof RegistrationRepository && is_string($duplicateLock)) {
        $repository->releaseDuplicateCheckLock($duplicateLock);
    }
}
