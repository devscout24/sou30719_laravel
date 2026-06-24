<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\HelpCenterController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PolicyController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\Api\UserProfileViewController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    // Registration & email verification
    Route::post('/user-signup', 'signup');
    Route::post('/verify-email', 'verifyEmail');
    Route::post('/resend-otp', 'resendOtp');

    // Login & logout
    Route::post('/user-signin', 'signin');
    Route::post('/user-logout', 'logout');

    // Forgot password flow
    Route::post('/forgot-password', 'sendOtp');
    Route::post('/verify-otp', 'verifyOtp');
    Route::post('/reset-password', 'resetPassword');

    // Account management
    Route::post('/store-user-fcm-token', 'storeFcmToken');
    Route::post('/delete-user-fcm-token', 'deleteFcmToken');
    Route::post('/delete-account', 'deleteUser');
});

// Authenticated routes (needs JWT)
Route::middleware('auth:api')->controller(AuthController::class)->group(function () {
    Route::post('/setup-profile', 'setupProfile');
});

// Profile & User Management
Route::controller(ProfileController::class)->middleware('auth:api')->group(function () {
    // Existing
    Route::get('/user-profile', 'profile');
    Route::post('/update-user-profile', 'updateProfile');
    Route::post('/change-user-password', 'changePassword');
    Route::post('/user-delete', 'deleteUser');

    // Basic Info (extends existing profile/updateProfile — see note below)
    Route::post('/update-basic-info', 'updateBasicInfo');

    // Gallery
    Route::get('/gallery', 'getGallery');
    Route::post('/gallery/upload', 'uploadGalleryImage');
    Route::delete('/gallery/{id}', 'deleteGalleryImage');

    // Dating Preferences — top level toggle/sliders
    Route::get('/dating-preferences', 'getDatingPreferences');
    Route::post('/update-dating-preferences', 'updateDatingPreferences');

    // Profile set-up
    Route::post('/update-profile-setup', 'updateProfileSetup');

    // Identity & Location
    Route::post('/update-identity-location', 'updateIdentityLocation');

    // Visual Info
    Route::get('/visual-info', 'getVisualInfo');
    Route::post('/update-visual-info', 'updateVisualInfo');
    Route::post('/visual-info/upload-photo', 'uploadVisualInfoPhoto');

    // Appearance & Lifestyle
    Route::post('/update-appearance-lifestyle', 'updateAppearanceLifestyle');

    // Interests & Personality
    Route::post('/update-interests-personality', 'updateInterestsPersonality');

    // Matching Criteria
    Route::post('/update-matching-criteria', 'updateMatchingCriteria');
});

Route::controller(UserProfileViewController::class)->middleware('auth:api')->group(function () {
    Route::get('/user/{id}/basic-info', 'basicInfo');
    Route::get('/user/{id}/gallery', 'gallery');
    Route::get('/user/{id}/identity-location', 'identityLocation');
    Route::get('/user/{id}/visual-info', 'visualInfo');
    Route::get('/user/{id}/appearance-lifestyle', 'appearanceLifestyle');
    Route::get('/user/{id}/interests-personality', 'interestsPersonality');
    Route::get('/user/{id}/matching-criteria', 'matchingCriteria');
    Route::get('/user/{id}/knowledge-base', 'knowledgeBase');
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

Route::middleware('auth:api')->controller(WorkspaceController::class)->group(function () {
    Route::get('/workspaces', 'index');
    Route::post('/workspaces', 'store')->middleware('admin');
});

Route::middleware('auth:api')->controller(ConversationController::class)->group(function () {
    Route::post('/conversations', 'store');
    Route::get('/conversations/{id}', 'show');
    Route::post('/conversations/{id}/messages', 'message');
});

Route::middleware('auth:api')->controller(PostController::class)->group(function () {
    // Feed & post details
    Route::get('/posts/feed', 'feed');
    Route::get('/posts/{id}', 'show');

    // Engagement
    Route::post('/posts/{id}/like', 'like');
    Route::post('/posts/{id}/share', 'share');
});

// ======================================================================
// ======================================================================
// ======================================================================

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
