<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\NumberController;
use App\Http\Controllers\Api\PaymentController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, API!']);
});


Route::get('/users', [UserController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/submitaccountsdetails', [AccountController::class, 'submit']);
Route::get('/AccountsDetailsByDate/{user_id}/{date}', [AccountController::class, 'accountsByDate']);
Route::get('/RecentAccounts/{user_id}', [AccountController::class, 'recentAccounts']);
Route::get('/AllAccountsByUser/{user_id}', [AccountController::class, 'allAccountsByUser']);
Route::post('/upload-file', [FileController::class, 'upload']);
Route::post('/number-to-words', [NumberController::class, 'numberToWords']);

// Payment Routes for Cashfree Integration
Route::post('/payment/create', [PaymentController::class, 'createPayment']);
Route::get('/payment/status', [PaymentController::class, 'getPaymentStatus']);
Route::get('/payment/user-payments', [PaymentController::class, 'getUserPayments']);
Route::post('/payment/refund', [PaymentController::class, 'refundPayment']);

// Payment Callback Routes (these should be accessible without authentication)
Route::get('/payment/callback', [PaymentController::class, 'handleCallback'])->name('payment.callback');
Route::post('/payment/webhook', [PaymentController::class, 'handleWebhook'])->name('payment.webhook');

// Test route for payment system
Route::get('/payment/test', function () {
    return response()->json([
        'message' => 'Cashfree Payment System is ready!',
        'environment' => env('CASHFREE_ENVIRONMENT', 'not configured'),
        'app_id_configured' => !empty(env('CASHFREE_APP_ID')),
        'secret_configured' => !empty(env('CASHFREE_SECRET_KEY')),
        'timestamp' => now()
    ]);
});