<?php

use App\Http\Controllers\API\AdminAiPostController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BlockController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\FeedCategoryController;
use App\Http\Controllers\API\FeedSearchController;
use App\Http\Controllers\API\HelpCenterController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PolicyController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\UserFeedTopicController;
use App\Http\Controllers\API\UserProfileViewController;
use App\Http\Controllers\API\WorkspaceController;
use App\Http\Controllers\API\ConversationController;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────────────────
// Public routes
// ──────────────────────────────────────────────────────────────────────────────

Route::controller(AuthController::class)->group(function () {
    Route::post('/user-signup', 'signup');
    Route::post('/verify-email', 'verifyEmail');
    Route::post('/resend-otp', 'resendOtp');

    Route::post('/user-signin', 'signin');
    Route::post('/user-logout', 'logout');

    Route::post('/forgot-password', 'sendOtp');
    Route::post('/verify-otp', 'verifyOtp');
    Route::post('/reset-password', 'resetPassword');

    Route::post('/store-user-fcm-token', 'storeFcmToken');
    Route::post('/delete-user-fcm-token', 'deleteFcmToken');
    Route::post('/delete-account', 'deleteUser');
});

// ──────────────────────────────────────────────────────────────────────────────
// Authenticated routes
// ──────────────────────────────────────────────────────────────────────────────

Route::middleware('auth:api')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────────
    Route::controller(AuthController::class)->group(function () {
        Route::post('/setup-profile', 'setupProfile');
    });

    // ── Profile & User Management ─────────────────────────────────────────────
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/user-profile', 'profile');
        Route::post('/update-user-profile', 'updateProfile');
        Route::post('/change-user-password', 'changePassword');
        Route::post('/user-delete', 'deleteUser');
        Route::post('/update-basic-info', 'updateBasicInfo');

        Route::get('/gallery', 'getGallery');
        Route::post('/gallery/upload', 'uploadGalleryImage');
        Route::delete('/gallery/{id}', 'deleteGalleryImage');

        Route::get('/dating-preferences', 'getDatingPreferences');
        Route::post('/update-dating-preferences', 'updateDatingPreferences');

        Route::post('/update-profile-setup', 'updateProfileSetup');
        Route::post('/update-identity-location', 'updateIdentityLocation');

        Route::get('/visual-info', 'getVisualInfo');
        Route::post('/update-visual-info', 'updateVisualInfo');
        Route::post('/visual-info/upload-photo', 'uploadVisualInfoPhoto');

        Route::post('/update-appearance-lifestyle', 'updateAppearanceLifestyle');
        Route::post('/update-interests-personality', 'updateInterestsPersonality');
        Route::post('/update-matching-criteria', 'updateMatchingCriteria');
    });

    // ── User Profile View (other users) ──────────────────────────────────────
    Route::controller(UserProfileViewController::class)->group(function () {
        Route::get('/user/{id}/basic-info', 'basicInfo');
        Route::get('/user/{id}/gallery', 'gallery');
        Route::get('/user/{id}/identity-location', 'identityLocation');
        Route::get('/user/{id}/visual-info', 'visualInfo');
        Route::get('/user/{id}/appearance-lifestyle', 'appearanceLifestyle');
        Route::get('/user/{id}/interests-personality', 'interestsPersonality');
        Route::get('/user/{id}/matching-criteria', 'matchingCriteria');
        Route::get('/user/{id}/knowledge-base', 'knowledgeBase');
    });

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::controller(NotificationController::class)->group(function () {
        Route::get('/notifications', 'notification');
        Route::post('/notifications/mark-all-read', 'markAllRead');
        Route::post('/notifications/mark-all-unread', 'markAllUnread');
        Route::post('/notifications/delete-all', 'deleteAll');
        Route::post('/notifications/delete', 'deleteNotification');
        Route::post('/notifications/mark-read', 'markNotificationRead');
        Route::post('/notifications/mark-unread', 'markNotificationUnread');
    });

    // ── Workspaces ────────────────────────────────────────────────────────────
    Route::controller(WorkspaceController::class)->group(function () {
        Route::get('/workspaces', 'index');
        Route::post('/workspaces', 'store')->middleware('admin');
    });

    // ── AI Conversations ──────────────────────────────────────────────────────
    Route::controller(ConversationController::class)->group(function () {
        Route::post('/conversations', 'store');
        Route::get('/conversations/{id}', 'show');
        Route::post('/conversations/{id}/messages', 'message');
    });

    // ── Feed & Posts ──────────────────────────────────────────────────────────
    Route::controller(PostController::class)->group(function () {
        // Feed (category/topic/type filters via query params)
        Route::get('/feed', 'feed');

        // Post CRUD
        Route::get('/posts/{id}', 'show');
        Route::delete('/posts/{id}', 'destroy');

        // Engagement
        Route::post('/posts/{id}/like', 'like');
        Route::post('/posts/{id}/share', 'share');
        Route::post('/posts/{id}/report', 'report');
    });

    // ── Feed Categories (fixed tabs) ──────────────────────────────────────────
    Route::controller(FeedCategoryController::class)->group(function () {
        Route::get('/feed/categories', 'index');
    });

    // ── User Custom Feed Topics ───────────────────────────────────────────────
    Route::controller(UserFeedTopicController::class)->group(function () {
        Route::get('/feed/topics', 'index');
        Route::post('/feed/topics', 'store');
        Route::delete('/feed/topics/{id}', 'destroy');
    });

    // ── AI Feed Search ────────────────────────────────────────────────────────
    Route::controller(FeedSearchController::class)->group(function () {
        Route::post('/feed/ai-search', 'search');
    });

    // ── Block / Unblock ───────────────────────────────────────────────────────
    Route::controller(BlockController::class)->group(function () {
        Route::post('/users/{id}/block', 'block');
        Route::delete('/users/{id}/block', 'unblock');
    });

    // ── Admin: AI-generated posts ─────────────────────────────────────────────
    Route::controller(AdminAiPostController::class)->middleware('admin')->group(function () {
        Route::post('/admin/ai-posts', 'store');
    });

    // ── Help & Policies ───────────────────────────────────────────────────────
    Route::controller(HelpCenterController::class)->group(function () {
        Route::post('/send-message', 'sendMessage');
    });

    Route::controller(PolicyController::class)->group(function () {
        Route::get('/get-policies-disclaimers', 'getDisclaimersPolicy');
    });

    // ── Provider Dashboard ────────────────────────────────────────────────────
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard-provider', 'dashboardProvider');
    });
});
