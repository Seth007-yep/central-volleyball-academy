param(
    [string] $MysqlPath = 'C:\xampp\mysql\bin\mysql.exe'
)

$mysqlCommand = Get-Command -Name $MysqlPath -ErrorAction Stop
$preflight = & $mysqlCommand.Source --protocol=TCP --host=127.0.0.1 --user=root --batch --skip-column-names --database=central_volleyball_academy --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'central_volleyball_academy' AND table_name IN ('roles', 'users', 'user_roles', 'audit_logs', 'login_attempts', 'academy_content', 'registrations', 'members', 'gallery_items', 'registration_rate_limits', 'coaches', 'programs', 'program_schedules', 'program_coaches', 'events')"

if ($LASTEXITCODE -ne 0 -or [int]$preflight -ne 15) {
    throw 'Apply the staff, login-attempt, academy-feature, coach, program, and event migrations before granting application permissions.'
}

# Keep web requests away from DDL, migration history, and unrelated tables.
$sql = @"
GRANT SELECT ON central_volleyball_academy.roles
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.users
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON central_volleyball_academy.user_roles
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT ON central_volleyball_academy.audit_logs
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON central_volleyball_academy.login_attempts
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.academy_content
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.registrations
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.members
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.gallery_items
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.coaches
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.programs
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON central_volleyball_academy.program_schedules
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON central_volleyball_academy.program_coaches
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON central_volleyball_academy.events
    TO 'cva_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON central_volleyball_academy.registration_rate_limits
    TO 'cva_app'@'127.0.0.1';
FLUSH PRIVILEGES;
"@

& $mysqlCommand.Source --protocol=TCP --host=127.0.0.1 --user=root --execute=$sql
if ($LASTEXITCODE -ne 0) {
    throw 'Could not grant the restricted application permissions.'
}

Write-Output 'The application account now has only the table permissions needed by the current staff and academy features.'
