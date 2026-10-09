# AGENT README: Organization Portal Subscription Plan Renewal & Upgrade

> **Status:** Architecture & Implementation Plan  
> **Target Path:** Organization Portal (`/org/subscription`)  
> **Gateways Supported:** Paystack & Flutterwave (via `PaymentService` & `PaymentConfigService`)  
> **Production Safety:** Strictly additive changes. **NEVER modify existing database migration files.** All new schema updates must use a new timestamped migration.

---

## 1. Executive Summary

This document specifies the end-to-end implementation plan for allowing Organization Administrators to **renew** their existing subscription plan or **upgrade** to higher pricing tiers directly from the Organization Portal (`/org/subscription`).

The implementation builds on the unified payment architecture recently introduced for AI Credit Packs, leveraging:
- `PaymentService::initiatePlanSubscriptionCheckout()`
- Unified webhook processing (`/webhooks/payments/flutterwave` & `/webhooks/payments/paystack`)
- Idempotent, race-condition-free confirmation using row-level database locking (`lockForUpdate()`)
- Automatic CSRF exemptions for payment webhooks

---

## 2. Business Rules & Logic

### 2.1 Renewal vs. Upgrade Definitions
1. **Plan Renewal (`type = 'renewal'`)**:
   - Extends the organization's existing plan by **+1 calendar year** (365 days).
   - **Renewal Date Calculation**:
     - If renewed *before* expiry (active or expiring soon): `new_renewal_date = current_renewal_date + 1 year`.
     - If renewed *after* expiry (past due or grace period): `new_renewal_date = now() + 1 year`.
   - **Price**: Uses the plan's configured `renewal_price` (fallback to `first_year_price` if renewal price is null).
   - **Status**: Resets subscription `status` to `'active'`.

2. **Plan Upgrade (`type = 'upgrade'`)**:
   - Immediately assigns the new `pricing_plan_id` to the organization.
   - Immediately unlocks the higher tier's capabilities (higher branch limits, more users per branch, ecommerce integrations, accounting module, increased monthly AI credit quota).
   - Sets `started_at = now()` and `renewal_date = now() + 1 year`.
   - **Price**: Uses the target plan's `first_year_price` (or prorated price if proration toggle is enabled).

### 2.2 Payment & Idempotency Rules
- Every payment attempt receives a globally unique reference: `sub_{orgId}_{planId}_{type}_{timestamp}_{uniqid}`.
- Every payment row is tracked in a dedicated `subscription_payments` table with status `pending`, `successful`, or `failed`.
- **Zero Double-Billing / Double-Extension**:
  - Processing is guarded by `DB::transaction()` and `lockForUpdate()`.
  - If a webhook fires multiple times, or if the user browser callback and webhook land at the exact same moment, the first process marks the payment `successful` and extends the subscription; subsequent executions detect `status === 'successful'` and terminate early.

---

## 3. Database Architecture (New Migration)

> [!IMPORTANT]
> **Production Rule:** Never touch existing migrations. Create a new timestamped migration:
> `database/migrations/YYYY_MM_DD_HHMMSS_create_subscription_payments_table.php`

### Schema: `subscription_payments`
```sql
CREATE TABLE `subscription_payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organization_id` BIGINT UNSIGNED NOT NULL,
    `pricing_plan_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `type` ENUM('renewal', 'upgrade') NOT NULL,
    `amount_paid` DECIMAL(12, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'NGN',
    `gateway` VARCHAR(50) NOT NULL, -- 'paystack' or 'flutterwave'
    `gateway_reference` VARCHAR(191) NOT NULL UNIQUE,
    `gateway_transaction_id` VARCHAR(191) NULL,
    `status` ENUM('pending', 'successful', 'failed') NOT NULL DEFAULT 'pending',
    `period_months` INT UNSIGNED NOT NULL DEFAULT 12,
    `metadata` JSON NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`pricing_plan_id`) REFERENCES `pricing_plans`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_sub_payments_org_status` (`organization_id`, `status`),
    INDEX `idx_sub_payments_ref` (`gateway_reference`)
);
```

---

## 4. Backend Implementation Flow

### 4.1 New / Updated Files

| Component | File Path | Purpose |
|---|---|---|
| **Model** | `app/Models/SubscriptionPayment.php` | Eloquent model with relations (`organization`, `pricingPlan`, `user`) and query scopes. |
| **Service** | `app/Services/Subscription/SubscriptionBillingService.php` | Handles price calculation, pending record creation, atomic confirmation, and history recording. |
| **Payment Service** | `app/Services/Payment/PaymentService.php` | Connect webhook dispatcher for `sub_` reference to `SubscriptionBillingService::confirmPayment()`. |
| **Controller** | `app/Http/Controllers/Org/OrgSubscriptionController.php` | Org Portal actions: index (`/org/subscription`), initiate checkout (`POST /org/subscription/checkout`), return callback (`GET /org/subscription/callback`). |
| **Routes** | `routes/web.php` | Register `/org/subscription` group inside `auth:web` + `org.admin` middleware. |

---

### 4.2 Step-by-Step Execution Logic

#### Step 1: Initiate Checkout (`OrgSubscriptionController@checkout`)
```php
$validated = $request->validate([
    'pricing_plan_id' => 'required|exists:pricing_plans,id',
    'type'            => 'required|in:renewal,upgrade',
    'gateway'         => 'required|in:paystack,flutterwave',
]);

$org = Auth::user()->organization;
$plan = PricingPlan::findOrFail($validated['pricing_plan_id']);

// Calculate amount (parses currency strings like "₦300,000 / year" into float 300000.00)
$amount = SubscriptionBillingService::calculatePrice($org, $plan, $validated['type']);

// Create pending SubscriptionPayment record & generate reference
$paymentRecord = SubscriptionBillingService::createPendingPayment(
    org: $org,
    plan: $plan,
    type: $validated['type'],
    amount: $amount,
    gateway: $validated['gateway'],
    user: Auth::user()
);

// Initiate hosted checkout via Flutterwave or Paystack
$checkout = PaymentService::initiatePlanSubscriptionCheckout(
    organization: $org,
    plan: $plan,
    gatewayName: $validated['gateway'],
    amount: $amount,
    type: $validated['type'],
    user: Auth::user(),
    callbackUrl: route('org.subscription.callback', ['reference' => $paymentRecord->gateway_reference])
);

return Inertia::location($checkout['checkout_url']);
```

#### Step 2: Confirmation Service (`SubscriptionBillingService::confirmPayment()`)
```php
public static function confirmPayment(string $reference, ?string $txId = null, array $metadata = []): ?SubscriptionPayment
{
    return DB::transaction(function () use ($reference, $txId, $metadata) {
        $payment = SubscriptionPayment::where('gateway_reference', $reference)
            ->lockForUpdate()
            ->first();

        if (!$payment) {
            return null;
        }

        // Idempotency: if already processed, exit without duplicating
        if ($payment->status === 'successful') {
            return $payment;
        }

        $org = $payment->organization;
        $plan = $payment->pricingPlan;
        $currentSub = $org->subscription;

        // Calculate new renewal date
        if ($payment->type === 'renewal') {
            $baseDate = ($currentSub && $currentSub->renewal_date && $currentSub->daysUntilRenewal() >= 0)
                ? Carbon::parse($currentSub->renewal_date)
                : Carbon::today();
            $newRenewalDate = $baseDate->copy()->addMonths($payment->period_months);
            $startedAt = $currentSub?->started_at ?? Carbon::today();
        } else { // Upgrade
            $startedAt = Carbon::today();
            $newRenewalDate = Carbon::today()->addMonths($payment->period_months);
        }

        // 1. Update active Subscription row
        Subscription::updateOrCreate(
            ['organization_id' => $org->id],
            [
                'pricing_plan_id' => $plan->id,
                'started_at'      => $startedAt,
                'renewal_date'    => $newRenewalDate,
                'status'          => 'active',
                'changed_by'      => $payment->user_id,
                'notes'           => "Online {$payment->type} via " . ucfirst($payment->gateway) . " (Ref: {$reference})",
            ]
        );

        // 2. Insert into SubscriptionHistory ledger
        SubscriptionHistory::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $plan->id,
            'started_at'      => $startedAt,
            'renewal_date'    => $newRenewalDate,
            'status'          => 'active',
            'changed_by'      => $payment->user_id,
            'notes'           => "Payment confirmed Ref: {$reference}",
        ]);

        // 3. Mark payment as successful
        $payment->update([
            'status'                 => 'successful',
            'gateway_transaction_id' => $txId,
            'metadata'               => array_merge($payment->metadata ?? [], $metadata),
        ]);

        // 4. Log to OrganizationActivityLog
        OrganizationActivityLog::create([
            'organization_id' => $org->id,
            'actor_user_id'   => $payment->user_id,
            'action'          => 'subscription_' . $payment->type . '_completed',
            'details'         => [
                'plan_name'        => $plan->name,
                'amount_paid'      => $payment->amount_paid,
                'gateway'          => $payment->gateway,
                'new_renewal_date' => $newRenewalDate->toDateString(),
            ],
            'created_at'      => now(),
        ]);

        return $payment;
    });
}
```

#### Step 3: Webhook Integration in `PaymentService.php`
Wire the existing webhook dispatch:
```php
if (str_starts_with($reference, 'sub_') || ($parsed['metadata']['payment_type'] ?? '') === 'plan_subscription') {
    if ($status === 'successful') {
        $confirmed = SubscriptionBillingService::confirmPayment(
            $reference,
            $parsed['transaction_id'],
            $parsed['metadata']
        );
        return [
            'success'     => true,
            'action'      => 'subscription_confirmed',
            'reference'   => $reference,
            'status_code' => 200,
        ];
    } elseif ($status === 'failed') {
        SubscriptionBillingService::failPayment($reference, 'Payment declined by gateway.');
        return [
            'success'     => true,
            'action'      => 'subscription_failed',
            'reference'   => $reference,
            'status_code' => 200,
        ];
    }
}
```

---

## 5. Frontend UI Design (`resources/js/Pages/Org/Subscription/Index.vue`)

### 5.1 Sections on the Page
1. **Hero Plan Status Card**:
   - Current Plan Badge (`Starter`, `Standard`, `Silver`, `Gold`, `Enterprise`).
   - Circular/linear progress of days remaining before renewal.
   - Status indicators: `Active` (Green), `Expiring in X days` (Amber), `Grace Period` (Orange), or `Expired` (Red).
   - "Quick Renew Plan" CTA button.

2. **Feature Limits & Usage Metrics**:
   - Branches currently used vs. max branches allowed.
   - Users currently created vs. max users per branch.
   - Module capability checkmarks (E-commerce sync, Accounting, Multi-branch, AI Assistant).

3. **Available Plans Comparison Matrix (For Upgrades)**:
   - Cards showing each active higher plan with pricing (`first_year_price`, `renewal_price`).
   - "Upgrade to [Plan]" button which launches the checkout modal.
   - Current plan shows "Current Plan" disabled badge.

4. **Interactive Checkout Modal**:
   - Shows order summary: Action (`Renew Plan` or `Upgrade to Gold Plan`), Duration (`1 Year`), Amount to pay (`₦...`).
   - Payment Gateway Selector:
     - **Paystack** (Cards, Bank Transfer, USSD, Apple Pay)
     - **Flutterwave** (Cards, Mobile Money, Bank Transfer, Barter)
     - Shows dynamic badges based on enabled gateways in SuperAdmin settings.
   - Secure checkout button: redirects to hosted checkout URL.

5. **Subscription & Payment History Table**:
   - Unified list of previous renewals, upgrades, and invoices.
   - Columns: Date, Plan, Action, Amount, Gateway, Reference, Status (`Successful`, `Pending`, `Failed`).

---

## 6. Security & Anti-Fraud Measures

1. **CSRF Exemption**: Webhook endpoints `/webhooks/payments/*` are already exempted in `bootstrap/app.php`.
2. **Gateway Signature Verification**: All webhooks verified via HMAC-SHA512 (Paystack) and Secret Hash header (Flutterwave) before processing.
3. **Price Tampering Prevention**: The frontend never supplies the transaction amount. The amount is strictly looked up and calculated on the server from the `pricing_plans` database table.
4. **Tenant Isolation**: Organizations can only view and renew their own subscription (`Auth::user()->organization_id`).

---

## 7. Testing & Verification Checklist

- [ ] **Migration Check**: Run `php artisan migrate` with new migration; ensure zero alteration of existing tables.
- [ ] **Price Parsing Test**: Unit tests ensuring strings like `"₦450,000"`, `"300000 / year"`, and formatted currencies parse accurately to float values.
- [ ] **Renewal Flow**: Test renewing active plan adds exactly 365 days from current renewal date.
- [ ] **Expired Renewal Flow**: Test renewing expired plan adds 365 days from today.
- [ ] **Upgrade Flow**: Test upgrading immediate increases branch/user/feature capabilities.
- [ ] **Webhook Idempotency Test**: Test sending duplicate Paystack & Flutterwave webhooks produces zero duplicate subscription history rows or double extensions.
- [ ] **Frontend Build**: Verify `npm run build` compiles Vue components without errors.

---

## 8. Summary of Implementation Steps for Next Phase

Once approved by the user, execution will follow these exact steps:
1. Create new migration for `subscription_payments` table & run `php artisan migrate`.
2. Create `SubscriptionPayment` model with relations and status scopes.
3. Create `SubscriptionBillingService` containing price parsing, pending record creation, and idempotent confirmation logic.
4. Connect webhook handling in `PaymentService.php` and `PaymentWebhookController.php`.
5. Create `OrgSubscriptionController.php` with index, checkout, and callback methods.
6. Register `/org/subscription` routes in `routes/web.php` and add navigation item to `OrgLayout.vue`.
7. Build `resources/js/Pages/Org/Subscription/Index.vue` with plan status card, upgrade grid, modal, and billing history.
8. Write comprehensive automated test suite `tests/Feature/OrgSubscriptionPaymentTest.php`.
9. Build frontend assets with `npm run build` and run complete test suite.
