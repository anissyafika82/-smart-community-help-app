<?php

use App\Http\Controllers\Api\Admin\AssistanceRequestController as AdminAssistanceRequestController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\HelpOfferController as AdminHelpOfferController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AssistanceRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\HelpOfferController;
use App\Http\Controllers\Api\RatingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/help-offers', [HelpOfferController::class, 'index']);
Route::get('/help-offers/nearby', [HelpOfferController::class, 'nearby']);
Route::get('/help-offers/{helpOffer}', [HelpOfferController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated routes (any role)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/onesignal/player-id', [AuthController::class, 'updatePlayerId']);

    /*
    |----------------------------------------------------------------------
    | Chat (helper <-> requester, scoped to a help offer)
    |----------------------------------------------------------------------
    */
    Route::get('/my-chats', [ChatController::class, 'threads']);
    Route::get('/help-offers/{helpOffer}/chat/{user}/messages', [ChatController::class, 'index']);
    Route::post('/help-offers/{helpOffer}/chat/{user}/messages', [ChatController::class, 'store']);

    /*
    |----------------------------------------------------------------------
    | Ratings (either party rates the other after a completed request)
    |----------------------------------------------------------------------
    */
    Route::post('/requests/{assistanceRequest}/rating', [RatingController::class, 'store']);

    /*
    |----------------------------------------------------------------------
    | Helper-only routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:helper')->group(function () {
        Route::get('/my-help-offers', [HelpOfferController::class, 'myHelpOffers']);
        Route::post('/help-offers', [HelpOfferController::class, 'store']);
        Route::put('/help-offers/{helpOffer}', [HelpOfferController::class, 'update']);
        Route::delete('/help-offers/{helpOffer}', [HelpOfferController::class, 'destroy']);

        Route::patch('/requests/{assistanceRequest}/approve', [AssistanceRequestController::class, 'approve']);
        Route::patch('/requests/{assistanceRequest}/on-the-way', [AssistanceRequestController::class, 'onTheWay']);
        Route::patch('/requests/{assistanceRequest}/reject', [AssistanceRequestController::class, 'reject']);
        Route::patch('/requests/{assistanceRequest}/complete', [AssistanceRequestController::class, 'complete']);
    });

    /*
    |----------------------------------------------------------------------
    | Requester-only routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:requester')->group(function () {
        Route::post('/help-offers/{helpOffer}/request', [AssistanceRequestController::class, 'store']);
        Route::patch('/requests/{assistanceRequest}/cancel', [AssistanceRequestController::class, 'cancel']);
        Route::get('/my-requests', [AssistanceRequestController::class, 'myRequests']);
    });

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
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

        Route::get('/help-offers', [AdminHelpOfferController::class, 'index']);
        Route::delete('/help-offers/{helpOffer}', [AdminHelpOfferController::class, 'destroy']);

        Route::get('/requests', [AdminAssistanceRequestController::class, 'index']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });
});
