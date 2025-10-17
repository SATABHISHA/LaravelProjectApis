<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->keyId = env('RAZORPAY_KEY_ID', 'rzp_test_RUX03OJs024Yes');
        $this->keySecret = env('RAZORPAY_KEY_SECRET', '212wP4jHAaC68JgtIzs76xpN');
    }

    /**
     * Create Razorpay payment order
     */

    public function createPayment(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1',
                'user_id' => 'required|exists:users,id',
                'description' => 'string|max:255',
                'return_url' => 'url'
            ]);

            $user = User::find($request->user_id);
            $orderId = 'RZP_ORDER_' . time() . '_' . Str::random(6);
            
            // Convert amount to paisa (Razorpay uses smallest currency unit)
            $amountInPaisa = $request->amount * 100;

            // Create payment record
            $payment = Payment::create([
                'user_id' => $request->user_id,
                'order_id' => $orderId,
                'amount' => $request->amount,
                'currency' => 'INR',
                'status' => 'CREATED',
                'description' => $request->description ?? 'Payment for order',
                'return_url' => $request->return_url,
                'gateway' => 'razorpay'
            ]);

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
                
                $payment->update([
                    'gateway_order_id' => $responseData['id'],
                    'gateway_response' => $responseData
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
        
        // Razorpay Standard Checkout parameters
        $checkoutParams = [
            'key_id' => $this->keyId,
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
            'name' => 'FlutterFlow Payment',
            'description' => $request->description ?? 'Payment for order',
            'order_id' => $razorpayOrder['id'],
            'callback_url' => $baseUrl . '/razorpay/callback',
            'cancel_url' => $baseUrl . '/razorpay/cancel',
            'prefill.name' => $user->name,
            'prefill.email' => $user->email,
            'prefill.contact' => $user->phone ?? '9999999999',
            'theme.color' => '#3399cc',
            'modal.ondismiss' => 'function(){window.location="' . $baseUrl . '/razorpay/cancel?order_id=' . $razorpayOrder['id'] . '"}'
        ];

        // Build query string for Razorpay Standard Checkout
        $queryString = http_build_query($checkoutParams);
        
        // Return Razorpay Standard Checkout URL (this redirects to Razorpay's hosted payment page)
        return 'https://checkout.razorpay.com/v1/checkout?' . $queryString;
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
                'user_id' => 'required|exists:users,id',
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
}
