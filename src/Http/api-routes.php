<?php

use Illuminate\Support\Facades\Route;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\AuthController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\DealController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\IndustryController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\InvoiceController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\LabelController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\LeadController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\LeadSourceController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\OrderController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\OrganizationController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\OrganizationTypeController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\PersonController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\PipelineStageController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\ProductCategoryController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\ProductController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\QuoteController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\TaxRateController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\TimezoneController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\UserController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\AttendanceController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\VisitController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\DeviceTokenController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\NotificationController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\WorkLocationController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\TaskController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\ActivityController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\DashboardController;

/*
 * Laravel CRM API routes (v2).
 *
 * This file is loaded by LaravelCrmServiceProvider::registerRoutes() under
 * the `crm/api/v2` prefix with the `api`, `laravel-crm.api.json`, and
 * `throttle:laravel-crm-api` middleware applied. Authenticated routes layer
 * `auth:sanctum`, `crm-api`, and `laravel-crm.api.team` on top.
 */

Route::post('auth/token', [AuthController::class, 'issueToken'])
    ->middleware('throttle:6,1')
    ->name('laravel-crm.api.v2.auth.token.issue');

Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

Route::middleware(['auth:sanctum', 'crm-api', 'laravel-crm.api.team', 'laravel-crm.tenant'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])
        ->name('laravel-crm.api.v2.auth.me');

    Route::delete('auth/token', [AuthController::class, 'revokeToken'])
        ->name('laravel-crm.api.v2.auth.token.revoke');

    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/activities', [ActivityController::class, 'store']);

    Route::apiResource('leads', LeadController::class)
        ->names('laravel-crm.api.v2.leads')
        ->scoped(['lead' => 'external_id']);

    Route::post('leads/{lead:external_id}/convert', [LeadController::class, 'convert'])
        ->name('laravel-crm.api.v2.leads.convert');

    Route::apiResource('work-locations', WorkLocationController::class)
        ->except(['show'])
        ->parameters(['work-locations' => 'id'])
        ->names('laravel-crm.api.v2.work-locations');

    Route::get('attendances/schedule', [AttendanceController::class, 'schedule'])
        ->name('laravel-crm.api.v2.attendances.schedule');

    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('laravel-crm.api.v2.notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('laravel-crm.api.v2.notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])
        ->name('laravel-crm.api.v2.notifications.read');

    Route::post('device-tokens', [DeviceTokenController::class, 'store'])
        ->name('laravel-crm.api.v2.device-tokens.store');
    Route::delete('device-tokens', [DeviceTokenController::class, 'destroy'])
        ->name('laravel-crm.api.v2.device-tokens.destroy');

    Route::get('attendances/shift', [AttendanceController::class, 'shift'])
        ->name('laravel-crm.api.v2.attendances.shift');

    Route::get('attendances/today', [AttendanceController::class, 'today'])
    ->name('laravel-crm.api.v2.attendances.today');

    Route::apiResource('attendances', AttendanceController::class)
    ->except(['update'])
    ->names('laravel-crm.api.v2.attendances')
    ->scoped(['attendance' => 'external_id']);

    Route::post('visits/{visit:external_id}/checkin', [VisitController::class, 'checkin'])
    ->name('laravel-crm.api.v2.visits.checkin');

    Route::apiResource('visits', VisitController::class)
        ->except(['update'])
        ->names('laravel-crm.api.v2.visits')
        ->scoped(['visit' => 'external_id']);

    Route::apiResource('products', ProductController::class)
        ->names('laravel-crm.api.v2.products')
        ->scoped(['product' => 'external_id']);

    Route::apiResource('organizations', OrganizationController::class)
        ->names('laravel-crm.api.v2.organizations')
        ->scoped(['organization' => 'external_id']);

    Route::apiResource('people', PersonController::class)
        ->parameters(['people' => 'person'])
        ->names('laravel-crm.api.v2.people')
        ->scoped(['person' => 'external_id']);

    Route::apiResource('deals', DealController::class)
        ->names('laravel-crm.api.v2.deals')
        ->scoped(['deal' => 'external_id']);

    Route::post('deals/{deal:external_id}/won', [DealController::class, 'won'])
        ->name('laravel-crm.api.v2.deals.won');
    Route::post('deals/{deal:external_id}/lost', [DealController::class, 'lost'])
        ->name('laravel-crm.api.v2.deals.lost');
    Route::post('deals/{deal:external_id}/reopen', [DealController::class, 'reopen'])
        ->name('laravel-crm.api.v2.deals.reopen');

    Route::apiResource('quotes', QuoteController::class)
        ->names('laravel-crm.api.v2.quotes')
        ->scoped(['quote' => 'external_id']);

    Route::post('quotes/{quote:external_id}/accept', [QuoteController::class, 'accept'])
        ->name('laravel-crm.api.v2.quotes.accept');
    Route::post('quotes/{quote:external_id}/reject', [QuoteController::class, 'reject'])
        ->name('laravel-crm.api.v2.quotes.reject');
    Route::post('quotes/{quote:external_id}/unaccept', [QuoteController::class, 'unaccept'])
        ->name('laravel-crm.api.v2.quotes.unaccept');
    Route::post('quotes/{quote:external_id}/unreject', [QuoteController::class, 'unreject'])
        ->name('laravel-crm.api.v2.quotes.unreject');
    Route::post('quotes/{quote:external_id}/send', [QuoteController::class, 'send'])
        ->name('laravel-crm.api.v2.quotes.send');

    Route::apiResource('orders', OrderController::class)
        ->names('laravel-crm.api.v2.orders')
        ->scoped(['order' => 'external_id']);

    Route::post('orders/{order:external_id}/convert-to-invoice', [OrderController::class, 'convertToInvoice'])
        ->name('laravel-crm.api.v2.orders.convert-to-invoice');

    Route::apiResource('invoices', InvoiceController::class)
        ->names('laravel-crm.api.v2.invoices')
        ->scoped(['invoice' => 'external_id']);

    Route::post('invoices/{invoice:external_id}/pay', [InvoiceController::class, 'pay'])
        ->name('laravel-crm.api.v2.invoices.pay');
    Route::post('invoices/{invoice:external_id}/send', [InvoiceController::class, 'send'])
        ->name('laravel-crm.api.v2.invoices.send');

    // --- Read-only lookup lists, dipakai buat isi dropdown/picker di client
    // (mobile app, dsb) yang sebelumnya cuma bisa diisi lewat Livewire web UI. ---
    Route::get('labels', [LabelController::class, 'index'])
        ->name('laravel-crm.api.v2.labels.index');

    Route::get('product-categories', [ProductCategoryController::class, 'index'])
        ->name('laravel-crm.api.v2.product-categories.index');

    Route::get('tax-rates', [TaxRateController::class, 'index'])
        ->name('laravel-crm.api.v2.tax-rates.index');

    Route::get('organization-types', [OrganizationTypeController::class, 'index'])
        ->name('laravel-crm.api.v2.organization-types.index');

    Route::get('industries', [IndustryController::class, 'index'])
        ->name('laravel-crm.api.v2.industries.index');

    Route::get('timezones', [TimezoneController::class, 'index'])
        ->name('laravel-crm.api.v2.timezones.index');

    Route::get('pipeline-stages', [PipelineStageController::class, 'index'])
        ->name('laravel-crm.api.v2.pipeline-stages.index');

    Route::get('lead-sources', [LeadSourceController::class, 'index'])
        ->name('laravel-crm.api.v2.lead-sources.index');

    Route::get('users', [UserController::class, 'index'])
        ->name('laravel-crm.api.v2.users.index');
});
