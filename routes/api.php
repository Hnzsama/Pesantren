<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user()->load([
        'roles:id,name',
        'school',
        'students.school',
        'students.level.department',
        'students.card',
    ]);
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/midtrans/snap-token', [PaymentController::class, 'getSnapToken']);
    Route::get('/transactions', [\App\Http\Controllers\Api\WalletTransactionController::class, 'index']);
    Route::get('/transactions/{id}', [\App\Http\Controllers\Api\WalletTransactionController::class, 'show']);
    Route::post('/transactions/{id}/cancel', [\App\Http\Controllers\Api\WalletTransactionController::class, 'cancel']);
    Route::post('/transactions/{id}/pay', [PaymentController::class, 'pay']);
    Route::get('/activities', [\App\Http\Controllers\Api\ActivityController::class, 'index']);
    Route::get('/students/{id}', [\App\Http\Controllers\Api\StudentController::class, 'show']);
    Route::get('/students/{id}/wallet', [\App\Http\Controllers\Api\StudentController::class, 'wallet']);
    Route::post('/students/{id}/wallet/pin', [\App\Http\Controllers\Api\StudentController::class, 'updatePin']);
    Route::post('/students/{id}/wallet/limit', [\App\Http\Controllers\Api\StudentController::class, 'updateDailyLimit']);
    Route::get('/bills', [\App\Http\Controllers\Api\BillController::class, 'index']);
    Route::get('/bills/{id}', [\App\Http\Controllers\Api\BillController::class, 'show']);
    Route::post('/bills/{id}/pay', [\App\Http\Controllers\Api\BillController::class, 'pay']);
    Route::get('/permissions', [\App\Http\Controllers\Api\StudentPermissionController::class, 'index']);
    Route::post('/permissions', [\App\Http\Controllers\Api\StudentPermissionController::class, 'store']);
    Route::apiResource('kajian', \App\Http\Controllers\Api\KajianController::class)->only(['index', 'show']);
    Route::apiResource('prestasi', \App\Http\Controllers\Api\AchievementController::class)->only(['index', 'show']);
    Route::get('/payment-channels', [\App\Http\Controllers\Api\PaymentChannelController::class, 'index']);
    Route::get('/student-cards', [\App\Http\Controllers\Api\StudentCardController::class, 'index']);
    Route::get('/student-cards/{id}', [\App\Http\Controllers\Api\StudentCardController::class, 'show']);
    Route::get('/student-cards/number/{cardNumber}', [\App\Http\Controllers\Api\StudentCardController::class, 'getByCardNumber']);

    // News API
    Route::apiResource('news', \App\Http\Controllers\Api\NewsController::class)->names('api.news');

    // Reports API
    Route::get('reports/filters', [\App\Http\Controllers\Api\ReportController::class, 'getFilters']);
    Route::get('reports', [\App\Http\Controllers\Api\ReportController::class, 'index']);
});

Route::post('/midtrans/callback', [PaymentController::class, 'callback']);

require __DIR__.'/authApi.php';
