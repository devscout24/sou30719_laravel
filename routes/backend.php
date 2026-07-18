<?php

use App\Http\Controllers\Web\Backend\AdminUserController;
use App\Http\Controllers\Web\Backend\DashboardController;
use App\Http\Controllers\Web\Backend\DynamicPageController;
use App\Http\Controllers\Web\Backend\SystemController;
use App\Http\Controllers\Web\Backend\UserManagementController;
use App\Http\Controllers\Web\Backend\WorkspaceController;
use App\Http\Controllers\Web\Backend\SubscriptionPlanController;
use App\Http\Controllers\Web\Backend\FeedTopicController;
use App\Http\Controllers\Web\Backend\PolicyController;
use App\Http\Controllers\Web\Backend\SupportTicketController;
use App\Http\Controllers\Web\Backend\HelpSupportController;
use App\Http\Controllers\Web\Backend\PostReportController;
use App\Http\Controllers\Web\Backend\PostController;
use App\Http\Controllers\Web\Backend\BillingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;


Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return redirect()->back()->with('success', 'Cache cleared successfully.');
})->name('cache.clear');


// Dashboard
Route::controller(DashboardController::class)->group(function () {
    Route::get('/dashboard', 'index')->name('dashboard');
});

Route::controller(SystemController::class)->group(function () {
    Route::get('/system-settings', 'systemSettings')->name('system.settings');
    Route::post('/system-settings-update', 'systemSettingsUpdate')->name('system.settings.update');

    Route::get('/credential-settings/{type}', 'credentialSettings')->name('system.settings.credential');
    Route::post('/credential-settings-update', 'credentialSettingsUpdate')->name('system.settings.credential.update');
});

Route::controller(DynamicPageController::class)->group(function () {
    Route::get('/dynamic-pages', 'index')->name('dynamic.pages');
    Route::get('/dynamic-pages/{page}', 'show')->name('dynamic.pages.show');
    Route::get('/dynamic-pages/{page}/edit', 'edit')->name('dynamic.pages.edit');
    Route::put('/dynamic-pages/{page}', 'update')->name('dynamic.pages.update');
    Route::post('/dynamic-pages/{page}/status', 'updateStatus')->name('dynamic.pages.status');
});


Route::controller(AdminUserController::class)->group(function () {
    Route::get('/profile', 'profile')->name('admin.user.profile');
    Route::get('/edit-profile', 'editProfile')->name('admin.user.profile.edit');
    Route::post('/profile/update', 'updateProfile')->name('admin.user.profile.update');
    Route::get('/email-change/{token}', 'showEmailChangeForm')->name('email.change.verify');
    Route::post('/email-change/confirm', 'confirmEmailChange')->name('email.change.confirm');
});


Route::controller(UserManagementController::class)->group(function () {
    Route::get('admin/users/data', 'data')->name('admin.user.data');

    Route::get('/user-lists', 'index')->name('admin.user.lists');
    Route::get('/user-lists-export', 'export')->name('admin.user.export');
    Route::post('/user-lists/bulk-delete', 'bulkDestroy')->name('admin.user.bulk-delete');

    Route::get('/user-lists/{user}', 'show')->name('admin.user.show');

    Route::get('/user-lists/{user}/edit', 'edit')->name('admin.user.edit');
    Route::post('/user-lists/{user}', 'update')->name('admin.user.update');
    Route::delete('/user-lists/{user}', 'destroy')->name('admin.user.destroy');

    Route::post('/user-lists/{user}/status', 'updateUserStatus')->name('admin.user.status.update');
    Route::post('/user-lists/{user}/role', 'updateUserRole')->name('admin.user.role.update');

    Route::get('/create-user', 'create')->name('admin.user.create');
    Route::post('/admin/user/store', 'store')->name('admin.user.store');
});


// Workspaces
Route::controller(WorkspaceController::class)->prefix('workspaces')->group(function () {
    Route::get('/', 'index')->name('admin.workspaces.index');
    Route::get('/create', 'create')->name('admin.workspaces.create');
    Route::post('/', 'store')->name('admin.workspaces.store');
    Route::get('/{workspace}/edit', 'edit')->name('admin.workspaces.edit');
    Route::put('/{workspace}', 'update')->name('admin.workspaces.update');
    Route::delete('/{workspace}', 'destroy')->name('admin.workspaces.destroy');
    Route::post('/{workspace}/status', 'updateStatus')->name('admin.workspaces.status');
});


// Subscription Plans
Route::controller(SubscriptionPlanController::class)->prefix('subscription-plans')->group(function () {
    Route::get('/', 'index')->name('admin.plans.index');
    Route::get('/create', 'create')->name('admin.plans.create');
    Route::post('/', 'store')->name('admin.plans.store');
    Route::get('/{plan}/edit', 'edit')->name('admin.plans.edit');
    Route::put('/{plan}', 'update')->name('admin.plans.update');
    Route::delete('/{plan}', 'destroy')->name('admin.plans.destroy');
    Route::post('/{plan}/status', 'updateStatus')->name('admin.plans.status');
});


// Fixed Feed Topics
Route::controller(FeedTopicController::class)->prefix('feed-topics')->group(function () {
    Route::get('/', 'index')->name('admin.feed-topics.index');
    Route::get('/create', 'create')->name('admin.feed-topics.create');
    Route::post('/', 'store')->name('admin.feed-topics.store');
    Route::get('/{feedTopic}/edit', 'edit')->name('admin.feed-topics.edit');
    Route::put('/{feedTopic}', 'update')->name('admin.feed-topics.update');
    Route::delete('/{feedTopic}', 'destroy')->name('admin.feed-topics.destroy');
    Route::post('/{feedTopic}/status', 'updateStatus')->name('admin.feed-topics.status');
});


// Policies / Disclaimers
Route::controller(PolicyController::class)->group(function () {
    Route::get('/policies/disclaimers', 'edit')->name('admin.policies.edit');
    Route::put('/policies/disclaimers', 'update')->name('admin.policies.update');
});


// Support Tickets
Route::controller(SupportTicketController::class)->prefix('support-tickets')->group(function () {
    Route::get('/', 'index')->name('admin.support-tickets.index');
    Route::get('/export', 'export')->name('admin.support-tickets.export');
    Route::get('/{supportTicket}', 'show')->name('admin.support-tickets.show');
    Route::put('/{supportTicket}/status', 'updateStatus')->name('admin.support-tickets.status');
    Route::post('/{supportTicket}/replies', 'storeReply')->name('admin.support-tickets.replies.store');
});


// Help & Support Messages
Route::controller(HelpSupportController::class)->prefix('help-support')->group(function () {
    Route::get('/', 'index')->name('admin.help-support.index');
    Route::get('/{helpSupport}', 'show')->name('admin.help-support.show');
    Route::delete('/{helpSupport}', 'destroy')->name('admin.help-support.destroy');
});


// Post Reports (moderation queue)
Route::controller(PostReportController::class)->prefix('post-reports')->group(function () {
    Route::get('/data', 'data')->name('admin.post-reports.data');
    Route::get('/', 'index')->name('admin.post-reports.index');
    Route::get('/{postReport}', 'show')->name('admin.post-reports.show');
    Route::put('/{postReport}/reviewed', 'markReviewed')->name('admin.post-reports.reviewed');
    Route::put('/{postReport}/dismissed', 'markDismissed')->name('admin.post-reports.dismissed');
    Route::delete('/{postReport}/post', 'deletePost')->name('admin.post-reports.delete-post');
});


// Posts (content moderation + AI authoring)
Route::controller(PostController::class)->prefix('posts')->group(function () {
    Route::get('/data', 'data')->name('admin.posts.data');
    Route::get('/', 'index')->name('admin.posts.index');
    Route::get('/generate', 'generateForm')->name('admin.posts.generate');
    Route::post('/generate', 'generate')->name('admin.posts.generate.store');
    Route::get('/{post}', 'show')->name('admin.posts.show');
    Route::delete('/{post}', 'destroy')->name('admin.posts.destroy');
});


// Billing: Subscriptions & Payments (read-only reporting)
Route::controller(BillingController::class)->prefix('billing')->group(function () {
    Route::get('/subscriptions/data', 'subscriptionsData')->name('admin.billing.subscriptions.data');
    Route::get('/subscriptions', 'subscriptions')->name('admin.billing.subscriptions');
    Route::get('/payments/data', 'paymentsData')->name('admin.billing.payments.data');
    Route::get('/payments', 'payments')->name('admin.billing.payments');
});
