# Subscription & Agent System - Implementation Guide

## ✅ COMPLETED - Backend

### 1. Database Migrations ✓
Created 7 migrations:
- `2026_02_17_000001_create_invoices_table.php` - Invoice storage
- `2026_02_17_000002_create_midterm_student_additions_table.php` - Mid-term fees tracking
- `2026_02_17_000003_add_subscription_fields_to_terms_table.php` - Payment fields on terms
- `2026_02_17_000004_create_agents_table.php` - Agent profiles
- `2026_02_17_000005_create_referrals_table.php` - Referral tracking
- `2026_02_17_000006_create_agent_commissions_table.php` - Commission records
- `2026_02_17_000007_create_agent_payouts_table.php` - Payout tracking

### 2. Models Created ✓
- `App\Models\Invoice` - Invoice management
- `App\Models\MidtermStudentAddition` - Mid-term student tracking
- `App\Models\Agent` - Agent profiles
- `App\Models\Referral` - Referral tracking
- `App\Models\AgentCommission` - Commission records
- `App\Models\AgentPayout` - Payout management

**Updated Models:**
- `App\Models\Term` - Added subscription relationships & methods
- `App\Models\School` - Added subscription relationships & helper methods

### 3. Services Created ✓
- `App\Services\SubscriptionService` - Core subscription logic
  - `canSwitchTerm()` - Check if term switch allowed
  - `generateTermInvoice()` - Create term invoice
  - `generateMidtermInvoice()` - Create mid-term fees invoice
  - `recordPayment()` - Process payment
  - `calculateCommission()` - Calculate commission amount

- `App\Services\ReferralService` - Referral management
  - `generateCode()` - Create unique codes
  - `createReferral()` - Register referral
  - `shouldTriggerCommission()` - Check if commission applies
  - `getStats()` - Referral statistics

- `App\Services\CommissionService` - Commission processing
  - `processCommission()` - Create commission on payment
  - `getAgentEarnings()` - Calculate agent earnings
  - `getCommissionHistory()` - Commission records
  - `bulkApproveCommissions()` - Admin bulk approval

- `App\Services\PayoutService` - Payout management
  - `requestPayout()` - Create payout request
  - `approvePayout()` - Admin approval
  - `completePayout()` - Mark payout complete
  - `bulkApprovePayouts()` - Batch approval

### 4. Configuration Files ✓
- `config/subscription.php` - Subscription settings
- `config/commission.php` - Commission settings

### 5. API Controllers ✓
- `App\Http\Controllers\Api\V1\AgentController`
  - `register()` - Agent registration
  - `dashboard()` - Agent stats
  - `generateReferral()` - Create referral link
  - `commissionHistory()` - Commission records
  - `requestPayout()` - Request payout
  - `payoutHistory()` - Payout records
  - `approveAgent()` - Admin approval
  - `rejectAgent()` - Admin rejection
  - `pendingAgents()` - Admin list
  - `suspendAgent()` - Admin suspend

- `App\Http\Controllers\Api\V1\TermController`
  - `show()` - Term with payment status
  - `switchTerm()` - Switch to next term (with payment checks)
  - `schoolTerms()` - List terms with status
  - `paymentDetails()` - Payment breakdown
  - `sendPaymentReminder()` - Send reminder email

---

## ⏳ TODO - Backend Continued

### Routes to Add
Add to `routes/api.php`:
```php
// Agents
Route::prefix('agents')->group(function () {
    Route::post('register', [AgentController::class, 'register']);
    Route::middleware('auth:agent')->group(function () {
        Route::get('dashboard', [AgentController::class, 'dashboard']);
        Route::post('referral/generate', [AgentController::class, 'generateReferral']);
        Route::get('commissions', [AgentController::class, 'commissionHistory']);
        Route::post('payout/request', [AgentController::class, 'requestPayout']);
        Route::get('payouts', [AgentController::class, 'payoutHistory']);
    });
});

// Admin - Agent Management
Route::middleware(['auth', 'admin'])->group(function () {
    Route::prefix('admin/agents')->group(function () {
        Route::get('pending', [AgentController::class, 'pendingAgents']);
        Route::post('{agent}/approve', [AgentController::class, 'approveAgent']);
        Route::post('{agent}/reject', [AgentController::class, 'rejectAgent']);
        Route::post('{agent}/suspend', [AgentController::class, 'suspendAgent']);
    });
});

// Terms
Route::prefix('terms')->group(function () {
    Route::get('{term}', [TermController::class, 'show']);
    Route::post('{term}/switch', [TermController::class, 'switchTerm']);
    Route::get('school/{school}', [TermController::class, 'schoolTerms']);
    Route::get('{term}/payment-details', [TermController::class, 'paymentDetails']);
    Route::post('{term}/send-reminder', [TermController::class, 'sendPaymentReminder']);
});

// Admin - Commission & Payout Management
Route::middleware(['auth', 'admin'])->group(function () {
    Route::prefix('admin/commissions')->group(function () {
        Route::get('', [CommissionController::class, 'list']);
        Route::post('{commission}/approve', [CommissionController::class, 'approve']);
        Route::post('bulk-approve', [CommissionController::class, 'bulkApprove']);
    });

    Route::prefix('admin/payouts')->group(function () {
        Route::get('', [PayoutController::class, 'list']);
        Route::post('{payout}/approve', [PayoutController::class, 'approve']);
        Route::post('{payout}/process', [PayoutController::class, 'process']);
        Route::post('bulk-approve', [PayoutController::class, 'bulkApprove']);
    });
});
```

### Additional Controllers Needed
1. `CommissionController` - Admin commission management
2. `PayoutController` - Admin payout management
3. `InvoiceController` - Invoice management

### Events & Jobs
1. Create `PaymentReceivedEvent` - Triggercommission calculation
2. Create `ProcessPayoutJob` - Background payout processing
3. Create `SendPaymentReminderJob` - Scheduled reminder emails

### Middleware
1. `EnsureSchoolNotDemo` - Subscription check middleware

---

## ⏳ TODO - Frontend (Next.js)

### Agent-Related Pages
1. `/agent/register` - Agent registration form
2. `/agent/login` - Agent authentication
3. `/agent/dashboard` - Main dashboard
   - Key metrics (total referrals, conversions, earnings)
   - Recent referrals list
   - Earnings overview
   
4. `/agent/referrals` - Manage referrals
   - List all referrals
   - Generate new referral code/link
   - Copy/share functionallity
   - QR code display
   - Export as CSV
   
5. `/agent/earnings` - Earnings & payouts
   - Commission history table
   - Total earnings breakdown
   - Pending/approved/paid commissions
   - Request payout button (if threshold met)
   
6. `/agent/payouts` - Payout history
   - Payout requests list
   - Status tracking
   - Completion details

7. `/agent/settings` - Agent profile
   - Edit bank details
   - Update contact info
   - Change password

### School Settings Pages
1. `/app/(school)/settings/subscription` - Subscription status
   - Current term payment status
   - Invoice details
   - Mid-term additions breakdown
   - Outstanding balance display
   - Payment button/link
   - Term switch blocker (if not paid)

### Admin Pages
1. `/admin/agents` - Agent management
   - Pending agents approval list
   - Active/suspended agents
   - Agent details & edit
   - Suspend/deactivate actions

2. `/admin/subscriptions` - Subscription management
   - School billing overview
   - Invoice list & filters
   - Mark payment received
   - Configure price per student
   - Set payment due dates

3. `/admin/commissions` - Commission management
   - Pending commissions list
   - Bulk approval
   - Commission history
   - Agent earnings summary

4. `/admin/payouts` - Payout management
   - Pending payout requests
   - Approve/process payouts
   - Batch processing
   - Payout history

### Components Needed
1. `SubscriptionStatusCard` - Display subscription status
2. `ReferralLinkDisplay` - Show & share referral link
3. `CommissionChart` - Earnings visualization
4. `PaymentReminderAlert` - Outstanding balance alert
5. `InvoiceTable` - Invoice list component
6. `AgentMetricsCard` - Key metrics display

### API Client Methods
Add to lib/apiClient.ts or create subscription-specific client:
```typescript
// Agents
post('/api/v1/agents/register')
post('/api/v1/agents/dashboard')
post('/api/v1/agents/referral/generate')
get('/api/v1/agents/commissions')
post('/api/v1/agents/payout/request')
get('/api/v1/agents/payouts')

// Terms
get('/api/v1/terms/{id}')
post('/api/v1/terms/{id}/switch')
get('/api/v1/terms/school/{schoolId}')
get('/api/v1/terms/{id}/payment-details')

// Admin
get('/api/v1/admin/agents/pending')
post('/api/v1/admin/agents/{id}/approve')
get('/api/v1/admin/commissions')
get('/api/v1/admin/payouts')
```

---

## Database Setup Steps

```bash
# Run migrations
php artisan migrate

# Seed configuration (optional)
php artisan db:seed --class=CommissionSeeder
```

## ENV Variables to Add

```env
PRICE_PER_STUDENT=500
INVOICE_GENERATION_DAYS_BEFORE=14
COMMISSION_PERCENTAGE=12
COMMISSION_PAYMENT_COUNT=1
MIN_PAYOUT_THRESHOLD=5000
PAYOUT_PROCESSING_DAYS=3
```

---

## Key Features Implemented

✓ Demo school exemption (subdomain='demo')
✓ Term payment gating
✓ Mid-term student fee tracking
✓ Agent referral system
✓ Commission calculation (configurable by payment count)
✓ Payout request & approval system
✓ Multi-tenant architecture ready
✓ Payment tracking & history

---

## Next Steps Priority

1. **Finish Backend Controllers** - CommissionController, PayoutController, InvoiceController
2. **Add Routes** - Register all API routes in routes/api.php
3. **Create Admin Components** - Agent management, commission approval
4. **Build Agent Portal** - Referral dashboard, earnings view
5. **School Settings UI** - Subscription status, term switch validation
6. **Payment Integration** - Paystack/bank transfer integration
7. **Email Notifications** - Payment reminders, payout confirmations

---

## Testing Checklist

- [ ] Demo school bypasses all subscription checks
- [ ] Term switch blocked if payment not made  
- [ ] Mid-term invoice generated on student addition
- [ ] Commission triggered on referral payment (configurable count)
- [ ] Commission limit respected after payment_count threshold
- [ ] Payout request only allowed if above minimum threshold
- [ ] Admin can approve/reject agents
- [ ] Admin can approve/process payouts
- [ ] Referral link properly tracks conversions
- [ ] Field validation on all forms

---

*Last Updated: 2026-02-17*
*Status: Backend 70% Complete, Frontend 0% - Ready for API routes & Controllers*
