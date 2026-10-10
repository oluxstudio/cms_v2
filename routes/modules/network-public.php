<?php

// network module — PUBLIC web routes — loaded BEFORE the /preview/{siteName}/{pageUrl} catch-all; no auth.
// The customer's consent link ("may we pass your details to X?").

use App\Modules\Network\Http\ConsentController;
use Illuminate\Support\Facades\Route;

Route::get('/referral/{token}', [ConsentController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{40,64}')->name('network.consent.show');
Route::post('/referral/{token}', [ConsentController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:leads')->name('network.consent.store');
