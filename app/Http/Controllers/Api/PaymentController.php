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

class PaymentController extends Controller
{
    private $cashfreeBaseUrl;
    private $appId;
    private $secretKey;

    public function __construct()
    {
        // Use sandbox URL for testing, production URL for live
        $this->cashfreeBaseUrl = env('CASHFREE_ENVIRONMENT', 'sandbox') === 'production' 
            ? 'https://api.cashfree.com' 
            : 'https://sandbox.cashfree.com';
        
        $this->appId = env('CASHFREE_APP_ID');
        $this->secretKey = env('CASHFREE_SECRET_KEY');
    }

    /**
     * Create payment order and redirect to Cashfree
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
            $orderId = 'ORDER_' . time() . '_' . Str::random(6);
            
            // Create payment record
            $payment = Payment::create([
                'user_id' => $request->user_id,
                'order_id' => $orderId,
                'amount' => $request->amount,
                'currency' => 'INR',
                'status' => 'CREATED',
                'description' => $request->description ?? 'Payment for order',
                'return_url' => $request->return_url
            ]);

            // Prepare Cashfree order data
            $orderData = [
                'order_id' => $orderId,
                'order_amount' => $request->amount,
                'order_currency' => 'INR',
                'customer_details' => [
                    'customer_id' => (string)$user->id,
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone ?? '9999999999'
                ],
                'order_meta' => [
                    'return_url' => route('payment.callback'),
                    'notify_url' => route('payment.webhook')
                ],
                'order_note' => $request->description ?? 'Payment for order'
            ];

            // Create order in Cashfree
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-client-id' => $this->appId,
                'x-client-secret' => $this->secretKey,
                'x-api-version' => '2023-08-01'
            ])->post($this->cashfreeBaseUrl . '/pg/orders', $orderData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                $payment->update([
                    'cf_order_id' => $responseData['cf_order_id'],
                    'cashfree_response' => $responseData
                ]);

                // Construct the correct payment URL for Cashfree
                $paymentUrl = null;
                if (isset($responseData['payment_session_id']) && $responseData['order_status'] === 'ACTIVE') {
                    // Use the correct Cashfree checkout URL format
                    if (env('CASHFREE_ENVIRONMENT', 'sandbox') === 'production') {
                        $paymentUrl = 'https://payments.cashfree.com/pay/' . $responseData['payment_session_id'];
                    } else {
                        $paymentUrl = 'https://payments-test.cashfree.com/pay/' . $responseData['payment_session_id'];
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment order created successfully',
                    'data' => [
                        'order_id' => $orderId,
                        'cf_order_id' => $responseData['cf_order_id'],
                        'payment_session_id' => $responseData['payment_session_id'],
                        'amount' => $request->amount,
                        'currency' => 'INR',
                                                'payment_url' => $paymentUrl,
                        'cashfree_response' => $responseData // Include full response for debugging
                    ]
                ], 200);
            } else {
                Log::error('Cashfree order creation failed', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'request_data' => $orderData
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment order',
                    'error' => $response->json(),
                    'debug_info' => [
                        'status_code' => $response->status(),
                        'cashfree_environment' => $this->cashfreeBaseUrl,
                        'app_id_configured' => !empty($this->appId)
                    ]
                ], 400);
            }

        } catch (Exception $e) {
            Log::error('Payment creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment creation failed',
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
            // Handle both GET query params and POST body params
            $orderId = $request->input('order_id') ?? $request->query('order_id');
            
            $request->validate([
                'order_id' => 'required|string'
            ]);

            $payment = Payment::where('order_id', $orderId)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Check status from Cashfree if payment exists
            if ($payment->cf_order_id) {
                $this->updatePaymentStatusFromCashfree($payment);
                $payment->refresh();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $payment->order_id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'paid_at' => $payment->paid_at,
                    'description' => $payment->description
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Payment status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment callback from Cashfree
     */
    public function handleCallback(Request $request)
    {
        try {
            $orderId = $request->get('order_id');
            $payment = Payment::where('order_id', $orderId)->first();

            if ($payment) {
                $this->updatePaymentStatusFromCashfree($payment);
                
                // Redirect to FlutterFlow with status
                $redirectUrl = $payment->return_url ?? env('FLUTTER_FLOW_SUCCESS_URL', 'https://your-app.flutterflow.app/payment-result');
                $redirectUrl .= '?order_id=' . $payment->order_id . '&status=' . $payment->status . '&amount=' . $payment->amount;
                
                return redirect($redirectUrl);
            }

            return redirect(env('FLUTTER_FLOW_ERROR_URL', 'https://your-app.flutterflow.app/payment-error'));

        } catch (Exception $e) {
            Log::error('Payment callback error: ' . $e->getMessage());
            return redirect(env('FLUTTER_FLOW_ERROR_URL', 'https://your-app.flutterflow.app/payment-error'));
        }
    }

    /**
     * Handle webhook from Cashfree
     */
    public function handleWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info('Cashfree webhook received', $payload);

            if (isset($payload['data']['order']['order_id'])) {
                $orderId = $payload['data']['order']['order_id'];
                $payment = Payment::where('order_id', $orderId)->first();

                if ($payment) {
                    $this->updatePaymentStatusFromCashfree($payment);
                }
            }

            return response()->json(['status' => 'success'], 200);

        } catch (Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Get user payment history (supports both GET and POST)
     */
    public function getUserPayments(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'status' => 'nullable|string|in:CREATED,PAID,FAILED,CANCELLED',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            // Handle both GET query params and POST body params
            $userId = $request->input('user_id') ?? $request->query('user_id');
            $status = $request->input('status') ?? $request->query('status');
            $limit = $request->input('limit') ?? $request->query('limit', 20);

            $query = Payment::where('user_id', $userId)
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
                        'payment_method' => $payment->payment_method,
                        'description' => $payment->description,
                        'created_at' => $payment->created_at,
                        'paid_at' => $payment->paid_at
                    ];
                })
            ], 200);

        } catch (Exception $e) {
            Log::error('User payments fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund payment (if supported)
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
                            ->where('status', 'PAID')
                            ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found or not eligible for refund'
                ], 404);
            }

            $refundAmount = $request->get('refund_amount', $payment->amount);

            $refundData = [
                'refund_amount' => $refundAmount,
                'refund_id' => 'REFUND_' . time() . '_' . Str::random(6),
                'refund_note' => $request->get('refund_note', 'Refund requested')
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-client-id' => $this->appId,
                'x-client-secret' => $this->secretKey,
                'x-api-version' => '2023-08-01'
            ])->post($this->cashfreeBaseUrl . '/pg/orders/' . $payment->cf_order_id . '/refunds', $refundData);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Refund initiated successfully',
                    'data' => $response->json()
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund failed',
                    'error' => $response->json()
                ], 400);
            }

        } catch (Exception $e) {
            Log::error('Refund error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status from Cashfree API
     */
    private function updatePaymentStatusFromCashfree(Payment $payment)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'x-client-id' => $this->appId,
                'x-client-secret' => $this->secretKey,
                'x-api-version' => '2023-08-01'
            ])->get($this->cashfreeBaseUrl . '/pg/orders/' . $payment->cf_order_id);

            if ($response->successful()) {
                $data = $response->json();
                
                $updateData = [
                    'cashfree_response' => $data
                ];

                if ($data['order_status'] === 'PAID') {
                    $updateData['status'] = 'PAID';
                    $updateData['paid_at'] = now();
                    
                    // Get payment details
                    $paymentsResponse = Http::withHeaders([
                        'Accept' => 'application/json',
                        'x-client-id' => $this->appId,
                        'x-client-secret' => $this->secretKey,
                        'x-api-version' => '2023-08-01'
                    ])->get($this->cashfreeBaseUrl . '/pg/orders/' . $payment->cf_order_id . '/payments');

                    if ($paymentsResponse->successful()) {
                        $paymentData = $paymentsResponse->json();
                        if (!empty($paymentData)) {
                            $updateData['payment_method'] = $paymentData[0]['payment_method'] ?? null;
                            $updateData['cf_payment_id'] = $paymentData[0]['cf_payment_id'] ?? null;
                        }
                    }
                } elseif (in_array($data['order_status'], ['CANCELLED', 'FAILED'])) {
                    $updateData['status'] = $data['order_status'];
                }

                $payment->update($updateData);
            }
        } catch (Exception $e) {
            Log::error('Failed to update payment status from Cashfree: ' . $e->getMessage());
        }
    }
}
