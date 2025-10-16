# Cashfree Payment Gateway Integration for FlutterFlow

## Overview
This Laravel API provides a complete Cashfree payment gateway integration designed specifically for FlutterFlow applications. It handles payment creation, status tracking, webhooks, and redirects.

## Setup Instructions

### 1. Environment Configuration
Add these variables to your `.env` file:

```env
# Cashfree Configuration
CASHFREE_APP_ID=your_cashfree_app_id
CASHFREE_SECRET_KEY=your_cashfree_secret_key
CASHFREE_ENVIRONMENT=sandbox  # Change to 'production' for live

# FlutterFlow URLs (customize these)
FLUTTER_FLOW_SUCCESS_URL=https://your-app.flutterflow.app/payment-success
FLUTTER_FLOW_ERROR_URL=https://your-app.flutterflow.app/payment-error

# Database Configuration (if not already set)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 2. Install Required Packages
```bash
# Install HTTP client for API calls
composer require guzzlehttp/guzzle
```

### 3. Run Database Migrations
```bash
php artisan migrate
```

### 4. Cashfree Account Setup
1. Sign up at [Cashfree Dashboard](https://merchant.cashfree.com/)
2. Get your App ID and Secret Key from the dashboard
3. Configure webhook URL: `https://your-domain.com/api/payment/webhook`
4. Set return URL: `https://your-domain.com/api/payment/callback`

## API Endpoints

### 1. Create Payment
**POST** `/api/payment/create`

Creates a payment order and returns payment URL for redirection.

**Request Body:**
```json
{
  "amount": 100.50,
  "user_id": 1,
  "description": "Product purchase",
  "return_url": "https://your-app.flutterflow.app/payment-result"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment order created successfully",
  "data": {
    "order_id": "ORDER_1697454123_ABC123",
    "cf_order_id": "cf_order_id_from_cashfree",
    "payment_session_id": "session_id",
    "amount": 100.50,
    "currency": "INR",
    "payment_url": "https://sandbox.cashfree.com/pg/view/order/cf_order_id"
  }
}
```

### 2. Check Payment Status
**GET** `/api/payment/status?order_id=ORDER_1697454123_ABC123`

**Response:**
```json
{
  "success": true,
  "data": {
    "order_id": "ORDER_1697454123_ABC123",
    "status": "PAID",
    "amount": 100.50,
    "currency": "INR",
    "payment_method": "UPI",
    "paid_at": "2023-10-16T12:30:45.000000Z",
    "description": "Product purchase"
  }
}
```

### 3. Get User Payment History
**GET** `/api/payment/user-payments?user_id=1&limit=10&status=PAID`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "order_id": "ORDER_1697454123_ABC123",
      "amount": 100.50,
      "currency": "INR",
      "status": "PAID",
      "payment_method": "UPI",
      "description": "Product purchase",
      "created_at": "2023-10-16T12:25:45.000000Z",
      "paid_at": "2023-10-16T12:30:45.000000Z"
    }
  ]
}
```

### 4. Refund Payment
**POST** `/api/payment/refund`

**Request Body:**
```json
{
  "order_id": "ORDER_1697454123_ABC123",
  "refund_amount": 50.25,
  "refund_note": "Partial refund requested"
}
```

## FlutterFlow Integration

### Step 1: Create Payment
In FlutterFlow, create an API call action:

1. **Method:** POST
2. **URL:** `https://your-domain.com/api/payment/create`
3. **Body:**
```json
{
  "amount": [amount_variable],
  "user_id": [user_id_variable],
  "description": "Payment for order",
  "return_url": "https://your-app.flutterflow.app/payment-result"
}
```

### Step 2: Handle Response
After API call success:
1. Extract `payment_url` from response
2. Use "Launch URL" action to redirect user to payment page
3. The payment URL will handle the entire payment process

### Step 3: Handle Return
Create a page `/payment-result` in FlutterFlow to handle returns:
1. Get URL parameters: `order_id`, `status`, `amount`
2. Optionally call `/api/payment/status` to get detailed status
3. Show success/failure message based on status

### Payment Flow Example

```dart
// FlutterFlow Custom Action Example
Future<void> initiatePayment(double amount, int userId) async {
  try {
    final response = await http.post(
      Uri.parse('https://your-domain.com/api/payment/create'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'amount': amount,
        'user_id': userId,
        'description': 'Payment for order',
        'return_url': 'https://your-app.flutterflow.app/payment-result'
      }),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      if (data['success']) {
        // Launch payment URL
        await launchUrl(Uri.parse(data['data']['payment_url']));
      }
    }
  } catch (e) {
    print('Payment initiation failed: $e');
  }
}
```

## Payment Status Types

- **CREATED:** Payment order created, pending payment
- **PAID:** Payment completed successfully
- **FAILED:** Payment failed
- **CANCELLED:** Payment cancelled by user

## Webhook Handling

The system automatically handles Cashfree webhooks at `/api/payment/webhook` to update payment status in real-time.

## Security Features

1. **Signature Verification:** Webhooks are logged and processed securely
2. **Status Validation:** Payment status is verified with Cashfree API
3. **Database Logging:** All transactions are logged with full details
4. **Error Handling:** Comprehensive error handling and logging

## Testing

### Sandbox Test Cards
Use these test cards in sandbox mode:

**Successful Payment:**
- Card: 4111 1111 1111 1111
- CVV: Any 3 digits
- Expiry: Any future date

**Failed Payment:**
- Card: 4111 1111 1111 1112

### Test UPI
- UPI ID: `success@razorpay` (for success)
- UPI ID: `failure@razorpay` (for failure)

## Error Handling

The API returns consistent error responses:

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error information"
}
```

## Database Schema

The `payments` table stores:
- Order details and amounts
- Cashfree order IDs and responses
- Payment status and methods
- User associations
- Timestamps for tracking

## Support

For issues:
1. Check Laravel logs in `storage/logs/`
2. Verify Cashfree credentials
3. Ensure database connectivity
4. Check webhook URL accessibility

## Production Checklist

- [ ] Set `CASHFREE_ENVIRONMENT=production`
- [ ] Use production Cashfree credentials
- [ ] Update FlutterFlow return URLs
- [ ] Test webhook URL accessibility
- [ ] Configure SSL certificates
- [ ] Set up proper database backups
- [ ] Monitor payment logs
