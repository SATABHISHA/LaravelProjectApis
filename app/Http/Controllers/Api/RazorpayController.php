<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Exception;

class RazorpayController extends Controller
{
    private $razorpayBaseUrl;
    private $keyId;
    private $keySecret;

    public function __construct()
    {
        // Razorpay configuration
        $this->razorpayBaseUrl = 'https://api.razorpay.com/v1';
        // LIVE KEYS
        $this->keyId = 'rzp_live_RaLYdhoChCz6XH';
        $this->keySecret = 'DsVdu8c3AmlW6Ysnom1vHbXw';
        // TEST KEYS (commented for future use)
        // $this->keyId = 'rzp_test_RUX03OJs024Yes';
        // $this->keySecret = '212wP4jHAaC68JgtIzs76xpN';
    }

    /**
     * Helper method to find payment by Razorpay order ID (backward compatible)
     */
    private function findPaymentByRazorpayOrderId($razorpayOrderId)
    {
        try {
            // First try to find by gateway_order_id column if it exists
            if (Schema::hasColumn('payments', 'gateway_order_id')) {
                $payment = Payment::where('gateway_order_id', $razorpayOrderId)->first();
                if ($payment) return $payment;
            }
        } catch (Exception $e) {
            // Column doesn't exist, continue to fallback
        }
        
        // Fallback: Look for payments by order_id pattern or in response data
        // Check if it's a recent Razorpay order by pattern
        $payment = Payment::where('order_id', 'LIKE', 'RZP_ORDER_%')
            ->where('created_at', '>=', now()->subDays(1)) // Recent orders only
            ->get()
            ->filter(function($p) use ($razorpayOrderId) {
                // Check if the razorpay order ID is stored in any response field
                $responses = [
                    $p->cashfree_response ?? [],
                    json_decode($p->gateway_response ?? '{}', true),
                ];
                
                foreach ($responses as $response) {
                    if (is_array($response) && isset($response['id']) && $response['id'] === $razorpayOrderId) {
                        return true;
                    }
                }
                return false;
            })
            ->first();
            
        return $payment;
    }

    /**
     * Create Razorpay payment order
     */

    public function createPayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1',
                'user_id' => 'required|integer',
                'description' => 'string|max:255',
                'return_url' => 'url'
            ]);

            // Get user details if user exists, otherwise use default values
            $user = User::find($request->user_id);
            if (!$user) {
                // Create a temporary user object with default values
                $user = (object) [
                    'id' => $request->user_id,
                    'name' => 'Guest User',
                    'email' => 'guest@example.com'
                ];
            }
            $orderId = 'RZP_ORDER_' . time() . '_' . Str::random(6);
            
            // Convert amount to paisa (Razorpay uses smallest currency unit)
            $amountInPaisa = $request->amount * 100;

            // Create payment record - backward compatible
            $paymentData = [
                'user_id' => $request->user_id,
                'order_id' => $orderId,
                'amount' => $request->amount,
                'currency' => 'INR',
                'status' => 'CREATED',
                'description' => $request->description ?? 'Payment for order',
                'return_url' => $request->return_url
            ];
            
            // Only set gateway field if column exists
            try {
                if (Schema::hasColumn('payments', 'gateway')) {
                    $paymentData['gateway'] = 'razorpay';
                }
            } catch (Exception $e) {
                // Column doesn't exist, skip it
            }
            
            $payment = Payment::create($paymentData);

            // Prepare Razorpay order data
            $orderData = [
                'amount' => $amountInPaisa,
                'currency' => 'INR',
                'receipt' => $orderId,
                'notes' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'description' => $request->description ?? 'Payment for order'
                ]
            ];

            // Create order in Razorpay
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post($this->razorpayBaseUrl . '/orders', $orderData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Update payment with Razorpay data - direct approach
                $payment->gateway_order_id = $responseData['id'];
                $payment->gateway_response = $responseData;
                $payment->save();
                
                Log::info('Payment updated with Razorpay data', [
                    'payment_id' => $payment->id,
                    'gateway_order_id' => $payment->gateway_order_id,
                    'razorpay_order_id' => $responseData['id']
                ]);

                // Create Razorpay checkout URL
                $checkoutUrl = $this->generateCheckoutUrl($responseData, $user, $request);

                return response()->json([
                    'success' => true,
                    'message' => 'Razorpay payment order created successfully',
                    'data' => [
                        'order_id' => $orderId,
                        'razorpay_order_id' => $responseData['id'],
                        'amount' => $request->amount,
                        'currency' => 'INR',
                        'payment_url' => $checkoutUrl,
                        'razorpay_key_id' => $this->keyId,
                        'gateway' => 'razorpay',
                        'razorpay_response' => $responseData
                    ]
                ], 200);
            } else {
                Log::error('Razorpay order creation failed', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'request_data' => $orderData
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create Razorpay payment order',
                    'error' => $response->json(),
                    'debug_info' => [
                        'status_code' => $response->status(),
                        'razorpay_environment' => 'sandbox',
                        'key_id_configured' => !empty($this->keyId)
                    ]
                ], 400);
            }

        } catch (Exception $e) {
            Log::error('Razorpay payment creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Razorpay payment creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Razorpay checkout URL for direct browser/WebView access
     */
    private function generateCheckoutUrl($razorpayOrder, $user, $request)
    {
        // Use production base URL for callbacks
        $baseUrl = 'https://ourprojectapi.sroy.es/public/api';
        
        // For FlutterFlow WebView integration, we need to return the data for Razorpay JS SDK
        // But since direct URL is needed, let's create a custom payment page URL
        
        // Generate a simple payment page URL that will handle Razorpay integration
        $paymentPageUrl = $baseUrl . '/razorpay/payment-page?' . http_build_query([
            'order_id' => $razorpayOrder['id'],
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
            'key' => $this->keyId,
            'name' => urlencode('FlutterFlow Payment'),
            'description' => urlencode($request->description ?? 'Payment for order'),
            'prefill_name' => urlencode($user->name),
            'prefill_email' => urlencode($user->email ?? 'user@example.com'),
            'prefill_contact' => urlencode($user->phone ?? '9999999999'),
            'callback_url' => urlencode($baseUrl . '/razorpay/callback'),
            'cancel_url' => urlencode($baseUrl . '/razorpay/cancel')
        ]);
        
        return $paymentPageUrl;
    }

    /**
     * Verify and update payment status
     */
    public function verifyPayment(Request $request)
    {
        try {
            $request->validate([
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_signature' => 'required|string'
            ]);

            // Find payment by razorpay order ID
            $payment = Payment::where('gateway_order_id', $request->razorpay_order_id)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Verify signature
            $signature = hash_hmac('sha256', 
                $request->razorpay_order_id . '|' . $request->razorpay_payment_id, 
                $this->keySecret
            );

            if ($signature === $request->razorpay_signature) {
                // Payment verified successfully
                $payment->update([
                    'status' => 'PAID',
                    'gateway_payment_id' => $request->razorpay_payment_id,
                    'paid_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => [
                        'order_id' => $payment->order_id,
                        'status' => 'PAID',
                        'amount' => $payment->amount,
                        'payment_id' => $request->razorpay_payment_id
                    ]
                ], 200);
            } else {
                // Invalid signature
                $payment->update(['status' => 'FAILED']);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed - invalid signature'
                ], 400);
            }

        } catch (Exception $e) {
            Log::error('Razorpay payment verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment status (supports both GET and POST)
     */
    public function getPaymentStatus(Request $request)
    {
        try {
            $orderId = $request->input('order_id') ?? $request->query('order_id');
            
            $request->validate([
                'order_id' => 'required|string'
            ]);
        

            $payment = Payment::where('order_id', $orderId)->where('gateway', 'razorpay')->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay payment not found'
                ], 404);
            }

            // Get latest status from Razorpay if payment exists
            if ($payment->gateway_order_id) {
                $this->updatePaymentStatusFromRazorpay($payment);
                $payment->refresh();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $payment->order_id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'gateway' => 'razorpay',
                    'payment_method' => $payment->payment_method,
                    'paid_at' => $payment->paid_at,
                    'description' => $payment->description,
                    'razorpay_order_id' => $payment->gateway_order_id,
                    'razorpay_payment_id' => $payment->gateway_payment_id
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Razorpay payment status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user payment history for Razorpay payments
     */
    public function getUserPayments(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer',
                'status' => 'nullable|string|in:CREATED,PAID,FAILED,CANCELLED',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $userId = $request->input('user_id') ?? $request->query('user_id');
            $status = $request->input('status') ?? $request->query('status');
            $limit = $request->input('limit') ?? $request->query('limit', 20);

            $query = Payment::where('user_id', $userId)
                          ->where('gateway', 'razorpay')
                          ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $payments = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $payments->map(function ($payment) {
                    return [
                        'order_id' => $payment->order_id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'gateway' => 'razorpay',
                        'payment_method' => $payment->payment_method,
                        'description' => $payment->description,
                        'created_at' => $payment->created_at,
                        'paid_at' => $payment->paid_at,
                        'razorpay_order_id' => $payment->gateway_order_id,
                        'razorpay_payment_id' => $payment->gateway_payment_id
                    ];
                })
            ], 200);

        } catch (Exception $e) {
            Log::error('Razorpay user payments fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Razorpay payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment callback from Razorpay
     */
    public function handleCallback(Request $request)
    {
        try {
            $razorpayPaymentId = $request->get('razorpay_payment_id');
            $razorpayOrderId = $request->get('razorpay_order_id');
            $razorpaySignature = $request->get('razorpay_signature');

            if ($razorpayPaymentId && $razorpayOrderId && $razorpaySignature) {
                // Verify payment
                $verifyRequest = new Request([
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'razorpay_order_id' => $razorpayOrderId,
                    'razorpay_signature' => $razorpaySignature
                ]);

                $verificationResult = $this->verifyPayment($verifyRequest);
                $verificationData = $verificationResult->getData(true);

                $payment = Payment::where('gateway_order_id', $razorpayOrderId)->first();
                
                if ($payment) {
                    $redirectUrl = $payment->return_url ?? env('FLUTTER_FLOW_SUCCESS_URL', 'https://your-app.flutterflow.app/payment-result');
                    $redirectUrl .= '?order_id=' . $payment->order_id . '&status=' . $payment->status . '&amount=' . $payment->amount . '&gateway=razorpay';
                    
                    return redirect($redirectUrl);
                }
            }

            return redirect(env('FLUTTER_FLOW_ERROR_URL', 'https://your-app.flutterflow.app/payment-error?gateway=razorpay'));

        } catch (Exception $e) {
            Log::error('Razorpay payment callback error: ' . $e->getMessage());
            return redirect(env('FLUTTER_FLOW_ERROR_URL', 'https://your-app.flutterflow.app/payment-error?gateway=razorpay'));
        }
    }

    /**
     * Handle payment cancellation
     */
    public function handleCancel(Request $request)
    {
        try {
            $razorpayOrderId = $request->get('order_id');
            
            if ($razorpayOrderId) {
                $payment = Payment::where('gateway_order_id', $razorpayOrderId)->first();
                
                if ($payment) {
                    $payment->update(['status' => 'CANCELLED']);
                    
                    $redirectUrl = $payment->return_url ?? env('FLUTTER_FLOW_CANCEL_URL', 'https://your-app.flutterflow.app/payment-cancelled');
                    $redirectUrl .= '?order_id=' . $payment->order_id . '&status=CANCELLED&gateway=razorpay';
                    
                    return redirect($redirectUrl);
                }
            }

            return redirect(env('FLUTTER_FLOW_CANCEL_URL', 'https://your-app.flutterflow.app/payment-cancelled?gateway=razorpay'));

        } catch (Exception $e) {
            Log::error('Razorpay payment cancellation error: ' . $e->getMessage());
            return redirect(env('FLUTTER_FLOW_ERROR_URL', 'https://your-app.flutterflow.app/payment-error?gateway=razorpay'));
        }
    }

    /**
     * Refund Razorpay payment
     */
    public function refundPayment(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string',
                'refund_amount' => 'numeric|min:1',
                'refund_note' => 'string|max:255'
            ]);

            $payment = Payment::where('order_id', $request->order_id)
                            ->where('gateway', 'razorpay')
                            ->where('status', 'PAID')
                            ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay payment not found or not eligible for refund'
                ], 404);
            }

            $refundAmount = $request->get('refund_amount', $payment->amount);
            $refundAmountInPaisa = $refundAmount * 100;

            $refundData = [
                'amount' => $refundAmountInPaisa,
                'notes' => [
                    'reason' => $request->get('refund_note', 'Refund requested'),
                    'order_id' => $payment->order_id
                ]
            ];

            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post($this->razorpayBaseUrl . '/payments/' . $payment->gateway_payment_id . '/refund', $refundData);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Razorpay refund initiated successfully',
                    'data' => $response->json()
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay refund failed',
                    'error' => $response->json()
                ], 400);
            }

        } catch (Exception $e) {
            Log::error('Razorpay refund error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Razorpay refund processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status from Razorpay API
     */
    private function updatePaymentStatusFromRazorpay(Payment $payment)
    {
        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get($this->razorpayBaseUrl . '/orders/' . $payment->gateway_order_id);

            if ($response->successful()) {
                $data = $response->json();
                
                $updateData = [
                    'gateway_response' => $data
                ];

                // Check payment status
                if ($data['status'] === 'paid') {
                    $updateData['status'] = 'PAID';
                    $updateData['paid_at'] = now();
                    
                    // Get payment details if available
                    if (!empty($data['payments'])) {
                        $paymentData = $data['payments'][0];
                        $updateData['payment_method'] = $paymentData['method'] ?? null;
                        $updateData['gateway_payment_id'] = $paymentData['id'] ?? null;
                    }
                } elseif ($data['status'] === 'attempted') {
                    $updateData['status'] = 'FAILED';
                }

                $payment->update($updateData);
            }
        } catch (Exception $e) {
            Log::error('Failed to update Razorpay payment status: ' . $e->getMessage());
        }
    }

    /**
     * Display custom payment page for Razorpay integration
     */
    public function paymentPage(Request $request)
    {
        // Get all parameters from query string
        $orderId = $request->order_id;
        $amount = $request->amount;
        $currency = $request->currency;
        $key = $request->key;
        $name = urldecode($request->name ?? 'Payment');
        $description = urldecode($request->description ?? 'Order Payment');
        $prefillName = urldecode($request->prefill_name ?? '');
        $prefillEmail = urldecode($request->prefill_email ?? '');
        $prefillContact = urldecode($request->prefill_contact ?? '');
        $callbackUrl = urldecode($request->callback_url ?? '');
        $cancelUrl = urldecode($request->cancel_url ?? '');

        // Generate HTML page with Razorpay integration
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Razorpay Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .payment-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .payment-title {
            color: #333;
            margin-bottom: 20px;
        }
        .payment-details {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }
        .pay-button {
            background: #3399cc;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 15px;
        }
        .pay-button:hover {
            background: #2680b3;
        }
        .cancel-button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .loading {
            display: none;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h2 class="payment-title">' . htmlspecialchars($name) . '</h2>
        <div class="payment-details">
            <p><strong>Description:</strong> ' . htmlspecialchars($description) . '</p>
            <p class="amount">₹' . number_format($amount/100, 2) . '</p>
            <p><strong>Order ID:</strong> ' . htmlspecialchars($orderId) . '</p>
        </div>
        
        <button class="pay-button" onclick="startPayment()">Pay Now with Razorpay</button>
        <div class="loading" id="loading">Processing payment...</div>
        <a href="' . htmlspecialchars($cancelUrl) . '?order_id=' . htmlspecialchars($orderId) . '" class="cancel-button">Cancel Payment</a>
    </div>

    <script>
    function startPayment() {
        document.getElementById("loading").style.display = "block";
        
        var options = {
            "key": "' . htmlspecialchars($key) . '",
            "amount": "' . htmlspecialchars($amount) . '",
            "currency": "' . htmlspecialchars($currency) . '",
            "name": "' . htmlspecialchars($name) . '",
            "description": "' . htmlspecialchars($description) . '",
            "order_id": "' . htmlspecialchars($orderId) . '",
            "handler": function (response) {
                // Payment successful
                console.log("Payment Success:", response);
                document.getElementById("loading").innerHTML = "✅ Payment successful! Redirecting...";
                
                // For FlutterFlow - immediately post success message to parent
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: "razorpay_payment_success",
                        payment_id: response.razorpay_payment_id,
                        order_id: response.razorpay_order_id,
                        signature: response.razorpay_signature,
                        action: "navigate_to_main"
                    }, "*");
                }
                
                // Submit to callback for server-side verification
                var form = document.createElement("form");
                form.method = "POST";
                form.action = "' . htmlspecialchars($callbackUrl) . '";
                
                var fields = ["razorpay_payment_id", "razorpay_order_id", "razorpay_signature"];
                fields.forEach(function(field) {
                    var input = document.createElement("input");
                    input.type = "hidden";
                    input.name = field;
                    input.value = response[field];
                    form.appendChild(input);
                });
                
                // Add CSRF token for Laravel
                var csrfInput = document.createElement("input");
                csrfInput.type = "hidden";
                csrfInput.name = "_token";
                csrfInput.value = "' . csrf_token() . '";
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            },
            "prefill": {
                "name": "' . htmlspecialchars($prefillName) . '",
                "email": "' . htmlspecialchars($prefillEmail) . '",
                "contact": "' . htmlspecialchars($prefillContact) . '"
            },
            "theme": {
                "color": "#3399cc"
            },
            "modal": {
                "ondismiss": function() {
                    document.getElementById("loading").style.display = "none";
                    console.log("Payment cancelled by user");
                    
                    // Notify FlutterFlow about cancellation
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            type: "razorpay_payment_cancelled",
                            order_id: "' . htmlspecialchars($orderId) . '",
                            action: "stay_on_payment_page"
                        }, "*");
                    }
                }
            }
        };
        
        var rzp = new Razorpay(options);
        rzp.open();
    }
    
    // Auto-trigger payment when page loads (for seamless WebView experience)
    window.onload = function() {
        setTimeout(startPayment, 1000);
    };
    </script>
</body>
</html>';

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Handle successful payment callback
     */
    public function callback(Request $request)
    {
        try {
            $razorpayPaymentId = $request->razorpay_payment_id;
            $razorpayOrderId = $request->razorpay_order_id;
            $razorpaySignature = $request->razorpay_signature;

            // Log the callback data for debugging
            Log::info('Razorpay callback received', [
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_signature' => $razorpaySignature
            ]);

            // Verify payment signature
            $signature = hash_hmac('sha256', 
                $razorpayOrderId . '|' . $razorpayPaymentId, 
                $this->keySecret
            );

            if ($signature !== $razorpaySignature) {
                throw new Exception('Invalid payment signature');
            }

            // Update payment status - try multiple methods to find payment
            $payment = null;
            
            // Method 1: Try to find by gateway_order_id
            if (Schema::hasColumn('payments', 'gateway_order_id')) {
                $payment = Payment::where('gateway_order_id', $razorpayOrderId)->first();
                Log::info('Method 1 - gateway_order_id search', ['found' => $payment ? 'yes' : 'no']);
            }
            
            // Method 2: If not found, try to find by the order pattern in order_id and check gateway_response
            if (!$payment) {
                $payments = Payment::where('order_id', 'LIKE', 'RZP_ORDER_%')
                    ->where('created_at', '>=', now()->subHours(2))
                    ->get();
                    
                foreach ($payments as $p) {
                    // Check if this payment has the razorpay order ID in its response data
                    $gatewayResponse = $p->gateway_response; // Already cast to array by Laravel
                    
                    // Also check cashfree_response as fallback
                    if (!$gatewayResponse && $p->cashfree_response) {
                        $gatewayResponse = is_array($p->cashfree_response) ? $p->cashfree_response : json_decode($p->cashfree_response ?? '{}', true);
                    }
                    
                    if ($gatewayResponse && isset($gatewayResponse['id']) && $gatewayResponse['id'] === $razorpayOrderId) {
                        $payment = $p;
                        Log::info('Method 2 - found payment by response data', ['payment_id' => $payment->id]);
                        break;
                    }
                }
            }
            
            // Method 3: If still not found, get the most recent unpaid Razorpay payment
            if (!$payment) {
                $payment = Payment::where('status', 'CREATED')
                    ->where('order_id', 'LIKE', 'RZP_ORDER_%')
                    ->where('created_at', '>=', now()->subHours(2))
                    ->orderBy('created_at', 'desc')
                    ->first();
                Log::info('Method 3 - most recent unpaid payment', ['found' => $payment ? 'yes' : 'no']);
            }
            
            if ($payment) {
                // Update payment status - backward compatible
                $updateData = [
                    'status' => 'PAID',
                    'paid_at' => now()
                ];
                
                // Only set gateway fields if columns exist
                try {
                    if (Schema::hasColumn('payments', 'gateway_payment_id')) {
                        $updateData['gateway_payment_id'] = $razorpayPaymentId;
                    }
                    if (Schema::hasColumn('payments', 'gateway_response')) {
                        $updateData['gateway_response'] = json_encode($request->all());
                    }
                } catch (Exception $e) {
                    // Columns don't exist, use fallback
                }
                
                // Fallback: use existing fields
                if (!isset($updateData['gateway_payment_id'])) {
                    $updateData['cf_payment_id'] = $razorpayPaymentId; // Use existing field as fallback
                }
                if (!isset($updateData['gateway_response'])) {
                    $updateData['cashfree_response'] = json_decode(json_encode($request->all()), true);
                }
                
                $payment->update($updateData);

                // For FlutterFlow integration - redirect to success page with auto-close
                $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #28a745, #20c997); margin: 0; padding: 20px; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { max-width: 400px; background: white; border-radius: 15px; padding: 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .success-icon { font-size: 60px; margin-bottom: 20px; }
        .success-title { color: #28a745; margin-bottom: 15px; font-size: 24px; }
        .success-message { color: #666; margin-bottom: 20px; line-height: 1.5; }
        .redirect-info { background: #e3f2fd; color: #1976d2; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 14px; }
        .countdown { font-size: 18px; font-weight: bold; color: #28a745; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h2 class="success-title">Payment Successful!</h2>
        <p class="success-message">Your payment of ₹' . number_format((float)$payment->amount, 2) . ' has been processed successfully.</p>
        
        <div class="redirect-info">
            <strong>Payment ID:</strong> ' . htmlspecialchars($razorpayPaymentId) . '<br>
            <strong>Order ID:</strong> ' . htmlspecialchars($razorpayOrderId) . '
        </div>
        
        <div class="countdown" id="countdown">Redirecting to app in <span id="timer">3</span> seconds...</div>
    </div>
    
    <script>
    // Countdown timer
    let timeLeft = 3;
    const timerElement = document.getElementById("timer");
    const countdownElement = document.getElementById("countdown");
    
    const countdown = setInterval(() => {
        timeLeft--;
        timerElement.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(countdown);
            countdownElement.textContent = "Redirecting now...";
            
            // Try multiple methods to close/redirect for FlutterFlow
            setTimeout(() => {
                // Method 1: Post message to parent (for WebView)
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: "payment_success",
                        action: "close_webview",
                        payment_id: "' . htmlspecialchars($razorpayPaymentId) . '",
                        order_id: "' . htmlspecialchars($razorpayOrderId) . '",
                        amount: ' . $payment->amount . ',
                        status: "PAID",
                        redirect_to_main: true
                    }, "*");
                }
                
                // Method 2: Try to close window
                try {
                    window.close();
                } catch(e) {
                    console.log("Cannot close window:", e);
                }
                
                // Method 3: Redirect to a custom URL scheme (for FlutterFlow deep linking)
                try {
                    window.location.href = "flutterflow://payment-success?payment_id=' . htmlspecialchars($razorpayPaymentId) . '&order_id=' . htmlspecialchars($razorpayOrderId) . '&amount=' . $payment->amount . '";
                } catch(e) {
                    console.log("Custom URL scheme not supported:", e);
                }
                
                // Method 4: Fallback - redirect to success URL with parameters
                setTimeout(() => {
                    window.location.href = "about:blank";
                }, 500);
                
            }, 500);
        }
    }, 1000);
    
    // Immediate post message on load
    window.addEventListener("load", function() {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: "payment_success",
                payment_id: "' . htmlspecialchars($razorpayPaymentId) . '",
                order_id: "' . htmlspecialchars($razorpayOrderId) . '",
                amount: ' . $payment->amount . ',
                status: "PAID",
                redirect_to_main: true
            }, "*");
        }
    });
    
    // Listen for messages from parent (FlutterFlow)
    window.addEventListener("message", function(event) {
        if (event.data && event.data.type === "close_payment_webview") {
            window.close();
        }
    });
    </script>
</body>
</html>';

                return response($html, 200, ['Content-Type' => 'text/html']);
            }

            throw new Exception('Payment record not found');

        } catch (Exception $e) {
            Log::error('Razorpay callback error: ' . $e->getMessage());
            
            // Return error page
            $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 50px auto; background: white; border-radius: 10px; padding: 30px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .error-icon { font-size: 50px; color: #dc3545; margin-bottom: 20px; }
        .error-title { color: #dc3545; margin-bottom: 15px; }
        .back-button { background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">❌</div>
        <h2 class="error-title">Payment Failed</h2>
        <p>Sorry, there was an issue processing your payment.</p>
        <p><em>' . htmlspecialchars($e->getMessage()) . '</em></p>
        <a href="#" onclick="window.close()" class="back-button">Close</a>
    </div>
</body>
</html>';

            return response($html, 400, ['Content-Type' => 'text/html']);
        }
    }

    /**
     * Handle payment cancellation
     */
    public function cancel(Request $request)
    {
        $orderId = $request->query('order_id');
        
        if ($orderId) {
            $payment = Payment::where('gateway_order_id', $orderId)->first();
            if ($payment) {
                $payment->update(['status' => 'CANCELLED']);
            }
        }

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 50px auto; background: white; border-radius: 10px; padding: 30px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .cancel-icon { font-size: 50px; color: #ffc107; margin-bottom: 20px; }
        .cancel-title { color: #856404; margin-bottom: 15px; }
        .back-button { background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="cancel-icon">⚠️</div>
        <h2 class="cancel-title">Payment Cancelled</h2>
        <p>You have cancelled the payment process.</p>
        <p>You can try again later if needed.</p>
        <a href="#" onclick="window.close()" class="back-button">Close</a>
    </div>
    
    <script>
    // For FlutterFlow integration - post message to parent
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: "payment_cancelled",
            order_id: "' . htmlspecialchars($orderId ?? '') . '",
            status: "CANCELLED"
        }, "*");
    }
    </script>
</body>
</html>';

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * API endpoint for FlutterFlow to check payment success and get navigation data
     */
    public function paymentSuccess(Request $request)
    {
        try {
            $orderId = $request->input('order_id') ?? $request->query('order_id');
            $paymentId = $request->input('payment_id') ?? $request->query('payment_id');
            
            if (!$orderId && !$paymentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order ID or Payment ID is required'
                ], 400);
            }

            // Find payment by order_id or payment_id
            $payment = null;
            if ($orderId) {
                $payment = Payment::where('gateway_order_id', $orderId)->where('gateway', 'razorpay')->first();
            } elseif ($paymentId) {
                $payment = Payment::where('gateway_payment_id', $paymentId)->where('gateway', 'razorpay')->first();
            }

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Check if payment is successful
            if ($payment->status === 'PAID') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful',
                    'navigation' => [
                        'action' => 'redirect_to_main',
                        'close_webview' => true,
                        'show_success_message' => true
                    ],
                    'payment_data' => [
                        'order_id' => $payment->order_id,
                        'razorpay_order_id' => $payment->gateway_order_id,
                        'payment_id' => $payment->gateway_payment_id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'paid_at' => $payment->paid_at,
                        'description' => $payment->description
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed',
                    'navigation' => [
                        'action' => 'show_error',
                        'close_webview' => false,
                        'retry_payment' => true
                    ],
                    'payment_data' => [
                        'order_id' => $payment->order_id,
                        'status' => $payment->status,
                        'amount' => $payment->amount
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Razorpay payment success check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
                'error' => $e->getMessage(),
                'navigation' => [
                    'action' => 'show_error',
                    'close_webview' => false,
                    'retry_payment' => true
                ]
            ], 500);
        }
    }
}
