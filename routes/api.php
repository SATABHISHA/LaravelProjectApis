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

// Payment Routes for Cashfree Integration
Route::post('/payment/create', [PaymentController::class, 'createPayment']);
Route::get('/payment/status', [PaymentController::class, 'getPaymentStatus']);
Route::post('/payment/status', [PaymentController::class, 'getPaymentStatus']); // Accept POST for FlutterFlow
Route::get('/payment/user-payments', [PaymentController::class, 'getUserPayments']);
Route::post('/payment/user-payments', [PaymentController::class, 'getUserPayments']); // Accept POST for FlutterFlow
Route::post('/payment/refund', [PaymentController::class, 'refundPayment']);

// Payment Callback Routes (these should be accessible without authentication)
Route::get('/payment/callback', [PaymentController::class, 'handleCallback'])->name('payment.callback');
Route::post('/payment/webhook', [PaymentController::class, 'handleWebhook'])->name('payment.webhook');

// Test route for payment system
Route::get('/payment/test', function () {
    return response()->json([
        'message' => 'Cashfree Payment System is ready! (PRODUCTION MODE)',
        'environment' => 'production',
        'app_id_configured' => !empty(env('CASHFREE_APP_ID')),
        'secret_configured' => !empty(env('CASHFREE_SECRET_KEY')),
        'timestamp' => now(),
        'base_url' => 'https://ourprojectapi.sroy.es/public/api/',
        'cashfree_urls' => [
            'api_base' => 'https://api.cashfree.com',
            'checkout_base' => 'https://payments.cashfree.com/pay/'
        ],
        'endpoints' => [
            'create_payment' => 'POST /payment/create',
            'check_status' => 'GET|POST /payment/status', 
            'user_payments' => 'GET|POST /payment/user-payments',
            'callback' => 'GET /payment/callback',
            'webhook' => 'POST /payment/webhook'
        ],
        'warning' => '⚠️ PRODUCTION MODE - Use real payment credentials and amounts!'
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
Route::get('/razorpay/callback', [RazorpayController::class, 'handleCallback'])->name('razorpay.callback');
Route::get('/razorpay/cancel', [RazorpayController::class, 'handleCancel'])->name('razorpay.cancel');

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
            'checkout_base' => 'https://api.razorpay.com/v1/checkout/embedded'
        ],
        'endpoints' => [
            'create_payment' => 'POST /razorpay/create',
            'verify_payment' => 'POST /razorpay/verify',
            'check_status' => 'GET|POST /razorpay/status',
            'user_payments' => 'GET|POST /razorpay/user-payments',
            'refund' => 'POST /razorpay/refund',
            'callback' => 'GET /razorpay/callback',
            'cancel' => 'GET /razorpay/cancel'
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
        'message' => 'API connection successful',
        'server_time' => now(),
        'ready_for_flutterflow' => true,
        'payment_gateways' => [
            'cashfree' => 'production',
            'razorpay' => 'sandbox'
        ]
    ]);
});

// Debug endpoint for payment testing
Route::post('/payment/debug-create', function (Request $request) {
    $sampleSessionId = 'session_prod_debug_456';
    $paymentUrl = 'https://payments.cashfree.com/pay/' . $sampleSessionId;
    
    return response()->json([
        'success' => true,
        'message' => 'Debug payment creation (PRODUCTION MODE)',
        'received_data' => $request->all(),
        'environment' => 'production',
        'url_format' => 'https://payments.cashfree.com/pay/{session_id}',
        'sample_response' => [
            'order_id' => 'ORDER_' . time() . '_PROD_DEBUG',
            'cf_order_id' => 'order_prod_debug_123',
            'payment_session_id' => $sampleSessionId,
            'amount' => $request->amount ?? 100,
            'currency' => 'INR',
            'payment_url' => $paymentUrl
        ],
        'warning' => '⚠️ This will create REAL payments in production!'
    ]);
});