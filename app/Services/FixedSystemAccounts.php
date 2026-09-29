<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

final class FixedSystemAccounts
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function accounts(): array
    {
        $configured = config('system_accounts.accounts', []);
        $normalized = [];

        if (!is_array($configured)) {
            return [];
        }

        foreach ($configured as $code => $account) {
            $normalized[$this->normalize((string) $code)] = is_array($account)
                ? $account
                : [];
        }

        return $normalized;
    }

    public function normalize(?string $userCode): string
    {
        return strtoupper(trim((string) $userCode));
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->accounts());
    }

    public function exists(?string $userCode): bool
    {
        return array_key_exists($this->normalize($userCode), $this->accounts());
    }

    public function accountMode(?string $userCode): string
    {
        $code = $this->normalize($userCode);
        $account = $this->accounts()[$code] ?? [];

        return strtoupper(trim((string) ($account['account_mode'] ?? 'SYSTEM_ADMIN')));
    }

    /**
     * Existing USERS record used only as Laravel's authenticated session carrier.
     */
    public function authUserCode(?string $userCode): string
    {
        $code = $this->normalize($userCode);
        $account = $this->accounts()[$code] ?? [];

        return $this->normalize((string) ($account['auth_user_code'] ?? 'NAYSA'));
    }

    /**
     * User code used by the application's role/menu lookup.
     * HEARTSTRONG resolves to itself, so it receives no normal Financials roles.
     * MIRACLE resolves to its configured full-access template account.
     */
    public function permissionUserCode(?string $userCode): string
    {
        $code = $this->normalize($userCode);
        $account = $this->accounts()[$code] ?? [];

        return $this->normalize((string) (
            $account['permission_user_code'] ?? $code
        ));
    }

    public function displayName(?string $userCode): string
    {
        $code = $this->normalize($userCode);
        $account = $this->accounts()[$code] ?? [];

        return trim((string) ($account['display_name'] ?? $code)) ?: $code;
    }

    /** @return array<string, mixed>|null */
    public function authenticate(?string $userCode, ?string $password): ?array
    {
        $code = $this->normalize($userCode);
        $account = $this->accounts()[$code] ?? null;

        if (!is_array($account)) {
            return null;
        }

        $hash = trim((string) ($account['password_hash'] ?? ''));

        if ($hash === '' || !Hash::check((string) $password, $hash)) {
            return null;
        }

        return [
            'USER_CODE' => $code,
            'USER_NAME' => $this->displayName($code),
            'EMAIL_ADD' => null,
            'AUTH_USER_CODE' => $this->authUserCode($code),
            'PERMISSION_USER_CODE' => $this->permissionUserCode($code),
            'ACCOUNT_MODE' => $this->accountMode($code),
            'SYSTEM_ACCOUNT' => true,
            'LICENSE_EXEMPT' => true,
        ];
    }

    public function resolvePermissionUserCode(?string $userCode): string
    {
        $code = $this->normalize($userCode);

        return $this->exists($code)
            ? $this->permissionUserCode($code)
            : $code;
    }

    public function findInPayload(mixed $value): ?string
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, ['usercode', 'user_code'], true)) {
                $code = $this->normalize(is_scalar($item) ? (string) $item : '');
                if ($this->exists($code)) {
                    return $code;
                }
            }

            if ($normalizedKey === 'users' && is_array($item)) {
                foreach ($item as $candidate) {
                    if (is_scalar($candidate)) {
                        $code = $this->normalize((string) $candidate);
                        if ($this->exists($code)) {
                            return $code;
                        }
                    }
                }
            }

            if (is_array($item) || is_object($item)) {
                $found = $this->findInPayload($item);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
