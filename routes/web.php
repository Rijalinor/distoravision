<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdvancedAnalyticsController;
use App\Http\Controllers\ArAnalyticsController;
use App\Http\Controllers\ArImportController;
use App\Http\Controllers\ColumnMappingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoModeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionalController;
use App\Http\Controllers\SalesmanController;
use App\Http\Controllers\SalesmanDashboardController;
use App\Http\Controllers\TvDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-dashboard', [SalesmanDashboardController::class, 'index'])->name('salesman.dashboard');
    Route::post('/demo-mode/toggle', [DemoModeController::class, 'toggle'])->name('demo-mode.toggle');

    // ══════════════════════════════════════════════════════════════
    // ADMIN ONLY — Import, Settings, Tutup Buku
    // ══════════════════════════════════════════════════════════════
    Route::middleware([AdminMiddleware::class])->group(function () {
        // Import Sales
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/create', [ImportController::class, 'create'])->name('imports.create');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::delete('/imports/{import}', [ImportController::class, 'destroy'])->name('imports.destroy');

        // Import AR
        Route::get('/ar/imports', [ArImportController::class, 'index'])->name('ar.imports.index');
        Route::get('/ar/imports/create', [ArImportController::class, 'create'])->name('ar.imports.create');
        Route::post('/ar/imports', [ArImportController::class, 'store'])->name('ar.imports.store');
        Route::delete('/ar/imports/{arImportLog}', [ArImportController::class, 'destroy'])->name('ar.imports.destroy');

        // Settings
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/settings/column-mapping', [ColumnMappingController::class, 'edit'])->name('settings.column-mapping');
        Route::put('/settings/column-mapping', [ColumnMappingController::class, 'update'])->name('settings.column-mapping.update');
        Route::get('/settings/activity-logs', [ActivityLogController::class, 'index'])->name('settings.activity-logs');

        // Tutup Buku (Period Management)
        Route::get('/periods', [PeriodController::class, 'index'])->name('periods.index');
        Route::post('/periods/{period}/close', [PeriodController::class, 'close'])->name('periods.close');
        Route::post('/periods/{period}/reopen', [PeriodController::class, 'reopen'])->name('periods.reopen');
        Route::get('/periods/{period}', [PeriodController::class, 'show'])->name('periods.show');

        // Admin-only Analytics
        Route::get('/analytics/margin', [AdvancedAnalyticsController::class, 'marginAnalysis'])->name('analytics.margin');
        Route::get('/analytics/report', [AdvancedAnalyticsController::class, 'generateReport'])->name('analytics.report');
        Route::post('/analytics/target-tracker/save', [AdvancedAnalyticsController::class, 'saveTargets'])->name('analytics.save-targets');
    });

    // ══════════════════════════════════════════════════════════════
    // ALL AUTHENTICATED USERS (with ACL scoping in models)
    // ══════════════════════════════════════════════════════════════

    // AR Dashboard (read-only, scoped by ACL)
    Route::get('/ar/dashboard', [ArAnalyticsController::class, 'dashboard'])->name('ar.dashboard');

    // Salesmen
    Route::get('/salesmen', [SalesmanController::class, 'index'])->name('salesmen.index');
    Route::get('/salesmen/{salesman}', [SalesmanController::class, 'show'])->name('salesmen.show');

    // Outlets
    Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
    Route::get('/outlets/{outlet}', [OutletController::class, 'show'])->name('outlets.show');

    // Principals (blocked for salesman role — handled in controller)
    Route::get('/principals', [PrincipalController::class, 'index'])->name('principals.index');
    Route::get('/principals/{principal}', [PrincipalController::class, 'show'])->name('principals.show');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');

    // Regional
    Route::get('/regional', [RegionalController::class, 'index'])->name('regional.index');

    // Advanced Analytics (open to all roles)
    Route::get('/analytics/pareto', [AdvancedAnalyticsController::class, 'pareto'])->name('analytics.pareto');
    Route::get('/analytics/sleeping-outlets', [AdvancedAnalyticsController::class, 'sleepingOutlets'])->name('analytics.sleeping-outlets');
    Route::get('/analytics/discount', [AdvancedAnalyticsController::class, 'discountEffectiveness'])->name('analytics.discount');
    Route::get('/analytics/rfm', [AdvancedAnalyticsController::class, 'rfmAnalysis'])->name('analytics.rfm');
    Route::get('/analytics/cross-selling', [AdvancedAnalyticsController::class, 'crossSelling'])->name('analytics.cross-selling');
    Route::get('/analytics/target-tracker', [AdvancedAnalyticsController::class, 'targetTracker'])->name('analytics.target-tracker');
    Route::get('/analytics/cohort', [AdvancedAnalyticsController::class, 'cohortAnalysis'])->name('analytics.cohort');
    Route::get('/analytics/promo-uplift', [AdvancedAnalyticsController::class, 'promoUplift'])->name('analytics.promo-uplift');

    // TV Dashboard Wallboard
    Route::get('/tv-dashboard', [TvDashboardController::class, 'index'])->name('tv.dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
