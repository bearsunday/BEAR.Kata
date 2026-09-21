<?php

declare(strict_types=1);

namespace BEAR\Kata\Auth;

use BEAR\Csrf\CsrfTokenInterface;

use function bin2hex;
use function hash_equals;
use function is_array;
use function is_int;
use function is_string;
use function random_bytes;
use function session_start;
use function session_status;

use const PHP_SESSION_ACTIVE;

/** @SuppressWarnings("PHPMD.Superglobals") Session adapter boundary. */
final class NativeAuthSession implements AuthSessionInterface
{
    private const string USER = 'cms_user';
    private const string STATE = 'cms_oauth_state';

    public function __construct(private readonly CsrfTokenInterface $csrf)
    {
    }

    public function currentUser(): UserInterface
    {
        $this->start();
        $user = $_SESSION[self::USER] ?? null;
        if (! is_array($user)) {
            return new Visitor();
        }

        if (! $this->hasUserShape($user)) {
            return new Visitor();
        }

        return new AdminUser(
            id: $user['id'],
            email: $user['email'],
            name: $user['name'],
            authorId: $user['authorId'],
        );
    }

    public function issueState(): string
    {
        $this->start();
        $state = bin2hex(random_bytes(16));
        $_SESSION[self::STATE] = $state;

        return $state;
    }

    public function consumeState(string $state): bool
    {
        $this->start();
        $expected = $_SESSION[self::STATE] ?? null;
        unset($_SESSION[self::STATE]);

        return is_string($expected) && hash_equals($expected, $state);
    }

    public function login(AuthenticatedUser $user, int $authorId): void
    {
        $this->start();
        $_SESSION[self::USER] = [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'authorId' => $authorId,
        ];
    }

    public function logout(): void
    {
        $this->start();
        unset($_SESSION[self::USER], $_SESSION[self::STATE]);
        $this->csrf->clear();
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
    }

    /** @param array<array-key, mixed> $user */
    private function hasUserShape(array $user): bool
    {
        return isset($user['id'], $user['email'], $user['name'], $user['authorId'])
            && is_string($user['id'])
            && is_string($user['email'])
            && is_string($user['name'])
            && is_int($user['authorId']);
    }
}
