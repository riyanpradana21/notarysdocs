<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

// Main Dashboard Landing (Like iLovePDF, visitors directly see the workspace/dashboard)
Route::get('/', [DocumentController::class, 'index'])->name('dashboard');
Route::get('/dashboard', function () {
    return redirect()->route('dashboard');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/login/demo', [AuthController::class, 'demoLogin'])->name('login.demo');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Google OAuth Routes
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('login.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('login.google.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Document Operations (Accessible to guests & authenticated users, authorized via DocumentPolicy)
Route::get('/documents', [DocumentController::class, 'history'])->name('documents.history');
Route::get('/documents/upload', [DocumentController::class, 'create'])->name('documents.upload');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');

Route::prefix('documents/{document}')->group(function () {
    Route::get('/editor', [DocumentController::class, 'editor'])->name('documents.editor');
    Route::get('/stream', [DocumentController::class, 'streamPdf'])->name('documents.stream');
    Route::get('/stream-final', [DocumentController::class, 'streamFinalPdf'])->name('documents.stream-final');
    Route::get('/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::patch('/config', [DocumentController::class, 'updateConfig'])->name('documents.config');
    Route::post('/generate', [DocumentController::class, 'generate'])->name('documents.generate');
    Route::post('/retry', [DocumentController::class, 'retry'])->name('documents.retry');
    Route::delete('/', [DocumentController::class, 'destroy'])->name('documents.destroy');
});
