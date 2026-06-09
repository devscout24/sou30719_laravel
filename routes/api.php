<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\HelpCenterController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PolicyController;
use App\Http\Controllers\API\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    // Registration & email verification
    Route::post('/user-signup', 'signup');
    Route::post('/verify-email', 'verifyEmail');

    // Login & logout
    Route::post('/user-signin', 'signin');
    Route::post('/user-logout', 'logout');

    // Forgot password flow
    Route::post('/forgot-password', 'sendOtp');
    Route::post('/verify-otp', 'verifyOtp');
    Route::post('/reset-password', 'resetPassword');

    // Account management
    Route::post('/user-delete', 'deleteUser');
    Route::post('/store-user-fcm-token', 'storeFcmToken');
    Route::post('/delete-user-fcm-token', 'deleteFcmToken');
});

Route::controller(ProfileController::class)->middleware('auth:api')->group(function () {
    Route::get('/user-profile', 'profile');
    Route::post('/update-user-profile', 'updateProfile');
    Route::post('/change-user-password', 'changePassword');
});

Route::middleware('auth:api')->controller(NotificationController::class)->group(function () {
    Route::get('/notifications', 'notification');

    // Mark all read / unread
    Route::post('/notifications/mark-all-read', 'markAllRead');
    Route::post('/notifications/mark-all-unread', 'markAllUnread');

    // Delete all
    Route::post('/notifications/delete-all', 'deleteAll');

    // Single operations
    Route::post('/notifications/delete', 'deleteNotification');
    Route::post('/notifications/mark-read', 'markNotificationRead');
    Route::post('/notifications/mark-unread', 'markNotificationUnread');
});

// Customer API Routes (trimmed)
Route::controller(HelpCenterController::class)->middleware('auth:api')->group(function () {
    Route::post('/send-message', 'sendMessage');
});

Route::controller(PolicyController::class)->middleware('auth:api')->group(function () {
    Route::get('/get-policies-disclaimers', 'getDisclaimersPolicy');
});

// Provider API Routes

Route::controller(DashboardController::class)->middleware('auth:api')->group(function () {
    Route::get('/dashboard-provider', 'dashboardProvider');
});






