<?php

use App\Http\Controllers\AmoWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/amocrm/{secret}', AmoWebhookController::class)->name('amocrm.webhook');
