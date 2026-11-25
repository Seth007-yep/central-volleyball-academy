# Backup and Recovery Preparation

## Status

No production database or production backup target exists. The XAMPP database is a development database and is not a production backup. Decide the backup schedule, retention period, off-site storage, recovery objectives, and responsible person with the academy before collecting real data.

## What needs protection

- The MySQL/MariaDB database, including migration history and audit records.
- The reviewed application source release and its matching deployment notes.
- Production configuration and secrets, protected separately from source code and database dumps.
- Any future uploaded media, once an upload feature and storage location are approved.

The current application has no uploaded media, scheduled jobs, or production personal records. Do not create a backup process that places unencrypted database dumps in the web root, repository, or the same server as the only copy.

## Decisions to record

- Backup frequency and retention duration.
- Recovery point objective (maximum acceptable data loss) and recovery time objective (maximum acceptable outage).
- Encrypted off-site destination, access controls, and key owner.
- Who receives backup-failure alerts and who is authorized to restore.
- How often restoration is tested and where the isolated test database will live.

## Recovery procedure to finalize for the selected host

1. Declare the incident, assign a recovery owner, and record the last known good backup timestamp.
2. Provision or isolate a replacement host and database on supported versions.
3. Restore the backup to an isolated database first; verify schema migrations and required table relationships before any production cutover.
4. Deploy the matching reviewed source release and reapply private production configuration through the host's secret manager.
5. Rotate database and application credentials if compromise is possible. Revoke credentials from the lost or compromised environment.
6. Run the staging/release checklist, then switch traffic only after the recovery owner approves.
7. Document data loss, restore duration, and follow-up actions; confirm scheduled backups resume and the next restore test is booked.

The exact backup and restore commands depend on the selected database service, encryption method, hosting platform, and retention policy. Add and verify those commands after the provider is chosen; do not treat an untested dump as a proven backup.
