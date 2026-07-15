<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

// Public Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Database Sync Routes (Local-only, CORS enabled)
Route::get('/api/database/pending-queries', [SettingController::class, 'getPendingQueries'])->name('api.database.pending-queries');
Route::post('/api/database/clear-pending-queries', [SettingController::class, 'clearPendingQueries'])->name('api.database.clear-pending-queries');
Route::options('/api/database/pending-queries', function() {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
});
Route::options('/api/database/clear-pending-queries', function() {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
});

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    // Redirect root to dashboard
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');

    // Super Admin Only Routes
    Route::middleware(['role:super_admin'])->group(function () {
        // Watchlist Routes
        Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
        Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
        Route::delete('/watchlist/{id}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

        // Jurnal Trading Routes
        Route::get('/trade', [TradeController::class, 'index'])->name('trade.index');
        Route::post('/trade', [TradeController::class, 'store'])->name('trade.store');
        Route::put('/trade/{id}', [TradeController::class, 'update'])->name('trade.update');
        Route::delete('/trade/{id}', [TradeController::class, 'destroy'])->name('trade.destroy');

        // User Management Routes
        Route::get('/user', [UserController::class, 'index'])->name('user.index');
        Route::post('/user', [UserController::class, 'store'])->name('user.store');
        Route::put('/user/{id}', [UserController::class, 'update'])->name('user.update');
        Route::delete('/user/{id}', [UserController::class, 'destroy'])->name('user.destroy');

        // Settings Routes
        Route::get('/setting', [SettingController::class, 'index'])->name('setting.index');
        Route::post('/setting', [SettingController::class, 'update'])->name('setting.update');
        Route::get('/setting/database/export', [SettingController::class, 'exportDatabase'])->name('setting.database.export');
        Route::post('/setting/database/import', [SettingController::class, 'importDatabase'])->name('setting.database.import');

        // Scanner Routes
        Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');

        // Database Sync Routes (Browser-Bridge)
        Route::post('/api/database/apply-queries', [SettingController::class, 'applyPendingQueries'])->name('api.database.apply-queries');

        // Async JSON API routes
        Route::get('/api/watchlist-metrics/{symbol}', [WatchlistController::class, 'getMetrics'])->name('api.watchlist-metrics');
        Route::get('/api/trade-live-stats/{symbol}', [TradeController::class, 'getLiveStats'])->name('api.trade-live-stats');
        Route::get('/api/scanner/results', [ScannerController::class, 'getResults'])->name('api.scanner.results');
        Route::get('/api/scanner/all', [ScannerController::class, 'getAllResults'])->name('api.scanner.all');
        Route::post('/api/scanner/trigger', [ScannerController::class, 'startScan'])->name('api.scanner.trigger');
    });
});
