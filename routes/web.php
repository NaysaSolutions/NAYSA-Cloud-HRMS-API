<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserBioController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::middleware(['web', 'tenant'])->prefix('api/user-bio')->group(function () {
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

Route::prefix('api')
    ->middleware(['web', 'tenant']) // <= IMPORTANT: web + tenant
    ->group(function () {
        Route::post('/login',  [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
        Route::get('/me',      [AuthController::class, 'me'])->middleware('auth');
        Route::post('/auth/heartbeat', [AuthController::class, 'heartbeat'])->middleware('auth');
    });

/*
|--------------------------------------------------------------------------
| Your SPA shell + fallback
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|sanctum|storage|broadcasting|horizon|telescope).*$');