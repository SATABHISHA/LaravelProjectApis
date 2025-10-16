# FlutterFlow Integration Guide for Cashfree Payments

## Quick Start for FlutterFlow

### 1. Create Payment API Call

In FlutterFlow, create a new API call with these settings:

**API Group Name:** `CashfreePayment`
**Call Name:** `CreatePayment`

**Settings:**
- **Method:** POST
- **URL:** `https://your-domain.com/api/payment/create`
- **Headers:**
  ```
  Content-Type: application/json
  Accept: application/json
  ```

**Body (JSON):**
```json
{
  "amount": "[amount]",
  "user_id": "[userId]",
  "description": "[description]",
  "return_url": "https://your-app.flutterflow.app/payment-result"
}
```

**Variables:**
- `amount` (double) - The payment amount
- `userId` (int) - Current user ID
- `description` (String) - Payment description

### 2. Create Payment Result Page

Create a new page called `PaymentResult` in FlutterFlow.

**Page Parameters:**
- `orderId` (String)
- `status` (String) 
- `amount` (String)

### 3. Payment Button Action

On your payment button, add these actions:

1. **API Call Action:** Select `CashfreePayment.CreatePayment`
2. **Set Variables:**
   - `amount`: Your amount variable
   - `userId`: Current user ID from auth
   - `description`: "Payment for order"
3. **Conditional Action:** If API call is successful
4. **Launch URL Action:** Use `[response].data.payment_url`

### 4. Complete FlutterFlow Custom Action

Create a custom action in FlutterFlow:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';

Future<bool> initiatePayment({
  required double amount,
  required int userId,
  String description = "Payment",
  required String baseUrl,
}) async {
  try {
    final response = await http.post(
      Uri.parse('$baseUrl/api/payment/create'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'amount': amount,
        'user_id': userId,
        'description': description,
        'return_url': 'https://your-app.flutterflow.app/payment-result'
      }),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        final paymentUrl = data['data']['payment_url'];
        
        // Launch payment URL
        if (await canLaunchUrl(Uri.parse(paymentUrl))) {
          await launchUrl(
            Uri.parse(paymentUrl),
            mode: LaunchMode.externalApplication,
          );
          return true;
        }
      }
    }
    
    return false;
  } catch (e) {
    print('Payment initiation error: $e');
    return false;
  }
}
```

### 5. Check Payment Status Custom Action

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

Future<Map<String, dynamic>?> checkPaymentStatus({
  required String orderId,
  required String baseUrl,
}) async {
  try {
    final response = await http.get(
      Uri.parse('$baseUrl/api/payment/status?order_id=$orderId'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        return data['data'];
      }
    }
    
    return null;
  } catch (e) {
    print('Payment status check error: $e');
    return null;
  }
}
```

### 6. Payment Result Page Setup

On your `PaymentResult` page:

1. **Add Text Widgets** for:
   - Order ID
   - Status
   - Amount
   - Payment Method

2. **Add Custom Action on Page Load:**
```dart
// Get URL parameters and check final status
final orderId = getUrlParameter('order_id');
final status = getUrlParameter('status');
final amount = getUrlParameter('amount');

// Update UI based on status
if (status == 'PAID') {
  // Show success message
  // Maybe confetti animation
} else if (status == 'FAILED') {
  // Show failure message  
  // Offer retry option
} else {
  // Check status via API for most current info
  final paymentData = await checkPaymentStatus(
    orderId: orderId,
    baseUrl: 'https://your-domain.com'
  );
  
  // Update UI based on API response
}
```

### 7. Simple Payment Widget

Create a reusable payment component:

**Widget Structure:**
```
Column
├── Text (Amount: ₹X.XX)
├── TextField (Description - Optional)
└── ElevatedButton (Pay Now)
    └── Action: initiatePayment()
```

**Pay Now Button Actions:**
1. **Custom Action:** `initiatePayment`
   - Parameters: amount, userId, description, baseUrl
2. **Show Snackbar:** "Redirecting to payment..."

### 8. Environment Configuration

In FlutterFlow, create app constants:

- `API_BASE_URL`: Your Laravel API URL
- `PAYMENT_RETURN_URL`: Your payment result page URL

### 9. Testing Workflow

1. **Sandbox Testing:**
   - Use test amounts (₹1, ₹10, etc.)
   - Test with sandbox Cashfree credentials
   - Verify return URL handling

2. **Payment Methods to Test:**
   - Card payments
   - UPI payments
   - Net banking
   - Wallet payments

### 10. Production Checklist

- [ ] Update API_BASE_URL to production
- [ ] Update Cashfree credentials to production
- [ ] Test payment flow end-to-end
- [ ] Verify webhook handling
- [ ] Test error scenarios
- [ ] Add loading states
- [ ] Add error handling
- [ ] Test on different devices

### Sample Payment Flow

```dart
// In your FlutterFlow action
Future<void> handlePaymentFlow() async {
  // 1. Show loading
  setState(() => isLoading = true);
  
  // 2. Create payment
  final success = await initiatePayment(
    amount: orderAmount,
    userId: currentUser.id,
    description: "Order #${orderNumber}",
    baseUrl: FFAppConstants.apiBaseUrl,
  );
  
  // 3. Handle result
  if (success) {
    // Payment URL launched successfully
    // User will be redirected back to app after payment
  } else {
    // Show error
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Failed to initiate payment'))
    );
  }
  
  setState(() => isLoading = false);
}
```

### Error Handling

Always handle these scenarios in FlutterFlow:

1. **Network Errors:** Show retry option
2. **Invalid Amount:** Validate before API call
3. **User Cancellation:** Handle gracefully
4. **Payment Failure:** Show clear error message
5. **Timeout:** Provide status check option

### URLs to Configure

Replace these with your actual URLs:

- **Laravel API:** `https://your-domain.com`
- **Payment Result:** `https://your-app.flutterflow.app/payment-result`
- **Success URL:** `https://your-app.flutterflow.app/success`
- **Error URL:** `https://your-app.flutterflow.app/error`

## 🔐 Production Configuration

Your Laravel API is now configured with **PRODUCTION** Cashfree credentials:

- **Environment:** Production
- **App ID:** Configured ✅
- **Secret Key:** Configured ✅

### Important Production Notes:

1. **Real Money Transactions:** All payments will be processed with real money
2. **Live Payment Methods:** Users can pay with real cards, UPI, net banking
3. **Production Webhooks:** Ensure your webhook URL is accessible from Cashfree servers
4. **SSL Required:** Your Laravel API must have a valid SSL certificate
5. **Testing:** Use small amounts (₹1-10) for initial testing

### Production Safety Checklist:

- [ ] SSL certificate installed on Laravel API domain
- [ ] Webhook URL accessible: `https://your-domain.com/api/payment/webhook`
- [ ] Return URL accessible: `https://your-domain.com/api/payment/callback`
- [ ] Test with small amounts first
- [ ] Monitor payment logs: `storage/logs/laravel.log`
- [ ] Set up proper error handling in FlutterFlow
- [ ] Configure proper success/failure pages

This setup provides a seamless payment experience where users just need to provide the amount, and everything else is handled automatically!
