<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * PaymentController - Redirects to RazorpayController
 * 
 * This controller maintains backward compatibility by redirecting
 * all payment-related requests to the RazorpayController.
 * Cashfree integration has been removed in favor of Razorpay only.
 */
class PaymentController extends Controller
{
    /**
     * Redirect payment creation to Razorpay
     */
    public function createPayment(Request $request)
    {
        $razorpayController = new \App\Http\Controllers\Api\RazorpayController();
        return $razorpayController->createPayment($request);
    }

    /**
     * Redirect payment status check to Razorpay
     */
    public function getPaymentStatus(Request $request)
    {
        $razorpayController = new \App\Http\Controllers\Api\RazorpayController();
        return $razorpayController->getPaymentStatus($request);
    }

    /**
     * Redirect user payments to Razorpay
     */
    public function getUserPayments(Request $request)
    {
        $razorpayController = new \App\Http\Controllers\Api\RazorpayController();
        return $razorpayController->getUserPayments($request);
    }

    /**
     * Redirect refund to Razorpay
     */
    public function refundPayment(Request $request)
    {
        $razorpayController = new \App\Http\Controllers\Api\RazorpayController();
        return $razorpayController->refundPayment($request);
    }

    /**
     * Redirect callback to Razorpay
     */
    public function handleCallback(Request $request)
    {
        $razorpayController = new \App\Http\Controllers\Api\RazorpayController();
        return $razorpayController->callback($request);
    }

    /**
     * Redirect webhook to Razorpay (treat as callback)
     */
    public function handleWebhook(Request $request)
    {
        $razorpayController = new \App\Http\Controllers\Api\RazorpayController();
        return $razorpayController->callback($request);
    }
}
