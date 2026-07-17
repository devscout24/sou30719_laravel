<?php

use App\Http\Controllers\API\AdminAiPostController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BlockController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\FeedSearchController;
use App\Http\Controllers\API\FriendController;
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

        Route::get('/basic-info', 'getBasicInfo');
        Route::post('/update-basic-info', 'updateBasicInfo');

        Route::get('/gallery', 'getGallery');
        Route::post('/gallery/upload', 'uploadGalleryImage');
        Route::delete('/gallery/{id}', 'deleteGalleryImage');

        Route::get('/dating-preferences', 'getDatingPreferences');
        Route::post('/update-dating-preferences', 'updateDatingPreferences');

        Route::get('/profile-setup', 'getProfileSetup');
        Route::post('/update-profile-setup', 'updateProfileSetup');

        Route::get('/identity-location', 'getIdentityLocation');
        Route::post('/update-identity-location', 'updateIdentityLocation');

        Route::get('/visual-info', 'getVisualInfo');
        Route::post('/update-visual-info', 'updateVisualInfo');
        Route::post('/visual-info/upload-photo', 'uploadVisualInfoPhoto');

        Route::get('/appearance-lifestyle', 'getAppearanceLifestyle');
        Route::post('/update-appearance-lifestyle', 'updateAppearanceLifestyle');

        Route::get('/interests-personality', 'getInterestsPersonality');
        Route::post('/update-interests-personality', 'updateInterestsPersonality');

        Route::get('/matching-criteria', 'getMatchingCriteria');
        Route::post('/update-matching-criteria', 'updateMatchingCriteria');
    });

    // ── User Profile View (other users) ──────────────────────────────────────
    Route::controller(UserProfileViewController::class)->group(function () {
        Route::get('/user/{username}/basic-info', 'basicInfo');
        Route::get('/user/{username}/gallery', 'gallery');
        Route::get('/user/{username}/identity-location', 'identityLocation');
        Route::get('/user/{username}/visual-info', 'visualInfo');
        Route::get('/user/{username}/appearance-lifestyle', 'appearanceLifestyle');
        Route::get('/user/{username}/interests-personality', 'interestsPersonality');
        Route::get('/user/{username}/matching-criteria', 'matchingCriteria');
        Route::get('/user/{username}/knowledge-base', 'knowledgeBase');
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
        Route::get('/workspaces/{workspace}', 'show');
        Route::post('/workspaces/{workspace}/nav-access', 'grantNavAccess')->middleware('admin');
        Route::delete('/workspaces/{workspace}/nav-access', 'revokeNavAccess')->middleware('admin');
    });

    // ── AI Conversations ──────────────────────────────────────────────────────
    Route::controller(ConversationController::class)->group(function () {
        Route::post('/conversations', 'store');
        Route::get('/conversations/{slug}', 'show');
        Route::post('/conversations/{slug}/messages', 'message');
    });

    // ── Feed & Posts ──────────────────────────────────────────────────────────
    Route::controller(PostController::class)->group(function () {
        // Feed (category/topic/type filters via query params)
        Route::get('/feed', 'feed');

        // Post CRUD
        Route::get('/posts/{slug}', 'show');
        Route::delete('/posts/{slug}', 'destroy');

        // Engagement
        Route::post('/posts/{slug}/like', 'like');
        Route::post('/posts/{slug}/share', 'share');
        Route::post('/posts/{slug}/report', 'report');
    });

    // ── Feed Topics: fixed (built-in) + user-added, unified ───────────────────
    Route::controller(UserFeedTopicController::class)->group(function () {
        Route::get('/feed/topics', 'index');
        Route::post('/feed/topics', 'store');
        Route::delete('/feed/topics/{id}', 'destroy');

        // Deprecated alias — old clients still calling the previous "categories"
        // endpoint get the same unified fixed+custom topic list.
        Route::get('/feed/categories', 'index');
    });

    // ── AI Feed Search ────────────────────────────────────────────────────────
    Route::controller(FeedSearchController::class)->group(function () {
        Route::post('/feed/ai-search', 'search');
    });

    // ── Friends: Connected & Curate ──────────────────────────────────────────
    Route::controller(FriendController::class)->group(function () {
        Route::get('/friends/connected', 'connected');
        Route::get('/friends/{username}/curate', 'curate');

        // ── Requests (sent / received) ───────────────────────────────────────
        Route::get('/friends/requests/sent', 'sentRequests');
        Route::get('/friends/requests/received', 'receivedRequests');
        Route::post('/friends/requests/{id}', 'sendRequest');
        Route::post('/friends/requests/{id}/accept', 'acceptRequest');
        Route::post('/friends/requests/{id}/reject', 'rejectRequest');
        Route::delete('/friends/requests/{id}', 'cancelRequest');

        // ── Favourites ────────────────────────────────────────────────────────
        Route::get('/friends/favorites', 'favorites');
        Route::post('/friends/favorites/{id}', 'addFavorite');
        Route::delete('/friends/favorites/{id}', 'removeFavorite');

        // ── Search ────────────────────────────────────────────────────────────
        Route::get('/friends/search', 'search');
    });

    // ── Block / Unblock ───────────────────────────────────────────────────────
    Route::controller(BlockController::class)->group(function () {
        Route::get('/friends/blocked', 'index');
        Route::post('/users/{id}/block', 'block');
        Route::delete('/users/{id}/block', 'unblock');
    });

    // ── Chat: one-on-one messaging with connected friends ────────────────────
    Route::controller(ChatController::class)->prefix('chat')->group(function () {
        Route::post('/send-message', 'sendMessage');
        Route::get('/conversations/{conversation}', 'conversation');
        Route::get('/recent', 'recent');
    });

    // "All Friend" for chat search reuses the existing connected-friends list.
    Route::get('/chat/friends', [FriendController::class, 'connected']);

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
});
