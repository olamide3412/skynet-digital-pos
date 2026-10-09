# POS System — AI Credit Packs & Payment Integration (Flutterwave / Paystack) (Agent README)

**Prerequisite:** Requires Part 3 (AI Credit Metering) and Part 1 (Subscription, organization-scoped) to already exist. This document extends Part 3 — it does not replace or restructure it.

**Reconciliation note (read this first):** An earlier version of this document proposed a new `ai_credit_purchases` table. **That table does not get built.** The actual codebase already has a working top-up ledger — `ai_credit_topups` — built independently, with a richer design than originally assumed here (it already supports targeting either the whole organization or one specific branch, already has a `type` field distinguishing grant reasons, and already supports an optional `expires_at`). This document is now corrected to **extend that existing table** with the handful of payment-specific columns needed for gateway purchases, rather than introduce a second, competing ledger. Everywhere this document previously said `ai_credit_purchases`, it now says `ai_credit_topups`.

**Resolves two previously open questions from Part 3:**
- **Monthly reset mechanism:** resolved below (§2) — calculated on demand, no reset job needed.
- **Credit overage behavior:** resolved below (§3) — stays a hard block once both the monthly allocation and any purchased balance are exhausted, with purchasing more credits as the path forward instead of silent overage.

---

## 1. Goal

Let an organization purchase additional AI credit packs via **Flutterwave or Paystack**, with purchased credits added to a persistent balance that doesn't expire at month-end — on top of the existing monthly plan allocation from Part 3.

---

## 2. Monthly Allocation — How It's Calculated (No Reset Job)

- **Do not store a "credits remaining" counter that gets reset.** Instead, calculate remaining monthly allocation on demand:
  `Monthly Remaining = plan_capabilities.ai_credits_monthly − SUM(ai_credit_usage.credits_used WHERE used_at falls in the current calendar month)`
- This scopes exactly as Part 3 already defines: by organization for Starter's shared pool, by branch for Bronze/Silver/Gold's per-branch pools.
- **Reset happens automatically** the moment the calendar moves into a new month — the query window shifts, no scheduled job required, nothing that can silently fail to run.
- Aligns to the **calendar month** (1st to 1st), not each organization's individual plan-start date — simpler to reconcile, and necessary for §3 below to make sense across an organization with multiple branches/pools.

---

## 3. Purchased Credit Balance — Extending the Existing `ai_credit_topups` Table

### 3.1 Existing Table (already built — do not recreate)
`ai_credit_topups` already exists with: `id`, `organization_id`, `branch_id` (nullable — null means the organization-wide pool, set means a specific branch's pool), `credits`, `type` (string, currently used for values like `manual_grant` and `support_compensation`), `notes`, `granted_by` (nullable FK → users), `expires_at` (nullable — null means never expires), timestamps.

### 3.2 New Migration: Extend `ai_credit_topups` With Payment Columns
Add these columns via a new migration (never edit the original table's migration):

| New Column | Type | Notes |
|---|---|---|
| `credit_pack_id` | bigint, FK → ai_credit_packs, nullable | which pack was purchased — null for non-purchase grant types |
| `amount_paid` | decimal, nullable | null for non-purchase grant types (`manual_grant`, `support_compensation`) |
| `currency` | string, nullable | e.g., "NGN" |
| `gateway` | enum: flutterwave / paystack, nullable | null for non-purchase grant types |
| `gateway_reference` | string, nullable | the transaction reference from the gateway — used for idempotency, see §6.3 |
| `status` | enum: pending / successful / failed, default `successful` | existing manual-grant rows are implicitly `successful`; only gateway-sourced rows meaningfully use `pending`/`failed` |

- Use `type = 'addon_purchase'` (already anticipated as a valid value in the existing design) for every row created by a real gateway payment — this keeps manual grants (`manual_grant`, `support_compensation`) and paid purchases (`addon_purchase`) in one unified, auditable ledger instead of two separate tables that would need reconciling against each other.

### 3.3 Pooling — Matches the Existing `branch_id` Nullable Design (Corrected From Earlier Draft)
- An earlier draft of this document assumed purchased credits always go into one organization-wide balance regardless of plan. **That's incorrect and is now corrected** — the existing table already supports targeting a specific branch via `branch_id`, matching how the monthly allocation itself splits by branch for Bronze/Silver/Gold (per Part 3). A purchase should follow the same targeting logic already built for manual grants:
  - **Starter (account-wide pool):** a purchase always targets the organization (`branch_id = null`) — there's only one pool to add to.
  - **Bronze/Silver/Gold (per-branch pools):** a purchase targets the specific branch the client was in when they purchased (`branch_id` = that branch) — consistent with how each branch's monthly allocation is already tracked independently.
  - **Enterprise:** follow whatever the organization's configured pooling is (per Part 3's Enterprise handling), defaulting to organization-wide if unmetered/no specific branch pools are configured.
- **Consumption order (unchanged in principle):** when an AI action is triggered, check and consume from the monthly allocation first (§2); only draw from the matching top-up balance (organization-wide or branch-specific, per the pool it belongs to) once that specific pool's monthly allocation is exhausted — this already matches how `AiCreditService::getSummary()` is described as combining "Base Quota + Active Top-Up Credits."
- **Active top-ups** means `expires_at IS NULL OR expires_at > now()` — the existing `expires_at` column already supports both non-expiring and time-limited grants, so this document no longer needs a separate yes/no "should purchased credits expire" decision (see §9, updated).

---

## 4. Credit Pack Catalog

### 4.1 `ai_credit_packs` (new table)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `name` | string | e.g., "500 Credits", "2,000 Credits" |
| `credits` | integer | |
| `price` | decimal | |
| `currency` | string | |
| `is_active` | boolean | lets a pack be retired without deleting purchase history referencing it |
| `sort_order` | integer | |

- Managed by Platform Super Admin through a simple CRUD screen — same structural pattern already used for `pricing_plans` (Homepage Pricing README) — reuse that UI pattern rather than building a new one from scratch.

---

## 5. Purchase Flow

### 5.1 Who Can Purchase
- The organization's own admin (Branch Admin today, since existing clients have one branch each; Organization Admin once delegated per Organization Layer Phase 6) initiates a purchase — this is the client paying for their own add-on, not something Platform Super Admin does on their behalf in the normal flow (though Platform Super Admin can still manually grant bonus credits without payment — see §8).

### 5.2 Flow
1. Client views the credit pack catalog (§4) from within their AI Settings / Credits area, showing current monthly usage (§2) and top-up balance (§3) alongside available packs. If the organization's plan uses per-branch pools, this screen is viewed from within a specific branch's context, so the target branch is already unambiguous.
2. Client selects a pack and clicks **Purchase**.
3. Create a `pending` row in `ai_credit_topups` with `type = 'addon_purchase'`, `credit_pack_id` set, `branch_id` set per the targeting rule in §3.3, and `status = 'pending'`.
4. Redirect to the selected gateway's hosted checkout (Flutterwave or Paystack — see §6 for how the choice is made) with the pack's price and a reference tying back to that `ai_credit_topups` row.
5. **Do not credit the account on the redirect-back alone** — a client reaching the "success" return URL is not proof of payment (it can be spoofed or interrupted). Credits are only counted as available once the webhook in §6.3 confirms payment server-side and flips `status` to `successful`.
6. Show a "Payment processing — your credits will appear shortly" state immediately after redirect-back, and reflect the real balance once the webhook completes (near-instant in practice, but must not be assumed instant).

---

## 6. Payment Gateway Integration — Multi-Provider, Same Pattern as the AI Provider Layer

### 6.1 Abstraction Layer
Mirror the exact pattern already used for the AI provider abstraction (AI Features README §0): one internal interface, one adapter per gateway.

- `PaymentGatewayInterface` with methods like `initiateCheckout()`, `verifyWebhookSignature()`, `parseWebhookPayload()`.
- `FlutterwaveGateway` and `PaystackGateway` adapter classes implementing that interface.
- Adding a third gateway later means writing one new adapter, not touching the purchase flow itself.

### 6.2 Gateway Settings
- A **Payment Settings** screen (Platform Super Admin, system-wide — same pattern as Mail Settings and AI Settings): enter Flutterwave and/or Paystack API keys (public + secret), store secrets **encrypted**, same as every other credential in this project.
- **Support both simultaneously** — don't force a single active gateway. Let the client choose which one to pay with at checkout (if both are configured), similar in spirit to not locking the AI features to one provider.
- Test connection / sandbox mode toggle per gateway, so this can be verified safely before going live with real payments.

### 6.3 Webhook Handling — Critical
- Each gateway posts a webhook to a dedicated, unguessable endpoint (e.g., `/webhooks/payments/flutterwave`, `/webhooks/payments/paystack`) when a payment completes.
- **Verify the webhook signature server-side** using each gateway's documented verification method — never trust an unverified payload, since anyone could otherwise POST a fake "successful payment" event.
- **Idempotency is mandatory:** match the incoming webhook to the `ai_credit_topups` row via `gateway_reference`. If that row's `status` is already `successful`, do nothing (webhooks can and do fire more than once for the same event) — never credit the account twice for one payment.
- On verified success: set `status = 'successful'` on the existing pending row (created in §5.2 step 3) — **no new row is created at this step**, the pending row simply gets confirmed. Since `credits` was already set on that row at creation, the moment `status` flips to `successful` it becomes a normal, fully active top-up — `AiCreditService::getSummary()` just needs to only count `ai_credit_topups` rows where `status` is `successful` (or null/not applicable, for pre-existing manual-grant rows that predate this `status` column).
- On a failed/declined payment: set `status = 'failed'` on that same row. No credits are counted as available (the existing summary calculation should exclude non-successful rows). Surface this clearly to the client rather than leaving them wondering.

---

## 7. Where It Surfaces

- A **Credits** area (client-facing — Branch Admin today, Organization Admin later) showing: current monthly allocation and how much is used/remaining (§2), top-up balance (§3), and the pack catalog with a Purchase button.
- A **top-up history** list (organization- or branch-scoped, matching §3.3's targeting) — pack (if applicable), amount paid (if applicable), gateway (if applicable), date, status — so a client can see their own payment and grant history in one place. This is the same ledger table already planned for the Platform Super Admin side (§8) — both views read from the same `ai_credit_topups` table, just filtered differently.
- Platform Super Admin sees this across all organizations, consistent with their existing oversight role elsewhere in this project.

---

## 8. Manual Grant (No Payment) — Already Built, No New Work Needed Here

- The Platform Super Admin "Grant bonus credits" capability — including the Organization/Branch targeting dropdown, credits amount, expiry choice, and reason/notes — is **already planned and largely designed** (per the existing `ai_credit_topups` schema and the described SuperAdmin UI with a "Credit Target," "Credits to Add," "Expiration," and "Reason/Note" form). This document does not need to add anything here — it only needed to make sure the payment-driven path (§3–§6) writes into the **same table** this manual-grant flow already uses, which is now corrected above.
- The only connection point worth confirming: the manual-grant UI's "Expiration" choice ("Never Expires" / "Expires at end of current month") maps directly to `expires_at` being `null` or set to end-of-month — no new field needed, this already lines up with the existing column.

---

## 9. Open Questions

1. **Default expiry for purchased packs:** Since `expires_at` already exists and supports either option per-row, should an `addon_purchase` default to never-expiring, or to some period (e.g., 12 months)? This is now just a default-value decision for the purchase flow, not a schema question — manual grants already let Platform Super Admin choose this per-grant, and purchases could either always use one fixed default or let the client see the expiry before buying.
2. **Currency handling:** Is NGN the only currency needed for now, or should credit packs support multiple currencies (relevant if this product is ever sold outside Nigeria)? Assuming NGN-only for v1 unless stated otherwise.
3. **Refunds:** If a payment succeeds but the client disputes it or a refund is issued through the gateway later, should there be a corresponding "claw back" of granted credits, or is that handled manually (Platform Super Admin adjusting the balance via the existing manual-grant mechanism, just with a negative `credits` value or a separate deduction path) rather than automated? Recommend manual handling for now — refund automation adds real complexity for a rare case.
4. **Purchase permission scope:** Confirming §5.1 — should Branch Admin be allowed to purchase credits today (since each current organization has only one branch, effectively acting as the organization's admin), or should this wait until Organization Admin exists formally (Organization Layer Phase 6) so purchasing authority sits unambiguously at the organization level rather than blurred with branch-level access?
5. **Existing `type` enum values:** Confirming `manual_grant`, `support_compensation`, and now `addon_purchase` are the complete intended set of `type` values, or whether additional types are expected later (e.g., a distinct type for a referral bonus or a promotional grant) — not blocking, just worth listing explicitly somewhere in code as the source of truth now that a payment-driven type is being added to the existing two.
