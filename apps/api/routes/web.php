<?php

declare(strict_types=1);

use App\Modules\Auth\Controllers\EmailVerificationLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', EmailVerificationLinkController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
