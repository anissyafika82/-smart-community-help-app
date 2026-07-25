<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ItemClaimController as AdminItemClaimController;
use App\Http\Controllers\Api\Admin\ItemReportController as AdminItemReportController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CustomLocationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ItemClaimController;
use App\Http\Controllers\Api\ItemReportController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TelegramLinkController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\VerificationQuestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/item-reports', [ItemReportController::class, 'index']);
Route::get('/item-reports/nearby', [ItemReportController::class, 'nearby']);
Route::get('/item-reports/{itemReport}', [ItemReportController::class, 'show']);
Route::get('/item-reports/{itemReport}/matches', [MatchController::class, 'forItemReport']);

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Authenticated routes (single "user" role, plus admin below)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/onesignal/player-id', [AuthController::class, 'updatePlayerId']);
    Route::post('/telegram/link-code', [TelegramLinkController::class, 'generate']);

    Route::get('/locations/search', [CustomLocationController::class, 'search']);
    Route::post('/locations', [CustomLocationController::class, 'store']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Item reports (report/browse/manage lost & found items)
    |----------------------------------------------------------------------
    */
    Route::get('/my-item-reports', [ItemReportController::class, 'myItemReports']);
    Route::post('/item-reports', [ItemReportController::class, 'store']);
    Route::put('/item-reports/{itemReport}', [ItemReportController::class, 'update']);
    Route::delete('/item-reports/{itemReport}', [ItemReportController::class, 'destroy']);

    /*
    |----------------------------------------------------------------------
    | Claims (submit an ownership claim; report owner verifies/rejects;
    |  either party marks the item returned)
    |----------------------------------------------------------------------
    */
    Route::post('/item-reports/{itemReport}/claims', [ItemClaimController::class, 'store']);
    Route::get('/my-claims', [ItemClaimController::class, 'myClaims']);
    Route::patch('/claims/{itemClaim}/approve', [ItemClaimController::class, 'approve']);
    Route::patch('/claims/{itemClaim}/reject', [ItemClaimController::class, 'reject']);
    Route::patch('/claims/{itemClaim}/return', [ItemClaimController::class, 'markReturned']);
    Route::patch('/claims/{itemClaim}/cancel', [ItemClaimController::class, 'cancel']);

    /*
    |----------------------------------------------------------------------
    | Private ownership-verification Q&A (found reports only)
    |----------------------------------------------------------------------
    */
    Route::get('/item-reports/{itemReport}/verification-questions', [VerificationQuestionController::class, 'forItemReport']);
    Route::get('/claims/{itemClaim}/answers', [ItemClaimController::class, 'answers']);

    /*
    |----------------------------------------------------------------------
    | Chat (report owner <-> claimant, scoped to an item report)
    |----------------------------------------------------------------------
    */
    Route::get('/my-chats', [ChatController::class, 'threads']);
    Route::get('/item-reports/{itemReport}/chat/{user}/messages', [ChatController::class, 'index']);
    Route::post('/item-reports/{itemReport}/chat/{user}/messages', [ChatController::class, 'store']);

    /*
    |----------------------------------------------------------------------
    | Ratings (either party rates the other after an item is returned)
    |----------------------------------------------------------------------
    */
    Route::post('/claims/{itemClaim}/rating', [RatingController::class, 'store']);
    Route::get('/ratings/received', [RatingController::class, 'received']);

    /*
    |----------------------------------------------------------------------
    | Activity timeline (own activity only)
    |----------------------------------------------------------------------
    */
    Route::get('/activities', [ActivityController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Reports (report a claim or user; any user may file one)
    |----------------------------------------------------------------------
    */
    Route::post('/reports', [ReportController::class, 'store']);

    /*
    |----------------------------------------------------------------------
    | Admin-only routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);
        Route::patch('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

        Route::get('/item-reports', [AdminItemReportController::class, 'index']);
        Route::delete('/item-reports/{itemReport}', [AdminItemReportController::class, 'destroy']);

        Route::get('/item-claims', [AdminItemClaimController::class, 'index']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::patch('/reports/{report}', [AdminReportController::class, 'update']);
    });
});
