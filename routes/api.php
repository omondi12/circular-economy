<?php

use App\Http\Controllers\Agent360Controller;
use App\Http\Middleware\EnsureAgent360Key;
use Illuminate\Support\Facades\Route;

/*
| Agent 360 (the RM register shared with Adam AHB Souq), server to server behind its key. Contract: docs/agent360-api.md
*/
Route::prefix('agent360/v1')->middleware([EnsureAgent360Key::class, 'throttle:60,1'])->name('api.agent360.')->group(function () {
    Route::get('agents', [Agent360Controller::class, 'index'])->name('agents.index');
    Route::get('agents/metrics', [Agent360Controller::class, 'metrics'])->name('agents.metrics');
    Route::post('agents', [Agent360Controller::class, 'store'])->name('agents.store');
    Route::post('agents/{id}/deactivate', [Agent360Controller::class, 'deactivate'])->whereNumber('id')->name('agents.deactivate');
    Route::post('agents/{id}/activate', [Agent360Controller::class, 'activate'])->whereNumber('id')->name('agents.activate');
});
