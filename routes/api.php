<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StopReportController;
use App\Http\Controllers\Api\ZoneVisitReportController;

Route::get('/reports/stops', [StopReportController::class, 'index']);

Route::get('/reports/zone-visits', [
    ZoneVisitReportController::class,
    'index'
]);
