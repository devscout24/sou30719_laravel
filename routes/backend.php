<?php

use App\Http\Controllers\Web\Backend\AdminUserController;
use App\Http\Controllers\Web\Backend\DashboardController;
use App\Http\Controllers\Web\Backend\DynamicPageController;
use App\Http\Controllers\Web\Backend\SystemController;
use App\Http\Controllers\Web\Backend\UserManagementController;
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
    Route::get('/user-lists/{user}', 'show')->name('admin.user.show');

    Route::get('/user-lists/{user}/edit', 'edit')->name('admin.user.edit');
    Route::post('/user-lists/{user}', 'update')->name('admin.user.update');

    Route::post('/user-lists/{user}/status', 'updateUserStatus')->name('admin.user.status.update');
    Route::post('/user-lists/{user}/role', 'updateUserRole')->name('admin.user.role.update');

    Route::get('/create-user', 'create')->name('admin.user.create');
    Route::post('/admin/user/store', 'store')->name('admin.user.store');
});
