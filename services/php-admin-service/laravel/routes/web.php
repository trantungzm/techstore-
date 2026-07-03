<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\JwtMiddleware;

// Public
Route::get('/api/banners/active', [BannerController::class, 'active']);

// Admin routes (use class middleware to validate JWT and attach claims)
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::get('/api/banners', [BannerController::class, 'index']);
    Route::post('/api/banners', [BannerController::class, 'store']);
    Route::get('/api/banners/{id}', [BannerController::class, 'show']);
    Route::put('/api/banners/{id}', [BannerController::class, 'update']);
    Route::delete('/api/banners/{id}', [BannerController::class, 'destroy']);
    Route::put('/api/banners/{id}/toggle', [BannerController::class, 'toggle']);

    Route::get('/api/settings', [SettingsController::class, 'get']);
    Route::put('/api/settings', [SettingsController::class, 'update']);
    Route::get('/api/settings/pickup-branches', [SettingsController::class, 'pickupBranches']);

    Route::get('/api/admin/notification-templates', [NotificationController::class, 'templates']);
    Route::post('/api/admin/notification-templates', [NotificationController::class, 'createTemplate']);
    Route::put('/api/admin/notification-templates/{id}', [NotificationController::class, 'updateTemplate']);
    Route::delete('/api/admin/notification-templates/{id}', [NotificationController::class, 'deleteTemplate']);

    Route::get('/api/admin/notifications/campaigns', [NotificationController::class, 'campaigns']);
    Route::post('/api/admin/notifications/campaigns', [NotificationController::class, 'createCampaign']);
});
