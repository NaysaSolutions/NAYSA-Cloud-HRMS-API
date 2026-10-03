<?php

namespace App\Http\Controllers;

use App\Mail\AdminApprovalMail;
use App\Mail\TempPasswordMail;
use App\Services\FixedSystemAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class UserManagementController extends Controller
{
    private function connection()
    {
        return DB::connection('tenant');
    }

    private function fixedAccounts(): FixedSystemAccounts
    {
        return app(FixedSystemAccounts::class);
    }

    private function isFixedAccount(?string $userCode): bool
    {
        return $this->fixedAccounts()->exists($userCode);
    }

    private function fixedAccountError(?string $userCode = null)
    {
        $code = $this->fixedAccounts()->normalize($userCode);

        return response()->json([
            'success' => false,
            'status'  => 'error',
            'message' => $code !== ''
                ? "{$code} is a fixed system account and cannot be managed through User Management."
                : 'Fixed system accounts cannot be managed through User Management.',
        ], 422);
    }

    private function actorCode(Request $request): string
    {
        return trim((string) (
            $request->input('doneBy')
            ?? Auth::user()?->USER_CODE
            ?? 'SYSTEM'
        ));
    }

    private function actorType(): string
    {
        return strtoupper(trim((string) (Auth::user()?->USER_TYPE ?? '')));
    }

    private function decodeResultRow(array $rows): mixed
    {
        $raw = $rows[0]->result ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    /**
     * Detect optional registration/audit columns without assuming they exist.
     * This keeps the controller compatible with USERS tables that do not yet
     * have these four columns.
     */
    private function userAuditColumns(): array
    {
        try {
            $columns = $this->connection()
                ->getSchemaBuilder()
                ->getColumnListing('users');

            $map = [];
            foreach ($columns as $column) {
                $map[strtolower($column)] = $column;
            }

            $pick = function (array $candidates) use ($map) {
                foreach ($candidates as $candidate) {
                    $key = strtolower($candidate);
                    if (isset($map[$key])) {
                        return $map[$key];
                    }
                }

                return null;
            };

            return [
                'registeredBy'   => $pick(['registered_by', 'reg_by']),
                'registeredDate' => $pick(['registered_date', 'reg_date']),
                'updatedBy'      => $pick(['updated_by', 'upd_by']),
                'updatedDate'    => $pick(['updated_date', 'upd_date']),
            ];
        } catch (Throwable $e) {
            Log::warning('Unable to inspect USERS registration columns: ' . $e->getMessage());

            return [
                'registeredBy'   => null,
                'registeredDate' => null,
                'updatedBy'      => null,
                'updatedDate'    => null,
            ];
        }
    }

    private function enrichRegistrationInfo(array $users): array
    {
        if (empty($users)) {
            return $users;
        }

        $auditColumns = $this->userAuditColumns();

        if (!array_filter($auditColumns)) {
            return $users;
        }

        $userCodes = collect($users)
            ->pluck('userCode')
            ->filter()
            ->unique()
            ->values();

        if ($userCodes->isEmpty()) {
            return $users;
        }

        $selects = ['user_code as userCode'];

        foreach ($auditColumns as $alias => $column) {
            if ($column) {
                $selects[] = "{$column} as {$alias}";
            }
        }

        $auditRows = $this->connection()
            ->table('users')
            ->select($selects)
            ->whereIn('user_code', $userCodes->all())
            ->get()
            ->map(fn ($row) => (array) $row)
            ->keyBy('userCode');

        return array_map(function (array $user) use ($auditRows) {
            $code = $user['userCode'] ?? null;

            if (!$code || !$auditRows->has($code)) {
                return $user;
            }

            return array_merge($user, $auditRows->get($code));
        }, $users);
    }

    private function touchRegistrationInfo(string $userCode, string $doneBy, bool $isNew): void
    {
        $auditColumns = $this->userAuditColumns();

        $updates = [];

        if ($isNew) {
            if ($auditColumns['registeredBy']) {
                $updates[$auditColumns['registeredBy']] = $doneBy;
            }

            if ($auditColumns['registeredDate']) {
                $updates[$auditColumns['registeredDate']] = now();
            }
        } else {
            if ($auditColumns['updatedBy']) {
                $updates[$auditColumns['updatedBy']] = $doneBy;
            }

            if ($auditColumns['updatedDate']) {
                $updates[$auditColumns['updatedDate']] = now();
            }
        }

        if (!empty($updates)) {
            $this->connection()
                ->table('users')
                ->where('user_code', $userCode)
                ->update($updates);
        }
    }

    /* ============================================================
     * LOAD
     * ============================================================
     */
    public function load(Request $request)
    {
        $status = ucfirst(strtolower(trim((string) $request->query('Status', 'Active'))));

        if (!in_array($status, ['Active', 'Pending', 'Inactive'], true)) {
            $status = 'Active';
        }

        try {
            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['Load', $status]
            );

            $decoded = $this->decodeResultRow($rows);

            if (is_array($decoded)) {
                $decoded = $this->enrichRegistrationInfo($decoded);

                if (isset($rows[0])) {
                    $rows[0]->result = json_encode(
                        $decoded,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $rows,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load users: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * GET
     * ============================================================
     */
    public function get(Request $request)
    {
        $userCode = trim((string) (
            $request->query('userCode')
            ?? $request->query('USER_CODE')
            ?? ''
        ));

        if ($userCode === '') {
            return response()->json([
                'success' => false,
                'message' => 'User ID is required.',
            ], 422);
        }

        if ($this->isFixedAccount($userCode)) {
            return $this->fixedAccountError($userCode);
        }

        try {
            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['Get', $userCode]
            );

            $decoded = $this->decodeResultRow($rows);

            if (is_array($decoded)) {
                $decoded = $this->enrichRegistrationInfo($decoded);

                if (isset($rows[0])) {
                    $rows[0]->result = json_encode(
                        $decoded,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $rows,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * UPSERT
     *
     * The HRMS UserManagement.jsx intentionally exposes fewer fields.
     * Preserve the hidden USERS values so editing from this screen does
     * not erase Branch, Department, Position, or Edit Unit Price.
     * ============================================================
     */
    public function upsert(Request $request)
    {
        $payload = $request->all();
        $jsonData = data_get($payload, 'json_data', []);

        if (!is_array($jsonData)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid json_data payload.',
            ], 422);
        }

        $userCode = trim((string) ($jsonData['userCode'] ?? ''));

        if ($userCode !== '' && $this->isFixedAccount($userCode)) {
            return $this->fixedAccountError($userCode);
        }

        $doneBy = trim((string) (
            $jsonData['doneBy']
            ?? $request->input('doneBy')
            ?? Auth::user()?->USER_CODE
            ?? 'SYSTEM'
        ));

        try {
            $existing = $userCode !== ''
                ? $this->connection()
                    ->table('users')
                    ->where('user_code', $userCode)
                    ->first()
                : null;

            $existingArray = $existing
                ? array_change_key_case((array) $existing, CASE_LOWER)
                : [];

            $isNew = $existing === null;

            // Preserve fields omitted from the simplified HRMS screen.
            $jsonData['branchCode'] = $jsonData['branchCode']
                ?? ($existingArray['branch_code'] ?? '');

            $jsonData['rcCode'] = $jsonData['rcCode']
                ?? ($existingArray['rc_code'] ?? '');

            $jsonData['editUprice'] = $jsonData['editUprice']
                ?? ($existingArray['edit_uprice'] ?? 'N');

            $jsonData['position'] = $jsonData['position']
                ?? ($existingArray['position'] ?? '');

            $jsonData['doneBy'] = $doneBy;

            $params = json_encode(
                ['json_data' => $jsonData],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $result = $rows[0] ?? null;
            $errorCount = (int) ($result->errorcount ?? 0);
            $errorMessage = $result->errormsg ?? '';

            if ($errorCount > 0) {
                return response()->json([
                    'success'    => false,
                    'message'    => $errorMessage,
                    'errorcount' => $errorCount,
                    'data'       => $rows,
                ], 422);
            }

            $this->touchRegistrationInfo($userCode, $doneBy, $isNew);

            return response()->json([
                'success' => true,
                'message' => $isNew
                    ? 'User created successfully.'
                    : 'User updated successfully.',
                'data' => $rows,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * CHECK DUPLICATE
     * ============================================================
     */
    public function checkDuplicate(Request $request)
    {
        $userCode = trim((string) (
            $request->input('json_data.userCode')
            ?? $request->input('userCode')
            ?? ''
        ));

        if ($userCode === '') {
            return response()->json([
                'success' => false,
                'message' => 'User ID is required.',
            ], 422);
        }

        if ($this->isFixedAccount($userCode)) {
            return response()->json([
                'success' => true,
                'data' => [[
                    'result'      => '{"result":"1"}',
                    'isDuplicate' => true,
                    'reserved'    => true,
                ]],
            ], 200);
        }

        try {
            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['CheckDuplicate', $userCode]
            );

            return response()->json([
                'success' => true,
                'data'    => $rows,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Duplicate check failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * DELETE / REJECT
     * ============================================================
     */
    public function delete(Request $request)
    {
        $request->validate([
            'userCode' => ['required', 'string'],
        ]);

        $userCode = trim((string) $request->input('userCode'));

        if ($this->isFixedAccount($userCode)) {
            return $this->fixedAccountError($userCode);
        }

        $doneBy  = $this->actorCode($request);
        $company = $request->header('X-Company-DB');

        try {
            $existing = $this->connection()
                ->table('users')
                ->where('user_code', $userCode)
                ->first();

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $existingArray = array_change_key_case((array) $existing, CASE_LOWER);
            $isPending = strtoupper((string) ($existingArray['active'] ?? '')) === 'P';
            $email = $existingArray['email_add'] ?? null;
            $name  = $existingArray['user_name'] ?? $userCode;

            $params = json_encode([
                'json_data' => [
                    'userCode' => $userCode,
                    'doneBy'   => $doneBy,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['Delete', $params]
            );

            $decoded = $this->decodeResultRow($rows);
            $message = is_array($decoded)
                ? ($decoded['message'] ?? 'User delete processed successfully.')
                : 'User delete processed successfully.';

            $deleteMode = is_array($decoded)
                ? ($decoded['deleteMode'] ?? null)
                : null;

            if ($isPending && !empty($email)) {
                try {
                    Mail::to($email)->send(
                        new AdminApprovalMail(
                            $name,
                            null,
                            null,
                            null,
                            $company,
                            true
                        )
                    );
                } catch (Throwable $mailError) {
                    Log::error(
                        "Failed to send rejection email to {$email}: "
                        . $mailError->getMessage()
                    );
                }
            }

            return response()->json([
                'success'    => true,
                'message'    => $message,
                'deleteMode' => $deleteMode,
                'data'       => $rows,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * APPROVE / ADMIN ADD
     *
     * admin_add = user created by admin; set temporary password.
     * release   = approve a pending/self-registered account.
     * ============================================================
     */
    public function approveAccount(Request $request)
    {
        $request->validate([
            'userCode' => ['required', 'string'],
            'mode'     => ['required', 'string', 'in:admin_add,release'],
        ]);

        $userCode = trim((string) $request->input('userCode'));

        if ($this->isFixedAccount($userCode)) {
            return $this->fixedAccountError($userCode);
        }

        $doneBy  = $this->actorCode($request);
        $mode    = $request->input('mode');
        $company = $request->header('X-Company-DB');

        try {
            $user = $this->connection()
                ->table('users')
                ->where('user_code', $userCode)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'User not found.',
                ], 404);
            }

            $data = array_change_key_case((array) $user, CASE_LOWER);
            $email = $data['email_add'] ?? null;
            $name  = $data['user_name'] ?? $userCode;

            if (empty($email)) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'User email is missing.',
                ], 422);
            }

            if ($mode === 'admin_add') {
                $tempPassword = substr(
                    str_shuffle(
                        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
                    ),
                    0,
                    8
                );

                $hash = Hash::make($tempPassword);

                $this->connection()->select(
                    'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                    [
                        'SetTempPassword',
                        json_encode([
                            'json_data' => [
                                'userCode'     => $userCode,
                                'passwordHash' => $hash,
                                'doneBy'       => $doneBy,
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]
                );

                Mail::to($email)->send(
                    new TempPasswordMail(
                        'admin_add',
                        $name,
                        $userCode,
                        $tempPassword,
                        $company
                    )
                );

                $this->touchRegistrationInfo($userCode, $doneBy, false);

                return response()->json([
                    'success' => true,
                    'status'  => 'success',
                    'message' => 'User activated. Temporary password sent.',
                ], 200);
            }

            // Approve pending/self-registered account.
            $params = json_encode([
                'json_data' => [
                    'userCode'      => $userCode,
                    'userName'      => $name,
                    'userType'      => $data['user_type'] ?? 'R',
                    'branchCode'    => $data['branch_code'] ?? '',
                    'rcCode'        => $data['rc_code'] ?? '',
                    'viewCostamt'   => $data['view_costamt'] ?? 'N',
                    'editUprice'    => $data['edit_uprice'] ?? 'N',
                    'emailAdd'      => $email,
                    'position'      => $data['position'] ?? '',
                    'active'        => 'Y',
                    'accountAction' => 'approve',
                    'doneBy'        => $doneBy,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $result = $rows[0] ?? null;

            if ((int) ($result->errorcount ?? 0) > 0) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => $result->errormsg ?? 'Approval failed.',
                ], 422);
            }

            Mail::to($email)->send(
                new TempPasswordMail(
                    'release',
                    $name,
                    $userCode,
                    null,
                    $company
                )
            );

            $this->touchRegistrationInfo($userCode, $doneBy, false);

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Account approved. Password setup link sent.',
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Approval failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * RESET PASSWORD EMAIL
     * ============================================================
     */
    public function requestPasswordReset(Request $request)
    {
        $request->validate([
            'userCode' => ['required', 'string'],
        ]);

        $userCode = trim((string) $request->input('userCode'));

        if ($this->isFixedAccount($userCode)) {
            return $this->fixedAccountError($userCode);
        }

        $company = $request->header('X-Company-DB');

        try {
            $user = $this->connection()
                ->table('users')
                ->where('user_code', $userCode)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'User not found.',
                ], 404);
            }

            $data = array_change_key_case((array) $user, CASE_LOWER);
            $email = $data['email_add'] ?? null;
            $name  = $data['user_name'] ?? $userCode;

            if (empty($email)) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'User email is missing.',
                ], 422);
            }

            Mail::to($email)->send(
                new TempPasswordMail(
                    'reset',
                    $name,
                    $userCode,
                    null,
                    $company
                )
            );

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Password reset link sent.',
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Reset failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * GET LOCK POLICY
     * ============================================================
     */
    public function getPolicy(Request $request)
    {
        // Only Security Administrators may view/configure the login/password policy.
        if ($this->actorType() !== 'X') {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Only a Security Administrator can access the Login / Password Policy.',
            ], 403);
        }

        try {
            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?',
                ['GetPolicy']
            );

            $decoded = $this->decodeResultRow($rows);

            if (!is_array($decoded)) {
                $decoded = ['maxLog' => 0];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    ...$decoded,
                    'maxLog' => (int) ($decoded['maxLog'] ?? 0),
                ],
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load login policy: ' . $e->getMessage(),
            ], 500);
        }
    }


    /* ============================================================
     * UPSERT LOGIN / PASSWORD POLICY
     * Security Administrator only
     * ============================================================
     */
    public function upsertPolicy(Request $request)
    {
        if ($this->actorType() !== 'X') {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Only a Security Administrator can update the Login / Password Policy.',
            ], 403);
        }

        $validated = $request->validate([
            'minimChar' => ['required', 'integer', 'min:0'],
            'passExp'   => ['required', 'integer', 'min:0'],
            'passHis'   => ['required', 'integer', 'min:0'],
            'upLow'     => ['required', 'boolean'],
            'letNum'    => ['required', 'boolean'],
            'specChar'  => ['required', 'boolean'],
            'maxLog'    => ['required', 'integer', 'min:0'],
        ]);

        $doneBy = $this->actorCode($request);

        try {
            $params = json_encode([
                'json_data' => [
                    'minimChar' => (int) $validated['minimChar'],
                    'passExp'   => (int) $validated['passExp'],
                    'passHis'   => (int) $validated['passHis'],
                    'upLow'     => (bool) $validated['upLow'] ? 1 : 0,
                    'letNum'    => (bool) $validated['letNum'] ? 1 : 0,
                    'specChar'  => (bool) $validated['specChar'] ? 1 : 0,
                    'maxLog'    => (int) $validated['maxLog'],
                    'doneBy'    => $doneBy,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $rows = $this->connection()->select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['UpsertPolicy', $params]
            );

            $decoded = $this->decodeResultRow($rows);

            if (
                is_array($decoded)
                && ($decoded['status'] ?? '') === 'success'
            ) {
                return response()->json([
                    'success' => true,
                    'status'  => 'success',
                    'message' => $decoded['message'] ?? 'Login / Password Policy saved successfully.',
                    'data'    => [
                        'minimChar' => (int) $validated['minimChar'],
                        'passExp'   => (int) $validated['passExp'],
                        'passHis'   => (int) $validated['passHis'],
                        'upLow'     => (bool) $validated['upLow'],
                        'letNum'    => (bool) $validated['letNum'],
                        'specChar'  => (bool) $validated['specChar'],
                        'maxLog'    => (int) $validated['maxLog'],
                    ],
                ], 200);
            }

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => is_array($decoded)
                    ? ($decoded['message'] ?? 'Failed to save Login / Password Policy.')
                    : 'Failed to save Login / Password Policy.',
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Failed to save Login / Password Policy: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * RELEASE LOCKED ACCOUNT
     *
     * A true lock is:
     *   ACTIVE = 'N'
     *   HS_SEC.MAXLOG > 0
     *   STAT >= HS_SEC.MAXLOG
     *
     * sproc ReleaseAccount must set:
     *   ACTIVE = 'Y'
     *   STAT = 0
     *   LOGIN_STAT = 0
     * ============================================================
     */
    public function releaseLockedAccount(Request $request)
    {
        $request->validate([
            'userCode' => ['required', 'string'],
        ]);

        $userCode = trim((string) $request->input('userCode'));

        if ($this->isFixedAccount($userCode)) {
            return $this->fixedAccountError($userCode);
        }

        // Matches the original UpdateUser security model: only X may release locks.
        if ($this->actorType() !== 'X') {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Only a Security Administrator can release a locked account.',
            ], 403);
        }

        $doneBy = $this->actorCode($request);

        try {
            $conn = $this->connection();

            $user = $conn
                ->table('users')
                ->where('user_code', $userCode)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'User not found.',
                ], 404);
            }

            $userData = array_change_key_case((array) $user, CASE_LOWER);

            $policy = $conn
                ->table('HS_SEC')
                ->orderBy('secID')
                ->first();

            $policyData = $policy
                ? array_change_key_case((array) $policy, CASE_LOWER)
                : [];

            $maxLog = (int) ($policyData['maxlog'] ?? 0);
            $stat   = (int) ($userData['stat'] ?? 0);
            $active = strtoupper(trim((string) ($userData['active'] ?? '')));

            $isLocked = $maxLog > 0
                && $active === 'N'
                && $stat >= $maxLog;

            if (!$isLocked) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'The selected user is not locked by failed login attempts.',
                    'data' => [
                        'active' => $active,
                        'stat'   => $stat,
                        'maxLog' => $maxLog,
                    ],
                ], 422);
            }

            $rows = $conn->transaction(function () use ($conn, $userCode, $doneBy) {
                return $conn->select(
                    'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                    [
                        'ReleaseAccount',
                        json_encode([
                            'json_data' => [
                                'userCode' => $userCode,
                                'doneBy'   => $doneBy,
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]
                );
            });

            $decoded = $this->decodeResultRow($rows);

            if (
                is_array($decoded)
                && isset($decoded['status'])
                && $decoded['status'] !== 'success'
            ) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => $decoded['message'] ?? 'Failed to release account.',
                ], 422);
            }

            $this->touchRegistrationInfo($userCode, $doneBy, false);

            // Verify the exact state requested by User Management.
            $released = $conn
                ->table('users')
                ->where('user_code', $userCode)
                ->first();

            $releasedData = $released
                ? array_change_key_case((array) $released, CASE_LOWER)
                : [];

            if (
                strtoupper((string) ($releasedData['active'] ?? '')) !== 'Y'
                || (int) ($releasedData['stat'] ?? -1) !== 0
                || (int) ($releasedData['login_stat'] ?? -1) !== 0
            ) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'Release procedure completed but the account state was not fully reset.',
                    'data' => [
                        'active'    => $releasedData['active'] ?? null,
                        'stat'      => $releasedData['stat'] ?? null,
                        'loginStat' => $releasedData['login_stat'] ?? null,
                    ],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Account released successfully.',
                'data' => [
                    'userCode'  => $userCode,
                    'active'    => 'Y',
                    'stat'      => 0,
                    'loginStat' => 0,
                ],
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Release failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
