<?php


use App\Http\Controllers\Api\CashOn\ActivitiesController;
use App\Http\Controllers\Api\CashOn\AuthController;
use App\Http\Controllers\Api\CashOn\IdentityController;
use App\Http\Controllers\Api\CashOn\PaymentMethodController;
use App\Http\Controllers\Api\CashOn\ProfileSettingController;
use App\Http\Controllers\Api\CashOn\SavingsController;
use App\Http\Controllers\Api\CashOn\WalletController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'zefamfb'], function () {
    // Public routes (no middleware)
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [AuthController::class, 'reset']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('/basic-info', [AuthController::class, 'saveBasicInfo']);

    Route::prefix('onboarding')->group(function () {
    Route::middleware(['jwt.auth', 'user.status'])->group(function () {
        Route::post('/verify-identity', [AuthController::class, 'verifyIdentity']);
        Route::post('/device/verify', [AuthController::class, 'verifyDevice']);
        Route::post('/upload-id', [AuthController::class, 'uploadId']);
        Route::post('/upload-scan', [AuthController::class, 'uploadScan']);
        Route::post('/setup-pin', [AuthController::class, 'setupPin']);
    });
});

    // Profile routes (protected by JWT)
    Route::middleware(['jwt.auth', 'user.status'])->prefix('profile')->group(function () {
        Route::get('/next-of-kin', [ProfileSettingController::class, 'getNextOfKin']);
        Route::post('/next-of-kin', [ProfileSettingController::class, 'saveNextOfKin']);

        Route::get('/about-you', [ProfileSettingController::class, 'getProfile']);
        Route::post('/about-you', [ProfileSettingController::class, 'saveProfileInfo']);

        Route::get('/address', [ProfileSettingController::class, 'getAddress']);
        Route::post('/address', [ProfileSettingController::class, 'saveAddress']);

        Route::get('/education', [ProfileSettingController::class, 'getEducation']);
        Route::post('/education', [ProfileSettingController::class, 'saveEducation']);

        Route::get('all-information', [ProfileSettingController::class, 'getAllInfo']);

        Route::get('/bank-account', [PaymentMethodController::class, 'listBankAccounts']);
        Route::post('/bank-account', [PaymentMethodController::class, 'addBankAccount']);
        Route::delete('/bank-account/{id}', [PaymentMethodController::class, 'deleteBankAccount']);
        Route::post('/withdraw', [PaymentMethodController::class, 'withdraw']);

        Route::get('/debit-card', [PaymentMethodController::class, 'listCard']);
        Route::post('/debit-card', [PaymentMethodController::class, 'linkCard']);
        Route::patch('/debit-card/{id}/unlink', [PaymentMethodController::class, 'unlinkCard']);
    });

    // KYC routes (protected by JWT)
    Route::middleware(['jwt.auth', 'user.status'])->prefix('kyc')->group(function () {
        Route::post('verify-id', [IdentityController::class, 'verifyIdentity']);
        Route::post('verify-business', [IdentityController::class, 'verifyBusiness']);
    });

    // Other protected routes
    Route::middleware(['jwt.auth', 'user.status'])->group(function () {
        Route::post('upload-file', [AuthController::class, 'uploadFile']);
        Route::get('/dashboard', [AuthController::class, 'dashboard']);
        Route::get('/history', [IdentityController::class, 'verificationHistory']);

        // Wallet endpoints
        Route::get('/wallets', [WalletController::class, 'index']);
        Route::get('/virtual-accounts', [WalletController::class, 'virtualAccounts']);

        // Savings endpoints
        Route::post('/savings', [SavingsController::class, 'store']);
        Route::get('/savings/interests', [SavingsController::class, 'interests']);
        Route::get('/savings', [SavingsController::class, 'userSavings']);
        Route::get('/savings-list', [SavingsController::class, 'savingsList']);
        Route::get('/savings/{id}', [SavingsController::class, 'savingsDetails']);
        Route::get('/all-activities', [ActivitiesController::class, 'allActivities']);
        Route::get('/activities-detail/{id}', [ActivitiesController::class, 'activityDetails']);
        Route::get('/deposits-list', [ActivitiesController::class, 'getAllUserDeposits']);
        Route::get('/deposits-details/{id}', [ActivitiesController::class, 'getDepositDetails']);
        Route::get('/wallet-transactions', [ActivitiesController::class, 'walletTransactions']);
        Route::get('/wallet-transactions/detail/{id}', [ActivitiesController::class, 'walletTransactionsDetail']);


        // bank list and validation
        Route::prefix('banks')->group(function () {
            Route::get('/', [WalletController::class, 'getBankList']);
            Route::post('/validate-account', [WalletController::class, 'validateAccountName']);
        });

    });
});
