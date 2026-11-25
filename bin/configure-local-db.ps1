param(
    [string] $MysqlPath = 'C:\xampp\mysql\bin\mysql.exe'
)

$mysqlCommand = Get-Command -Name $MysqlPath -ErrorAction Stop
$projectRoot = Split-Path -Parent $PSScriptRoot
$localConfigPath = Join-Path $projectRoot 'config\local.php'

if (Test-Path -LiteralPath $localConfigPath) {
    throw 'config/local.php already exists; this setup script will not replace local credentials.'
}

$existingUsers = & $mysqlCommand.Source --protocol=TCP --host=127.0.0.1 --user=root --batch --skip-column-names --execute="SELECT COUNT(*) FROM mysql.user WHERE User IN ('cva_app', 'cva_migrator') AND Host = '127.0.0.1'"
if ($LASTEXITCODE -ne 0) {
    throw 'Could not check the local database accounts. Confirm that XAMPP MariaDB is running and root can connect.'
}
if ([int]$existingUsers -ne 0) {
    throw 'A project database account already exists; inspect it before creating new credentials.'
}

$randomGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()

function New-RandomHex([System.Security.Cryptography.RandomNumberGenerator] $Generator) {
    $randomBytes = New-Object byte[] 32
    $Generator.GetBytes($randomBytes)
    $hexValue = [BitConverter]::ToString($randomBytes).Replace('-', '').ToLowerInvariant()
    [Array]::Clear($randomBytes, 0, $randomBytes.Length)
    return $hexValue
}

$appDbPassword = New-RandomHex $randomGenerator
$migrationDbPassword = New-RandomHex $randomGenerator
$appKey = New-RandomHex $randomGenerator
$phpConfig = @"
<?php

declare(strict_types=1);

// This generated file is ignored by Git and must stay outside public/.
return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'central_volleyball_academy',
        'username' => 'cva_app',
        'password' => '$appDbPassword',
    ],
    'migration_database' => [
        'username' => 'cva_migrator',
        'password' => '$migrationDbPassword',
    ],
    'app_key' => '$appKey',
];
"@

$sql = @"
CREATE DATABASE IF NOT EXISTS central_volleyball_academy
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cva_app'@'127.0.0.1' IDENTIFIED BY '$appDbPassword';
CREATE USER 'cva_migrator'@'127.0.0.1' IDENTIFIED BY '$migrationDbPassword';
GRANT SELECT, INSERT, CREATE, ALTER, INDEX
    ON central_volleyball_academy.* TO 'cva_migrator'@'127.0.0.1';
FLUSH PRIVILEGES;
"@

& $mysqlCommand.Source --protocol=TCP --host=127.0.0.1 --user=root --execute=$sql
if ($LASTEXITCODE -ne 0) {
    throw 'XAMPP MariaDB could not create the local application accounts. Inspect the accounts before rerunning this script.'
}

[System.IO.File]::WriteAllText(
    $localConfigPath,
    $phpConfig,
    [System.Text.UTF8Encoding]::new($false)
)

Write-Output 'Local database accounts and ignored config/local.php are ready.'
$randomGenerator.Dispose()
$appDbPassword = $null
$migrationDbPassword = $null
$appKey = $null
$phpConfig = $null
$sql = $null
