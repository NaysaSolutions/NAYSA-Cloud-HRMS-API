<?php

namespace App\Http\Controllers;
use App\Models\User; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Support\TenantCatalog;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Return companies for the login dropdown (from tenants.csv).
     */
    public function companies()
    {
        $catalog = new TenantCatalog();
        $list = array_map(function ($r) {
            return [
                'code'     => $r['code']     ?? '',
                'company'  => $r['company']  ?? '',
                'database' => $r['database'] ?? '',
            ];
        }, $catalog->all());

        return response()->json([
            'success' => true,
            'data'    => $list,
        ]);
    }

    /**
     * Optional: quick diagnostic to confirm the active tenant binding.
     */
    public function ping(Request $req)
    {
        return response()->json([
            'ok'      => true,
            'message' => 'Tenant connection active',
            'tenant'  => [
                'database' => $req->attributes->get('tenant.database'),
                'code'     => $req->attributes->get('tenant.code'),
                'company'  => $req->attributes->get('tenant.company'),
            ],
        ]);
    }

    /**
     * Register a user INSIDE the current tenant DB.
     */
    public function register(Request $req)
    {
        $v = Validator::make($req->all(), [
            'USER_CODE' => ['required', 'string', 'max:10'],
            'USER_NAME' => ['required', 'string', 'max:100'],
            'EMAIL_ADD' => ['required', 'email', 'max:255'],
            'PASSWORD'  => ['required', 'string', 'min:6'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $v->errors()->first(),
            ], 422);
        }

        $userId   = trim($req->input('USER_CODE'));
        $username = trim($req->input('USER_NAME'));
        $email    = trim($req->input('EMAIL_ADD'));
        $password = $req->input('PASSWORD');

        try {
            // Uniqueness checks are performed against the CURRENT tenant DB
            $existsUsername = DB::table('USERS')->where('USER_NAME', $username)->exists();
            if ($existsUsername) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Username already taken.',
                ], 409);
            }

            $existsEmail = DB::table('USERS')->where('EMAIL_ADD', $email)->exists();
            if ($existsEmail) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Email already registered.',
                ], 409);
            }

            // NOTE: The USERS table must have a BIGINT IDENTITY 'id' column for Sanctum to work.
            DB::table('USERS')->insert([
                'USER_CODE'  => $userId,
                'USER_NAME'  => $username,
                'EMAIL_ADD'  => $email,
                'PASSWORD'   => Hash::make($password), // bcrypt
            ]);

            $user = DB::table('USERS')
                ->select('USER_CODE', 'USER_NAME', 'EMAIL_ADD')
                ->where('USER_NAME', $username)
                ->first();

            return response()->json([
                'status'  => 'success',
                'message' => 'Registration successful.',
                'data'    => $user,
                'tenant'  => [
                    'database' => $req->attributes->get('tenant.database'),
                    'code'     => $req->attributes->get('tenant.code'),
                    'company'  => $req->attributes->get('tenant.company'),
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Registration failed. ' . $e->getMessage(),
            ], 500);
        }
    }

    
    /**
     * Handles login, generates a new token, revokes the old token, and enforces 
     * the single-session rule by recording the new token's ID.
     */
    // public function login(Request $req)
    // {
    //     $v = Validator::make($req->all(), [
    //         'USER_CODE' => ['required', 'string'],
    //         'PASSWORD'  => ['required', 'string'],
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => $v->errors()->first(),
    //         ], 422);
    //     }

    //     $userId   = trim($req->input('USER_CODE'));
    //     $password = $req->input('PASSWORD');

    //     try {
    //         // Retrieve the user using the Eloquent Model
    //         $user = User::where('USER_CODE', $userId)->first();

    //         // 1. Validate Credentials
    //         if (!$user || !Hash::check($password, $user->PASSWORD)) {
    //             return response()->json([
    //                 'status'  => 'error',
    //                 'message' => 'Invalid credentials.',
    //             ], 401);
                
    //         }

    //         // --- SINGLE SESSION ENFORCEMENT START ---

    //         // 2. Revoke the previous token if one exists (by looking up the ID we saved earlier)
    //         if ($user->current_token_id) {
    //             PersonalAccessToken::find($user->current_token_id)?->delete();
    //         }

    //         // 3. Generate a new token (which also inserts a new record into personal_access_tokens)
    //         $newToken = $user->createToken('auth_token');
    //         $token = $newToken->plainTextToken; 
            
    //         // Get the actual database ID of the newly created token record
    //         $tokenId = $newToken->accessToken->id; 

    //         // 4. Store the new token's ID in the user's record
    //         // This is the ID checked by the check.single.session middleware
    //         $user->update(['current_token_id' => $tokenId]);
            
    //         // --- SINGLE SESSION ENFORCEMENT END ---

    //         $data = [
    //             'USER_CODE' => $user->USER_CODE,
    //             'USER_NAME' => $user->USER_NAME,
    //             'EMAIL_ADD' => $user->EMAIL_ADD,
    //         ];

    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'Login successful.',
    //             'data'    => $data,
    //             'token'   => $token, // Send the new token to React
    //             'tenant'  => [
    //                 'database' => $req->attributes->get('tenant.database'),
    //                 'code'     => $req->attributes->get('tenant.code'),
    //                 'company'  => $req->attributes->get('tenant.company'),
    //             ],
    //         ], 200);

    //     } catch (Throwable $e) {
    //         // Reverting to the generic failure message, as intended for production
    //         \Log::error("Login failed for user {$userId}: " . $e->getMessage()); 
            
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'Login failed due to a server error. Please check database configuration.', 
    //         ], 500);
    //     }
    // }

    // /**
    //  * Log the user out by deleting their current token and clearing the token ID record.
    //  */
    // public function logout(Request $req)
    // {
    //     $user = $req->user();

    //     if ($user) {
    //         // Delete the token being used for the current request
    //         $user->currentAccessToken()->delete(); 
    //         // Clear the single session flag on the user record
    //         $user->update(['current_token_id' => null]); 
    //     }
    //     return response()->json(['message' => 'Successfully logged out']);
    // }


    public function login(Request $req)
{
    $v = Validator::make($req->all(), [
        'USER_CODE' => ['required', 'string'],
        'PASSWORD'  => ['required', 'string'],
    ]);
    if ($v->fails()) {
        return response()->json([
            'status'  => 'error',
            'message' => $v->errors()->first(),
        ], 422);
    }

    $userId   = trim($req->input('USER_CODE'));
    $password = $req->input('PASSWORD');

    try {
        $user = User::where('USER_CODE', $userId)->first();

        // 1) Validate credentials
        if (!$user || !Hash::check($password, $user->PASSWORD)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // 2) STRICT LOCK: if a recorded token is still active, BLOCK this login
        if ($user->current_token_id) {
            $active = PersonalAccessToken::find($user->current_token_id);
            if ($active) {
                return response()->json([
                    'status'  => 'error',
                    'code'    => 'ALREADY_LOGGED_IN',
                    'message' => 'You are already signed in on another device. Please log out there first.',
                    'session' => [
                        'user_agent' => $user->current_token_user_agent,
                        'ip'         => $user->current_token_ip,
                        'since'      => $user->current_token_created_at,
                    ],
                ], 409);
            } else {
                // Stale pointer → clear it
                $user->forceFill([
                    'current_token_id'          => null,
                    'current_token_user_agent'  => null,
                    'current_token_ip'          => null,
                    'current_token_created_at'  => null,
                ])->save();
            }
        }

        // 3) Issue a new token (only now that we know none is active)
        $newToken = $user->createToken('auth_token');
        $token    = $newToken->plainTextToken;
        $tokenId  = $newToken->accessToken->id;

        // 4) Record token + metadata
        // $user->forceFill([
        //     'current_token_id'          => $tokenId,
        //     'current_token_user_agent'  => $req->userAgent(),
        //     'current_token_ip'          => $req->ip(),
        //     'current_token_created_at'  => now(),
        // ])->save();

        $data = [
            'USER_CODE' => $user->USER_CODE,
            'USER_NAME' => $user->USER_NAME,
            'EMAIL_ADD' => $user->EMAIL_ADD,
        ];

        return response()->json([
            'status'  => 'success',
            'message' => 'Login successful.',
            'data'    => $data,
            'token'   => $token,
            'tenant'  => [
                'database' => $req->attributes->get('tenant.database'),
                'code'     => $req->attributes->get('tenant.code'),
                'company'  => $req->attributes->get('tenant.company'),
            ],
        ], 200);

    } catch (Throwable $e) {
        \Log::error("Login failed for user {$userId}: ".$e->getMessage());
        return response()->json([
            'status'  => 'error',
            'message' => 'Login failed due to a server error. Please check database configuration.',
        ], 500);
    }
}

/**
 * Logout: delete the current token and clear the strict-lock pointers.
 */
public function logout(Request $req)
{
    $user = $req->user();

    if ($user) {
        $user->currentAccessToken()?->delete();
        $user->forceFill([
            'current_token_id'          => null,
            'current_token_user_agent'  => null,
            'current_token_ip'          => null,
            'current_token_created_at'  => null,
        ])->save();
    }

    return response()->json(['message' => 'Successfully logged out']);
}


}
