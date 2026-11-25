<?php

declare(strict_types=1);

namespace Cva\Services;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\LoginAttemptRepository;
use Cva\Repositories\UserRepository;
use PDO;

/** Coordinate credential checks, rate limits, account state, and audit events. */
final class AuthService
{
    private const MAX_EMAIL_BYTES = 254;
    // PASSWORD_DEFAULT currently uses bcrypt, which accepts at most 72 bytes.
    private const MAX_PASSWORD_BYTES = 72;

    private static ?string $dummyPasswordHash = null;

    private UserRepository $users;
    private LoginAttemptRepository $attempts;
    private AuditLogRepository $audit;

    public function __construct(PDO $database, private string $appKey)
    {
        $this->users = new UserRepository($database);
        $this->attempts = new LoginAttemptRepository($database);
        $this->audit = new AuditLogRepository($database);
    }

    /**
     * Authenticate an active administrator or academy staff member.
     * No raw password, email, or network address is written to audit storage.
     *
     * @return array{status:string,user:?array{id:int,display_name:string,is_admin:bool}}
     */
    public function authenticate(string $email, string $password, string $remoteAddress): array
    {
        $normalizedEmail = strtolower(trim($email));
        $emailIsValid = strlen($normalizedEmail) <= self::MAX_EMAIL_BYTES
            && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) !== false;
        $boundedEmail = substr($normalizedEmail, 0, self::MAX_EMAIL_BYTES);
        $boundedPassword = strlen($password) <= self::MAX_PASSWORD_BYTES ? $password : '';
        $buckets = $this->rateLimitBuckets($boundedEmail, $remoteAddress);

        $this->attempts->pruneExpired();

        if ($this->attempts->isLocked($buckets)) {
            return ['status' => 'rate_limited', 'user' => null];
        }

        $user = $emailIsValid
            ? $this->users->findForAuthentication($normalizedEmail)
            : null;

        // Use a real password hash check even when the email does not exist so
        // the response timing does not reveal which staff addresses are valid.
        $hashToCheck = is_array($user)
            && is_string($user['password_hash'] ?? null)
            ? $user['password_hash']
            : self::dummyHash();
        $passwordIsValid = password_verify($boundedPassword, $hashToCheck);
        $accountCanSignIn = is_array($user)
            && ($user['status'] ?? '') === 'active'
            && (int) ($user['can_sign_in'] ?? 0) === 1;

        if (!$emailIsValid || !$passwordIsValid || !$accountCanSignIn) {
            $this->attempts->recordFailure($buckets);
            $this->audit->record(null, 'admin.login.failed');

            return ['status' => 'failed', 'user' => null];
        }

        $userId = (int) $user['id'];
        $displayName = (string) $user['display_name'];
        $this->attempts->clear($buckets);
        $this->users->recordSuccessfulLogin($userId);
        $this->audit->record($userId, 'admin.login.success', (string) $userId);

        return [
            'status' => 'authenticated',
            'user' => [
                'id' => $userId,
                'display_name' => $displayName,
                'is_admin' => (int) ($user['is_admin'] ?? 0) === 1,
            ],
        ];
    }

    private function rateLimitBuckets(string $email, string $remoteAddress): array
    {
        $validAddress = filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false
            ? $remoteAddress
            : 'unknown';

        return [
            // HMAC prevents the rate-limit table from becoming a list of emails/IPs.
            'email' => hash_hmac('sha256', 'email:' . $email, $this->appKey),
            'network' => hash_hmac('sha256', 'network:' . $validAddress, $this->appKey),
        ];
    }

    private static function dummyHash(): string
    {
        if (self::$dummyPasswordHash === null) {
            self::$dummyPasswordHash = password_hash(
                bin2hex(random_bytes(32)),
                PASSWORD_DEFAULT
            );
        }

        return self::$dummyPasswordHash;
    }
}
