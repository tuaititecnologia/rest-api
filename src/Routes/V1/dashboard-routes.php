<?php

use Illuminate\Support\Facades\Route;
use Webkul\RestApi\Http\Controllers\V1\Dashboard\DashboardController;

Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'dashboard'], function () {
    Route::get('stats', [DashboardController::class, 'stats']);
});