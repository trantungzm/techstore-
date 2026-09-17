<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\JwtMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/banners/active', [BannerController::class, 'active']);

Route::middleware([JwtMiddleware::class])->group(function () {
    Route::get('/banners', [BannerController::class, 'index']);
    Route::post('/banners', [BannerController::class, 'store']);
    Route::get('/banners/{id}', [BannerController::class, 'show']);
    Route::put('/banners/{id}', [BannerController::class, 'update']);
    Route::delete('/banners/{id}', [BannerController::class, 'destroy']);
    Route::put('/banners/{id}/toggle', [BannerController::class, 'toggle']);

    Route::get('/settings', [SettingsController::class, 'get']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/settings/pickup-branches', [SettingsController::class, 'pickupBranches']);

    Route::get('/admin/notification-templates', [NotificationController::class, 'templates']);
    Route::post('/admin/notification-templates', [NotificationController::class, 'createTemplate']);
    Route::put('/admin/notification-templates/{id}', [NotificationController::class, 'updateTemplate']);
    Route::delete('/admin/notification-templates/{id}', [NotificationController::class, 'deleteTemplate']);

    Route::get('/admin/notifications/campaigns', [NotificationController::class, 'campaigns']);
    Route::post('/admin/notifications/campaigns', [NotificationController::class, 'createCampaign']);
});
