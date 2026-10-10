<?php

// network module — SUPER ADMIN pages — required inside Route::middleware('super')->group in routes/web.php.

use Illuminate\Support\Facades\Route;

Route::view('/admin/referrals', 'platform-referrals')->name('admin.referrals');
