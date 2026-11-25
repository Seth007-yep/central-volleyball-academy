<?php

declare(strict_types=1);

namespace Cva\Support;

use DateTimeImmutable;

/** Validate the small public registration form before any database write. */
final class RegistrationValidator
{
    /** @return array{values:array, errors:array<string,string>} */
    public static function validate(array $input): array
    {
        $errors = [];
        $limits = [
            'first_name' => 80,
            'last_name' => 80,
            'date_of_birth' => 10,
            'email' => 254,
            'phone' => 40,
            'program_interest' => 120,
            'previous_experience' => 2000,
            'guardian_name' => 160,
            'guardian_relationship' => 60,
            'guardian_email' => 254,
            'guardian_phone' => 40,
        ];
        $values = [];
        foreach ($limits as $field => $maximumBytes) {
            $raw = $input[$field] ?? '';
            if (!is_string($raw)) {
                $values[$field] = '';
                $errors[$field] = 'Enter this value as plain text.';
            } elseif (strlen($raw) > $maximumBytes) {
                $values[$field] = '';
                $errors[$field] = 'This value is too long.';
            } else {
                $values[$field] = trim($raw);
            }
        }

        foreach (['first_name' => 'Enter the applicant\'s first name.', 'last_name' => 'Enter the applicant\'s last name.'] as $field => $message) {
            if ($values[$field] === '' || !self::validText($values[$field]) || preg_match('/[\r\n\t]/', $values[$field]) === 1) {
                $errors[$field] = $message;
            }
        }
        if ($values['program_interest'] === '' || !self::validText($values['program_interest']) || preg_match('/[\r\n\t]/', $values['program_interest']) === 1) {
            $errors['program_interest'] = 'Enter the program or training the applicant is interested in.';
        }
        foreach (['previous_experience', 'guardian_name', 'guardian_relationship'] as $field) {
            if (!self::validText($values[$field])) {
                $errors[$field] = 'Use plain text without control characters.';
            }
        }

        $birthDate = self::parseDate($values['date_of_birth']);
        if ($birthDate === null || $birthDate > new DateTimeImmutable('today')) {
            $errors['date_of_birth'] = 'Enter a valid date of birth that is not in the future.';
        }

        foreach (['email', 'guardian_email'] as $field) {
            if ($values[$field] !== '' && filter_var($values[$field], FILTER_VALIDATE_EMAIL) === false) {
                $errors[$field] = 'Enter a valid email address or leave this field blank.';
            }
        }
        foreach (['phone', 'guardian_phone'] as $field) {
            if ($values[$field] !== '' && !preg_match('/\A[0-9+(). \t\-]{5,40}\z/', $values[$field])) {
                $errors[$field] = 'Enter a phone number using digits and common phone punctuation.';
            }
        }

        $adultAge = self::adultAgeThreshold();
        if ($birthDate !== null && $birthDate <= new DateTimeImmutable('today')) {
            $age = $birthDate->diff(new DateTimeImmutable('today'))->y;
            if ($age < $adultAge) {
                if ($values['guardian_name'] === '' || $values['guardian_relationship'] === '') {
                    $errors['guardian_name'] = 'A parent or guardian name and relationship are required for applicants under ' . $adultAge . '.';
                }
                if ($values['guardian_email'] === '' && $values['guardian_phone'] === '') {
                    $errors['guardian_email'] = 'Provide a parent or guardian email address or phone number.';
                }
            } else {
                if ($values['email'] === '' && $values['phone'] === '') {
                    $errors['email'] = 'Provide an email address or phone number so the academy can contact the applicant.';
                }
                // Do not retain guardian details for an adult applicant.
                $values['guardian_name'] = '';
                $values['guardian_relationship'] = '';
                $values['guardian_email'] = '';
                $values['guardian_phone'] = '';
            }
        }

        if (($input['contact_consent'] ?? null) !== '1') {
            $errors['contact_consent'] = 'Confirm that you are authorized to submit this information and be contacted about the application.';
        }

        // Database NULL values avoid storing empty strings as real contact data.
        foreach (['email', 'phone', 'previous_experience', 'guardian_name', 'guardian_relationship', 'guardian_email', 'guardian_phone'] as $field) {
            if ($values[$field] === '') {
                $values[$field] = null;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    private static function validText(string $value): bool
    {
        return preg_match('//u', $value) === 1
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) !== 1;
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $issues = DateTimeImmutable::getLastErrors();

        if (!$date instanceof DateTimeImmutable || (is_array($issues) && ($issues['warning_count'] > 0 || $issues['error_count'] > 0))) {
            return null;
        }

        return $date;
    }

    /** The academy must confirm its age-of-majority rule before real intake. */
    public static function adultAgeThreshold(): int
    {
        $configured = getenv('REGISTRATION_ADULT_AGE');
        $age = $configured === false || $configured === ''
            ? 18
            : filter_var($configured, FILTER_VALIDATE_INT);

        return is_int($age) && $age >= 16 && $age <= 25 ? $age : 18;
    }
}
