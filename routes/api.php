<?php

use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook/{random_string}', [\App\Http\Controllers\Api\TelegramApiController::class, 'webhook']);
Route::post('/paydisini/webhook/response', [\App\Http\Controllers\Api\PaydisiniController::class, 'webhook']);
