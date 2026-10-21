<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class LicenseSeatPolicy
{
    public function __construct(
        private readonly FixedSystemAccounts $fixedAccounts
    ) {
    }

    /** @return list<string> */
    public function exemptUserCodes(): array
    {
        return $this->fixedAccounts->codes();
    }

    public function isExempt(?string $userCode): bool
    {
        return $this->fixedAccounts->exists($userCode);
    }

    public function activeLicensedUsersQuery(): Builder
    {
        $query = DB::table('USERS')->where('LOGIN_STAT', 1);
        $codes = $this->exemptUserCodes();

        if ($codes !== []) {
            $query->whereNotIn(
                DB::raw('UPPER(LTRIM(RTRIM(USER_CODE)))'),
                $codes
            );
        }

        return $query;
    }

    public function activeLicensedUserCount(): int
    {
        return $this->activeLicensedUsersQuery()->count();
    }
}
