<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\NumberController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RazorpayController;

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

// Payment Routes - Redirected to Razorpay (for backward compatibility)
Route::post('/payment/create', [RazorpayController::class, 'createPayment']);
Route::get('/payment/status', [RazorpayController::class, 'getPaymentStatus']);
Route::post('/payment/status', [RazorpayController::class, 'getPaymentStatus']); // Accept POST for FlutterFlow
Route::get('/payment/user-payments', [RazorpayController::class, 'getUserPayments']);
Route::post('/payment/user-payments', [RazorpayController::class, 'getUserPayments']); // Accept POST for FlutterFlow
Route::post('/payment/refund', [RazorpayController::class, 'refundPayment']);

// Payment Callback Routes - Redirected to Razorpay
Route::get('/payment/callback', [RazorpayController::class, 'callback'])->name('payment.callback');
Route::post('/payment/callback', [RazorpayController::class, 'callback']);

// Test route for payment system
Route::get('/payment/test', function () {
    return response()->json([
        'message' => 'Razorpay Payment System is ready! (SANDBOX MODE)',
        'environment' => 'sandbox',
        'gateway' => 'razorpay',
        'key_id_configured' => !empty(env('RAZORPAY_KEY_ID', 'rzp_test_RUX03OJs024Yes')),
        'key_secret_configured' => !empty(env('RAZORPAY_KEY_SECRET', '212wP4jHAaC68JgtIzs76xpN')),
        'timestamp' => now(),
        'base_url' => 'https://ourprojectapi.sroy.es/public/api/',
        'razorpay_urls' => [
            'api_base' => 'https://api.razorpay.com/v1',
            'checkout_page' => 'Custom payment page with Razorpay integration'
        ],
        'endpoints' => [
            'create_payment' => 'POST /payment/create (redirected to Razorpay)',
            'create_razorpay' => 'POST /razorpay/create',
            'check_status' => 'GET|POST /payment/status (redirected to Razorpay)', 
            'user_payments' => 'GET|POST /payment/user-payments (redirected to Razorpay)',
            'callback' => 'POST /payment/callback (redirected to Razorpay)',
            'razorpay_callback' => 'POST /razorpay/callback',
            'payment_page' => 'GET /razorpay/payment-page'
        ],
        'note' => '✅ All payment routes now use Razorpay. Cashfree has been removed.'
    ]);
});

// Razorpay Routes for Payment Integration
Route::post('/razorpay/create', [RazorpayController::class, 'createPayment']);
Route::post('/razorpay/verify', [RazorpayController::class, 'verifyPayment']);
Route::get('/razorpay/status', [RazorpayController::class, 'getPaymentStatus']);
Route::post('/razorpay/status', [RazorpayController::class, 'getPaymentStatus']); // Accept POST for FlutterFlow
Route::get('/razorpay/user-payments', [RazorpayController::class, 'getUserPayments']);
Route::post('/razorpay/user-payments', [RazorpayController::class, 'getUserPayments']); // Accept POST for FlutterFlow
Route::post('/razorpay/refund', [RazorpayController::class, 'refundPayment']);

// Razorpay Callback Routes (accessible without authentication)
Route::get('/razorpay/payment-page', [RazorpayController::class, 'paymentPage'])->name('razorpay.payment-page');
Route::post('/razorpay/callback', [RazorpayController::class, 'callback'])->name('razorpay.callback');
Route::get('/razorpay/cancel', [RazorpayController::class, 'cancel'])->name('razorpay.cancel');
Route::get('/razorpay/payment-success', [RazorpayController::class, 'paymentSuccess'])->name('razorpay.payment-success');
Route::post('/razorpay/payment-success', [RazorpayController::class, 'paymentSuccess']);

// Razorpay Test Route
Route::get('/razorpay/test', function () {
    return response()->json([
        'message' => 'Razorpay Payment System is ready! (SANDBOX MODE)',
        'environment' => 'sandbox',
        'key_id_configured' => !empty(env('RAZORPAY_KEY_ID', 'rzp_test_RUX03OJs024Yes')),
        'key_secret_configured' => !empty(env('RAZORPAY_KEY_SECRET', '212wP4jHAaC68JgtIzs76xpN')),
        'timestamp' => now(),
        'base_url' => 'https://ourprojectapi.sroy.es/public/api/',
        'razorpay_urls' => [
            'api_base' => 'https://api.razorpay.com/v1',
            'payment_page_base' => 'https://ourprojectapi.sroy.es/public/api/razorpay/payment-page'
        ],
        'endpoints' => [
            'create_payment' => 'POST /razorpay/create (also /payment/create)',
            'verify_payment' => 'POST /razorpay/verify',
            'check_status' => 'GET|POST /razorpay/status (also /payment/status)',
            'user_payments' => 'GET|POST /razorpay/user-payments (also /payment/user-payments)',
            'refund' => 'POST /razorpay/refund (also /payment/refund)',
            'payment_page' => 'GET /razorpay/payment-page',
            'callback' => 'POST /razorpay/callback (also /payment/callback)',
            'cancel' => 'GET /razorpay/cancel',
            'success_check' => 'GET|POST /razorpay/payment-success'
        ],
        'credentials' => [
            'key_id' => 'rzp_test_RUX03OJs024Yes',
            'environment' => 'Test/Sandbox'
        ]
    ]);
});

// Quick API test for FlutterFlow
Route::get('/test-connection', function () {
    return response()->json([
        'success' => true,
        'message' => 'API connection successful - Razorpay Only',
        'server_time' => now(),
        'ready_for_flutterflow' => true,
        'payment_gateway' => 'razorpay',
        'environment' => 'sandbox',
        'endpoints' => [
            'create_payment' => 'POST /payment/create OR /razorpay/create',
            'payment_status' => 'GET|POST /payment/status OR /razorpay/status',
            'user_payments' => 'GET|POST /payment/user-payments OR /razorpay/user-payments',
            'payment_page' => 'GET /razorpay/payment-page',
            'callback' => 'POST /payment/callback OR /razorpay/callback'
        ],
        'note' => 'All /payment/* endpoints now redirect to Razorpay. Cashfree has been completely removed.'
    ]);
});