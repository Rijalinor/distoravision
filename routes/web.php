<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionalController;
use App\Http\Controllers\SalesmanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Import
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('/imports/create', [ImportController::class, 'create'])->name('imports.create');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::delete('/imports/{import}', [ImportController::class, 'destroy'])->name('imports.destroy');

    // Salesmen
    Route::get('/salesmen', [SalesmanController::class, 'index'])->name('salesmen.index');
    Route::get('/salesmen/{salesman}', [SalesmanController::class, 'show'])->name('salesmen.show');

    // Outlets
    Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
    Route::get('/outlets/{outlet}', [OutletController::class, 'show'])->name('outlets.show');

    // Principals
    Route::get('/principals', [PrincipalController::class, 'index'])->name('principals.index');
    Route::get('/principals/{principal}', [PrincipalController::class, 'show'])->name('principals.show');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');

    // Regional
    Route::get('/regional', [RegionalController::class, 'index'])->name('regional.index');

    // Advanced Analytics
    Route::get('/analytics/pareto', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'pareto'])->name('analytics.pareto');
    Route::get('/analytics/sleeping-outlets', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'sleepingOutlets'])->name('analytics.sleeping-outlets');
    Route::get('/analytics/discount', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'discountEffectiveness'])->name('analytics.discount');
    Route::get('/analytics/rfm', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'rfmAnalysis'])->name('analytics.rfm');
    Route::get('/analytics/cross-selling', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'crossSelling'])->name('analytics.cross-selling');
    Route::get('/analytics/margin', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'marginAnalysis'])->name('analytics.margin');
    Route::get('/analytics/target-tracker', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'targetTracker'])->name('analytics.target-tracker');
    Route::post('/analytics/target-tracker/save', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'saveTargets'])->name('analytics.save-targets');
    Route::get('/analytics/cohort', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'cohortAnalysis'])->name('analytics.cohort');
    Route::get('/analytics/report', [\App\Http\Controllers\AdvancedAnalyticsController::class, 'generateReport'])->name('analytics.report');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
