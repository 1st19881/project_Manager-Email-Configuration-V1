<?php

use App\Http\Controllers\Api\V1\MailTemplateController;
use App\Http\Controllers\Api\V1\MailTestController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationLogController;
use App\Http\Controllers\Api\V1\PlantController;
use App\Http\Controllers\Api\V1\TrcMailNotiMgrController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('notifications', [NotificationController::class, 'store'])
        ->middleware('notification.api-key')
        ->name('notifications.store');

    Route::get('plants', [PlantController::class, 'index'])->name('plants.index');

    Route::post('mail/test', [MailTestController::class, 'store'])->name('mail.test');

    Route::get('mail-templates', [MailTemplateController::class, 'index'])->name('mail-templates.index');

    Route::get('notification-logs/summary', [NotificationLogController::class, 'summary'])
        ->name('notification-logs.summary');
    Route::get('notification-logs', [NotificationLogController::class, 'index'])
        ->name('notification-logs.index');

    Route::get('mail-notification-managers-import/template', [TrcMailNotiMgrController::class, 'downloadImportTemplate'])
        ->name('mail-notification-managers.import-template');
    Route::post('mail-notification-managers-import', [TrcMailNotiMgrController::class, 'import'])
        ->name('mail-notification-managers.import');

    Route::apiResource('mail-notification-managers', TrcMailNotiMgrController::class)
        ->parameters(['mail-notification-managers' => 'mailNotificationManager']);
});
