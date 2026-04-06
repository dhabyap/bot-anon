<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Webhook endpoint dari Telegram
Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook']);

// Helper endpoint untuk mengatur setWebhook dengan mudah
Route::get('/telegram/status', [TelegramController::class, 'status']);
Route::get('/telegram/set-webhook', [TelegramController::class, 'setWebhook']);
