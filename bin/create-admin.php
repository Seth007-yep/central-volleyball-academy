<?php

declare(strict_types=1);

// First-admin creation is a one-time CLI action, never a public web route.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Support\Database;

$password = getenv('CVA_ADMIN_PASSWORD');

if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Use the PowerShell setup script to provide a password between 12 and 72 bytes.\n");
    exit(1);
}

$readLine = static function (string $prompt): string {
    fwrite(STDOUT, $prompt);
    $value = fgets(STDIN);
    return is_string($value) ? trim($value) : '';
};

$displayName = $readLine('Administrator display name: ');
$email = strtolower($readLine('Administrator email: '));

if ($displayName === '' || strlen($displayName) > 160) {
    fwrite(STDERR, "The display name must be between 1 and 160 bytes.\n");
    exit(1);
}

if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Enter a valid administrator email address.\n");
    exit(1);
}

$settings = require $projectRoot . '/config/database.php';
$database = Database::connect($settings);
$existingAdministrator = $database->query(
    "SELECT 1
     FROM users u
     INNER JOIN user_roles ur ON ur.user_id = u.id
     INNER JOIN roles r ON r.id = ur.role_id
     WHERE r.slug = 'admin'
     LIMIT 1"
)->fetchColumn();

if ($existingAdministrator !== false) {
    fwrite(STDERR, "An administrator already exists. Use the protected admin workflow to add staff later.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$database->beginTransaction();

try {
    $insertUser = $database->prepare(
        'INSERT INTO users (email, display_name, password_hash, status)
         VALUES (:email, :display_name, :password_hash, :status)'
    );
    $insertUser->execute([
        'email' => $email,
        'display_name' => $displayName,
        'password_hash' => $passwordHash,
        'status' => 'active',
    ]);
    $userId = (int) $database->lastInsertId();

    $assignRole = $database->prepare(
        "INSERT INTO user_roles (user_id, role_id)
         SELECT :user_id, id FROM roles WHERE slug = 'admin'"
    );
    $assignRole->execute(['user_id' => $userId]);

    $audit = $database->prepare(
        "INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id)
         VALUES (NULL, 'setup.admin.created', 'user', :entity_id)"
    );
    $audit->execute(['entity_id' => (string) $userId]);

    $database->commit();
    fwrite(STDOUT, "Initial administrator created. Sign in at /admin/login.\n");
} catch (Throwable $error) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }

    fwrite(STDERR, 'Administrator setup failed (' . get_class($error) . '). No password was stored in plain text.' . "\n");
    exit(1);
} finally {
    // Reduce the lifetime of the password in this CLI process.
    $password = '';
    putenv('CVA_ADMIN_PASSWORD');
}
