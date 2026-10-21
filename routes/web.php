<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserBioController;
use App\Http\Controllers\HeartStrongController;
use App\Http\Controllers\LicenseManagementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file already receives Laravel's "web" middleware group.
| Session-backed API routes are placed here so StartSession always runs.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Biometric routes
|--------------------------------------------------------------------------
*/

Route::middleware('tenant')
    ->prefix('api/user-bio')
    ->group(function () {
        Route::post('/register/options', [UserBioController::class, 'registerOptions']);
        Route::post('/register/verify', [UserBioController::class, 'registerVerify']);
        Route::post('/login/options', [UserBioController::class, 'loginOptions']);
        Route::post('/login/verify', [UserBioController::class, 'loginVerify']);
        Route::post('/login/options-passwordless', [UserBioController::class, 'loginOptionsPasswordless']);
        Route::post('/login/verify-passwordless', [UserBioController::class, 'loginVerifyPasswordless']);
        Route::get('/list/{userCode}', [UserBioController::class, 'listByUser']);
        Route::post('/deactivate', [UserBioController::class, 'deactivate']);
        Route::post('/delete', [UserBioController::class, 'delete']);
    });

/*
|--------------------------------------------------------------------------
| Authentication and fixed system accounts
|--------------------------------------------------------------------------
*/
Route::middleware('tenant')
    ->prefix('api')
    ->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);

        /*
        |--------------------------------------------------------------------------
        | Active-session login approval flow
        |--------------------------------------------------------------------------
        */
        Route::match(
['get', 'post'],
            '/login/pending-request',
            [AuthController::class, 'pendingLoginRequest']
        );

        Route::get(
            '/login/request-status/{requestId}',
            [AuthController::class, 'loginRequestStatus']
        );

        Route::post(
            '/login/approve-request',
            [AuthController::class, 'approveLoginRequest']
        );

        Route::post(
            '/login/deny-request',
            [AuthController::class, 'denyLoginRequest']
        );

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/auth/heartbeat', [AuthController::class, 'heartbeat']);

        /*
        |--------------------------------------------------------------------------
        | HeartStrong setup
        |--------------------------------------------------------------------------
        */
        Route::prefix('heartstrong')
            ->middleware('account.mode:LICENSE_ADMIN,SYSTEM_ADMIN')
            ->group(function () {
                Route::get(
                    '/options',
                    [HeartStrongController::class, 'options']
                );

                Route::post('/options',[HeartStrongController::class, 'updateOption']);
                Route::get('/modules', [HeartStrongController::class, 'modules']);
                Route::post('/modules', [HeartStrongController::class, 'updateModule']);
                Route::post('/modules/reset', [HeartStrongController::class, 'resetModules']);
                Route::get('/document-dropdowns',[HeartStrongController::class, 'documentDropdowns']);
                Route::post('/document-dropdowns/save',[HeartStrongController::class, 'saveDocumentDropdown']);
                Route::post('/document-dropdowns/delete',[HeartStrongController::class, 'deleteDocumentDropdown']);
                Route::get('/environment',[HeartStrongController::class, 'environment']);
                Route::post('/environment',[HeartStrongController::class, 'updateEnvironment']);
                Route::get('/documents',[HeartStrongController::class, 'documents']);
                Route::post('/documents/save',[HeartStrongController::class, 'saveDocument']);
            });

        /*
        |--------------------------------------------------------------------------
        | License seats — HeartStrong fourth tab
        |--------------------------------------------------------------------------
        */
        Route::prefix('license-management')
            ->middleware('account.mode:LICENSE_ADMIN,SYSTEM_ADMIN')
            ->group(function () {
                Route::get('/test', function (Request $request) {
                    return response()->json([
                        'success' => true,
                        'hasSession' => $request->hasSession(),
                        'sessionId' => $request->session()->getId(),
                        'userCode' => $request->session()->get(
                            'SYSTEM_ACCOUNT_CODE'
                        ),
                        'accountMode' => $request->session()->get(
                            'ACCOUNT_MODE'
                        ),
                        'permissionUserCode' => $request->session()->get(
                            'PERMISSION_USER_CODE'
                        ),
                        'licenseExempt' => (bool) $request->session()->get(
                            'LICENSE_EXEMPT',
                            false
                        ),
                    ]);
                });

                Route::get('/status',[LicenseManagementController::class, 'status']);
                Route::post('/seats',[LicenseManagementController::class, 'updateSeats']);
            });
    });

/*
|--------------------------------------------------------------------------
| SPA shell and fallback
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|sanctum|storage|broadcasting|horizon|telescope).*$');

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::get('/{any}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '^(?!api(?:/|$)|sanctum(?:/|$)).*');