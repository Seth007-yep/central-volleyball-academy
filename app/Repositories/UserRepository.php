<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Database access for staff identities and their role membership. */
final class UserRepository
{
    public function __construct(private PDO $database)
    {
    }

    /** Return sign-in fields while keeping account and role checks separate. */
    public function findForAuthentication(string $normalizedEmail): ?array
    {
        $statement = $this->database->prepare(
            'SELECT u.id, u.display_name, u.password_hash, u.status,
                    EXISTS (
                        SELECT 1
                        FROM user_roles ur
                        INNER JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id AND r.slug = :admin_role
                    ) AS is_admin,
                    EXISTS (
                        SELECT 1
                        FROM user_roles ur
                        INNER JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id AND r.slug IN (\'admin\', \'staff\')
                    ) AS can_sign_in
             FROM users u
             WHERE u.email = :email
             LIMIT 1'
        );
        $statement->execute(['admin_role' => 'admin', 'email' => $normalizedEmail]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function isActiveAdministrator(int $userId): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.id = :user_id
               AND u.status = :status
               AND r.slug = :role_slug
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'status' => 'active',
            'role_slug' => 'admin',
        ]);

        return $statement->fetchColumn() !== false;
    }

    /** Active staff may use academy workflows; account management remains admin-only. */
    public function isActiveStaffMember(int $userId): bool
    {
        $statement = $this->database->prepare(
            "SELECT 1
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.id = :user_id
               AND u.status = 'active'
               AND r.slug IN ('admin', 'staff')
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    /** Return the two roles currently supported by the admin account editor. */
    public function manageableRoles(): array
    {
        return $this->database->query(
            "SELECT id, slug, display_name FROM roles
             WHERE slug IN ('admin', 'staff')
             ORDER BY CASE slug WHEN 'admin' THEN 0 ELSE 1 END"
        )->fetchAll();
    }

    /** Lock this stable row to serialize changes that could remove the last admin. */
    public function lockAdministratorRole(): void
    {
        $statement = $this->database->query("SELECT id FROM roles WHERE slug = 'admin' FOR UPDATE");
        if ($statement->fetchColumn() === false) {
            throw new \RuntimeException('The administrator role is not configured.');
        }
    }

    public function findForAdmin(int $userId, bool $lock = false): ?array
    {
        // Lock the account row itself, then read its role without aggregate-lock ambiguity.
        $statement = $this->database->prepare(
            'SELECT id, email, display_name, status FROM users WHERE id = :user_id'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['user_id' => $userId]);
        $account = $statement->fetch();

        if (!is_array($account)) {
            return null;
        }

        $role = $this->database->prepare(
            'SELECT r.slug FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.slug DESC LIMIT 1'
        );
        $role->execute(['user_id' => $userId]);
        $account['role_slug'] = $role->fetchColumn() ?: null;

        return $account;
    }

    public function activeAdministratorCount(): int
    {
        return (int) $this->database->query(
            "SELECT COUNT(*)
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.status = 'active' AND r.slug = 'admin'"
        )->fetchColumn();
    }

    /** Create one active or inactive staff account and assign its initial role. */
    public function createAccount(array $account): int
    {
        $roleId = $this->roleId((string) $account['role_slug']);
        $insert = $this->database->prepare(
            'INSERT INTO users (email, display_name, password_hash, status)
             VALUES (:email, :display_name, :password_hash, :status)'
        );
        $insert->execute([
            'email' => $account['email'],
            'display_name' => $account['display_name'],
            'password_hash' => $account['password_hash'],
            'status' => $account['status'],
        ]);
        $userId = (int) $this->database->lastInsertId();

        $assign = $this->database->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        $assign->execute(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    /** Update identity, role, account status, and an optional password reset. */
    public function updateAccount(int $userId, array $account): void
    {
        $roleId = $this->roleId((string) $account['role_slug']);
        $update = $this->database->prepare(
            'UPDATE users SET email = :email, display_name = :display_name, status = :status
             WHERE id = :user_id'
        );
        $update->execute([
            'email' => $account['email'],
            'display_name' => $account['display_name'],
            'status' => $account['status'],
            'user_id' => $userId,
        ]);

        // The current admin UI assigns one role per account; normalize any old multi-role rows.
        $removeRoles = $this->database->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $removeRoles->execute(['user_id' => $userId]);
        $assign = $this->database->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        $assign->execute(['user_id' => $userId, 'role_id' => $roleId]);

        if (isset($account['password_hash'])) {
            $password = $this->database->prepare(
                'UPDATE users SET password_hash = :password_hash WHERE id = :user_id'
            );
            $password->execute(['password_hash' => $account['password_hash'], 'user_id' => $userId]);
        }
    }

    private function roleId(string $roleSlug): int
    {
        if (!in_array($roleSlug, ['admin', 'staff'], true)) {
            throw new \InvalidArgumentException('Unsupported staff role.');
        }

        $statement = $this->database->prepare('SELECT id FROM roles WHERE slug = :slug');
        $statement->execute(['slug' => $roleSlug]);
        $roleId = $statement->fetchColumn();
        if ($roleId === false) {
            throw new \RuntimeException('The requested staff role is not configured.');
        }

        return (int) $roleId;
    }

    public function countActive(): int
    {
        return (int) $this->database
            ->query("SELECT COUNT(*) FROM users WHERE status = 'active'")
            ->fetchColumn();
    }

    /** Count staff accounts using only the filters selected by the admin. */
    public function countForAdminList(?string $status, string $search): int
    {
        [$where, $parameters] = $this->adminListFilter($status, $search);
        $statement = $this->database->prepare('SELECT COUNT(*) FROM users u ' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** Return one database-paginated slice of staff accounts for the admin UI. */
    public function pageForAdminList(
        ?string $status,
        string $search,
        int $limit,
        int $offset
    ): array {
        [$where, $parameters] = $this->adminListFilter($status, $search);
        $statement = $this->database->prepare(
            'SELECT u.id, u.email, u.display_name, u.status, u.last_login_at, u.created_at,
                    GROUP_CONCAT(r.display_name ORDER BY r.display_name SEPARATOR ", ") AS role_names,
                    MAX(r.slug) AS role_slug
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             ' . $where . '
             GROUP BY u.id, u.email, u.display_name, u.status, u.last_login_at, u.created_at
             ORDER BY u.display_name ASC, u.id ASC
             LIMIT :row_limit OFFSET :row_offset'
        );

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':row_limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $statement->bindValue(':row_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function recordSuccessfulLogin(int $userId): void
    {
        $statement = $this->database->prepare(
            'UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
    }

    /** Build a fixed SQL filter; user values are always bound separately. */
    private function adminListFilter(?string $status, string $search): array
    {
        $conditions = [];
        $parameters = [];

        if ($status === 'active' || $status === 'inactive') {
            $conditions[] = 'u.status = :status';
            $parameters[':status'] = $status;
        }

        if ($search !== '') {
            $conditions[] = '(LOCATE(:name_query, u.display_name) > 0
                              OR LOCATE(:email_query, u.email) > 0)';
            $parameters[':name_query'] = $search;
            $parameters[':email_query'] = $search;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $parameters];
    }
}
