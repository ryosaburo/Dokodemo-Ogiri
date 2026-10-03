<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DevController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home', ['devUsers' => app()->environment('local') ? \App\Models\User::orderBy('id')->get(['id', 'name']) : collect()]))->name('home');
// Breeze のログイン後リダイレクト先(dashboard)はホームへ寄せる
Route::get('/dashboard', fn () => redirect()->route('home'))->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::post('/rooms/join', [RoomController::class, 'join'])->name('rooms.join');
    Route::get('/rooms/{code}', [RoomController::class, 'show'])->name('rooms.show');
    Route::get('/rooms/{code}/state', [RoomController::class, 'state']);

    Route::get('/rooms/{code}/odai-candidates', [RoomController::class, 'odaiCandidates']);
    Route::post('/rooms/{code}/rounds', [RoomController::class, 'startRound']);
    Route::post('/rooms/{code}/close-answers', [RoomController::class, 'closeAnswers']);
    Route::post('/rooms/{code}/reveal-next', [RoomController::class, 'revealNext']);
    Route::post('/rooms/{code}/volume', [RoomController::class, 'volume']);
    Route::post('/rooms/{code}/finish-round', [RoomController::class, 'finishRound']);
    Route::post('/rooms/{code}/answer', [RoomController::class, 'answer']);
    Route::post('/rooms/{code}/vote', [RoomController::class, 'vote']);
});

if (app()->environment('local')) {
    Route::post('/dev/users', [DevController::class, 'createUsers'])->name('dev.users');
}

require __DIR__.'/auth.php';
