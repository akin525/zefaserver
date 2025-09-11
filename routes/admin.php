<?php


use App\Http\Controllers\Api\Admin\AdminAllOverview;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminConfigController;
use App\Http\Controllers\Api\Admin\AdminFundController;
use Illuminate\Support\Facades\Route;


Route::prefix('admin')->group(function () {
    // Public routes
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('password/request', [AdminAuthController::class, 'requestPasswordReset']);
    Route::post('password/reset', [AdminAuthController::class, 'resetPassword']);

    // Protected admin routes
    Route::middleware(['admin.auth'])->group(function () {
        // Basic authenticated routes
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::get('profile', [AdminAuthController::class, 'profile'])->name('admin.profile');
        Route::post('password/change-temp', [AdminAuthController::class, 'changeTempPassword'])->name('admin.password.change-temp');

        Route::get('dashboard/overview', [AdminAllOverview::class, 'overview']);
        Route::get('dashboard/metrics/{metric}', [AdminAllOverview::class, 'getMetricStats']);
        Route::get('dashboard/export', [AdminAllOverview::class, 'exportDashboard']);
        Route::get('admin-role',[AdminConfigController::class, 'getRoles']);
        Route::get('admin-permission',[AdminConfigController::class, 'getPermissions']);
        Route::get('admin-department',[AdminConfigController::class, 'getDepartments']);
        Route::get('admin-department/{id}',[AdminConfigController::class, 'getDepartmentDetails']);
        Route::get('system-config',[AdminConfigController::class, 'getSystemConfig']);

        // Routes with specific permissions
        Route::middleware(['admin.permission:manage_admin'])->group(function () {
            Route::get('admins', [AdminAuthController::class, 'admins']);
        });

        Route::middleware(['admin.permission:view_user'])->group(function () {


        });

        Route::middleware(['admin.permission:manage_user'])->group(function () {


        });

        // High-risk operations
        Route::middleware(['admin.role:super_admin', 'admin.permission:manage_admin'])->group(function () {
            Route::post('credit-wallet', [AdminFundController::class, 'fundUserWallet']);
            Route::post('create', [AdminAuthController::class, 'createAdmin']);
            Route::post('create-department', [AdminConfigController::class, 'createDepartment']);
        });
    });
});
