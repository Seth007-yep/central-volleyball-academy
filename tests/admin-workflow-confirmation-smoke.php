<?php

declare(strict_types=1);

// Uses synthetic accounts and an application inside one transaction, then rolls them back.
// Run with: C:\xampp\php\php.exe tests\admin-workflow-confirmation-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Repositories\RegistrationRepository;
use Cva\Repositories\UserRepository;
use Cva\Support\Database;

function expectAdminWorkflowCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

try {
    $settings = require $projectRoot . '/config/database.php';
    $database = Database::connect($settings);
    $users = new UserRepository($database);
    $registrations = new RegistrationRepository($database);
    $marker = bin2hex(random_bytes(10));
    $database->beginTransaction();

    try {
        // Confirm the Academy Staff role is usable without granting administrator access.
        $staffId = $users->createAccount([
            'email' => 'smoke-' . $marker . '@example.invalid',
            'display_name' => 'Synthetic Staff ' . $marker,
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'status' => 'active',
            'role_slug' => 'staff',
        ]);
        $staffLogin = $users->findForAuthentication('smoke-' . $marker . '@example.invalid');
        expectAdminWorkflowCheck(
            is_array($staffLogin)
                && (int) $staffLogin['can_sign_in'] === 1
                && (int) $staffLogin['is_admin'] === 0,
            'staff role can authenticate without administrator privileges'
        );
        expectAdminWorkflowCheck(
            $users->isActiveStaffMember($staffId) && !$users->isActiveAdministrator($staffId),
            'staff role is authorized as staff only'
        );

        $users->updateAccount($staffId, [
            'email' => 'smoke-' . $marker . '@example.invalid',
            'display_name' => 'Synthetic Staff ' . $marker,
            'status' => 'active',
            'role_slug' => 'admin',
        ]);
        expectAdminWorkflowCheck($users->isActiveAdministrator($staffId), 'role assignment can promote a staff account');
        $users->updateAccount($staffId, [
            'email' => 'smoke-' . $marker . '@example.invalid',
            'display_name' => 'Synthetic Staff ' . $marker,
            'status' => 'inactive',
            'role_slug' => 'staff',
        ]);
        expectAdminWorkflowCheck(!$users->isActiveStaffMember($staffId), 'inactive account loses protected-route access');

        // Stage a synthetic application to verify the status guard and approval transaction.
        $registrationId = $registrations->create([
            'first_name' => 'Smoke' . $marker,
            'last_name' => 'Applicant',
            'date_of_birth' => '2000-01-01',
            'email' => 'applicant-' . $marker . '@example.invalid',
            'phone' => null,
            'program_interest' => 'Synthetic program for smoke test',
            'previous_experience' => null,
            'guardian_name' => null,
            'guardian_relationship' => null,
            'guardian_email' => null,
            'guardian_phone' => null,
        ]);
        $staleDecisionRejected = false;
        try {
            $registrations->review($registrationId, 'approved', null, null, 'rejected');
        } catch (RuntimeException) {
            $staleDecisionRejected = true;
        }
        expectAdminWorkflowCheck($staleDecisionRejected, 'confirmation rejects a stale starting status');
        $stillPending = $registrations->findForAdmin($registrationId);
        expectAdminWorkflowCheck(
            is_array($stillPending) && $stillPending['status'] === 'pending',
            'stale confirmation leaves the application unchanged'
        );

        $registrations->review($registrationId, 'approved', 'Synthetic review note.', null, 'pending');
        $approved = $registrations->findForAdmin($registrationId);
        $member = $database->prepare('SELECT 1 FROM members WHERE source_registration_id = :id');
        $member->execute(['id' => $registrationId]);
        expectAdminWorkflowCheck(
            is_array($approved) && $approved['status'] === 'approved' && $member->fetchColumn() !== false,
            'confirmed approval and player creation occur together'
        );
    } finally {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
    }

    fwrite(STDOUT, "Staff roles and review confirmation checks passed; synthetic rows rolled back.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Admin workflow confirmation check failed (' . get_class($error) . ').' . "\n");
    exit(1);
}
