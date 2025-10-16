# 🚀 Production Deployment Guide

## ✅ Production Credentials Configured

Your Laravel application is now configured with **LIVE** Cashfree production credentials:

- **App ID:** `10918331c4c305ee1cd321490603381901`
- **Environment:** `production`
- **Status:** Ready for live transactions

## 🔐 Security Reminders

### Critical Security Points:
1. **Never commit production credentials to Git** - They are only in your local `.env` file
2. **Production credentials are for LIVE money transactions**
3. **Always use HTTPS in production**
4. **Monitor all transactions carefully**

## 📋 Pre-Production Checklist

### 1. Server Requirements
- [ ] HTTPS/SSL certificate installed
- [ ] PHP 8.1+ installed
- [ ] MySQL/MariaDB running
- [ ] Web server (Apache/Nginx) configured
- [ ] Laravel requirements met

### 2. Domain Setup
- [ ] Domain pointing to your server
- [ ] SSL certificate valid
- [ ] API endpoints accessible via HTTPS

### 3. Environment Configuration
- [ ] Upload `.env` file to production server (never commit it)
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure proper `APP_URL`

### 4. Database Setup
- [ ] Create production database
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Verify all tables created

### 5. Cashfree Dashboard Configuration
- [ ] Add webhook URL: `https://your-domain.com/api/payment/webhook`
- [ ] Add return URL: `https://your-domain.com/api/payment/callback`
- [ ] Verify production credentials are active
- [ ] Set up notification preferences

## 🧪 Production Testing

### Test with Small Amounts First

```bash
# Test payment creation
curl -X POST https://your-domain.com/api/payment/create \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 1.00,
    "user_id": 1,
    "description": "Production test payment - ₹1"
  }'
```

### Payment Methods to Test:
1. **UPI:** Test with your UPI ID
2. **Cards:** Use your personal card with small amount
3. **Net Banking:** Test with your bank
4. **Wallets:** Test with Paytm/PhonePe if available

### Expected Flow:
1. API creates payment order
2. Returns payment URL
3. User redirects to Cashfree payment page
4. User completes payment
5. Cashfree sends webhook to your API
6. User redirects back to your FlutterFlow app
7. Status shows "PAID" in database

## 🔍 Monitoring & Logging

### Check Payment Status:
```bash
# Check specific payment
curl "https://your-domain.com/api/payment/status?order_id=ORDER_ID"

# Check user payments
curl "https://your-domain.com/api/payment/user-payments?user_id=1"
```

### Monitor Logs:
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Web server logs
tail -f /var/log/nginx/error.log  # For Nginx
tail -f /var/log/apache2/error.log  # For Apache
```

## 🚨 Emergency Procedures

### If Something Goes Wrong:

1. **Payment Stuck:** Check webhook delivery in Cashfree dashboard
2. **Wrong Amount:** Use refund API endpoint
3. **User Complaints:** Check payment status in database and Cashfree dashboard
4. **System Down:** Temporarily disable payment creation

### Refund Process:
```bash
curl -X POST https://your-domain.com/api/payment/refund \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "ORDER_ID",
    "refund_amount": 100.00,
    "refund_note": "Customer requested refund"
  }'
```

## 📱 FlutterFlow Production Setup

### Update FlutterFlow Constants:
- `API_BASE_URL`: `https://your-domain.com`
- `PAYMENT_RETURN_URL`: `https://your-app.flutterflow.app/payment-result`

### Test FlutterFlow Integration:
1. Build FlutterFlow app
2. Test payment flow with ₹1
3. Verify success/failure handling
4. Check return URL parameters

## 🎯 Go-Live Checklist

### Final Verification:
- [ ] Test payment with ₹1 - SUCCESS
- [ ] Test payment cancellation - HANDLED
- [ ] Test payment failure - HANDLED  
- [ ] Webhook receiving data - VERIFIED
- [ ] Database updating correctly - VERIFIED
- [ ] FlutterFlow app handling responses - VERIFIED
- [ ] SSL certificate valid - VERIFIED
- [ ] All error scenarios tested - VERIFIED

### Launch Day:
- [ ] Monitor logs continuously
- [ ] Have Cashfree dashboard open
- [ ] Keep refund API ready
- [ ] Notify users about new payment feature
- [ ] Have support contact ready

## 📞 Support Contacts

### Cashfree Support:
- **Email:** support@cashfree.com
- **Phone:** +91-80-61065555
- **Dashboard:** https://merchant.cashfree.com/

### Emergency Actions:
1. **Disable payments:** Comment out routes in `routes/api.php`
2. **Temporary maintenance:** Set `APP_ENV=maintenance`
3. **Contact Cashfree:** For payment gateway issues

## 🎉 You're Ready for Production!

Your Cashfree payment integration is now live and ready to handle real transactions. Start with small test payments and gradually scale up as you gain confidence.

**Remember:** Every payment is real money in production mode!

Good luck with your launch! 🚀
