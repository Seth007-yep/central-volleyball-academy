<?php

declare(strict_types=1);

// Pure validation smoke checks: no database connection or applicant data is used.
// Run with C:\xampp\php\php.exe tests\registration-validation-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Support\RegistrationValidator;

/** Stop immediately with the name of a failed check, without printing input data. */
function expectRegistrationCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

$adult = [
    'first_name' => 'Sample',
    'last_name' => 'Applicant',
    'date_of_birth' => '1980-01-01',
    'email' => 'sample@example.invalid',
    'phone' => '',
    'program_interest' => 'Volleyball training',
    'previous_experience' => '',
    'guardian_name' => 'Should be removed',
    'guardian_relationship' => 'Parent',
    'guardian_email' => 'parent@example.invalid',
    'guardian_phone' => '',
    'contact_consent' => '1',
];
$adultResult = RegistrationValidator::validate($adult);
expectRegistrationCheck($adultResult['errors'] === [], 'valid adult application');
expectRegistrationCheck($adultResult['values']['guardian_name'] === null, 'adult guardian details are not retained');

$minor = $adult;
$minor['date_of_birth'] = '2020-01-01';
$minor['email'] = '';
$minor['guardian_name'] = 'Sample Guardian';
$minor['guardian_relationship'] = 'Parent';
$minor['guardian_email'] = 'guardian@example.invalid';
$minorResult = RegistrationValidator::validate($minor);
expectRegistrationCheck($minorResult['errors'] === [], 'valid minor application with guardian contact');

$missingGuardian = $minor;
$missingGuardian['guardian_name'] = '';
$missingGuardian['guardian_relationship'] = '';
$missingGuardian['guardian_email'] = '';
$missingGuardianResult = RegistrationValidator::validate($missingGuardian);
expectRegistrationCheck(isset($missingGuardianResult['errors']['guardian_name']), 'minor requires guardian identity');
expectRegistrationCheck(isset($missingGuardianResult['errors']['guardian_email']), 'minor requires guardian contact method');

$futureDate = $adult;
$futureDate['date_of_birth'] = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$futureDateResult = RegistrationValidator::validate($futureDate);
expectRegistrationCheck(isset($futureDateResult['errors']['date_of_birth']), 'future date of birth is rejected');

$arrayInput = $adult;
$arrayInput['first_name'] = ['unexpected', 'array'];
$arrayInputResult = RegistrationValidator::validate($arrayInput);
expectRegistrationCheck(isset($arrayInputResult['errors']['first_name']), 'array input is rejected as plain text');

fwrite(STDOUT, "Registration validation smoke checks passed (5 checks).\n");
