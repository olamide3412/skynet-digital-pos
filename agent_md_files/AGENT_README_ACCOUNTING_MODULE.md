# POS System — Double-Entry Accounting Module (Agent README)

> **Status:** Specification — decisions confirmed by the owner (see §15). Nothing in this document has been implemented yet.
> **Build mode:** Phased. Complete and verify one phase before starting the next (see §14).

### Confirmed Decisions (Summary)

| # | Decision | Where it applies |
|---|---|---|
| 1 | **Branch-level books, rolled up into organization reports.** Each branch has its own chart of accounts and ledger; the organization portal shows consolidated reports. | §4, §5, §8 |
| 2 | **Cost of goods sold = cost snapshot frozen at the moment of sale** (uses the batch cost when the item was sold from a batch, otherwise the item's `buy_price` at that moment). Chosen because it is automatic, needs no accounting knowledge from staff, and stops past profit changing when buy prices change. | §4.2, §6 |
| 3 | **Per-branch VAT On/Off switch only.** Accounting itself has no branch switch — it is on automatically for every branch whose plan includes it. Each branch decides only whether VAT/tax is tracked in its books. | §4.2, §6, §9 |
| 4 | **Plan access: Silver and above** (Silver, Gold, Enterprise), matching how other Silver features are gated. SuperAdmin can change this later from the Plan Capabilities page. | §9 |
| 5 | **Closed periods:** a backdated transaction that falls in a closed month is posted into the **current open period with a memo** referencing the original date. | §7 |
| 6 | **Start date:** books start from the **date accounting becomes active for the branch** (`accounting_started_at`). No history before that date is posted; the branch can optionally enter **opening balances**. | §4.2, §10 |
| 7 | **Vendor payables:** purchases are **treated as paid at receipt** for now (no vendor balance tracking). | §6 |

---

## 1. Goal

Add a built-in **double-entry accounting module** to the POS so every branch (and every organization) gets proper books — Chart of Accounts, Journals, General Ledger, Trial Balance, Income Statement, Balance Sheet, Cash Flow, AR/AP aging and VAT reporting — **without changing how cashiers or admins currently work**.

Staff keep selling, returning, recording expenses, receiving purchase orders and closing tills exactly as today. The accounting module **listens** to those events and posts balanced journal entries automatically.

This is **not** a third-party integration (no QuickBooks / Xero / Sage / Akaunting). It is an in-house module built on the existing Laravel + Inertia + Vue stack.

---

## 2. Core Rule: Accounting Is a Passive Layer, Not a New Business Flow

This is the most important rule in this document.

- The accounting module **observes** existing domain events and **writes** journal entries. It must **never** alter how a sale, return, expense, purchase, debt payment, stock adjustment, transfer or till session is created, priced, taxed, discounted or saved.
- **Do not** modify cart logic, checkout, pricing, tax, discount, payment or stock-deduction code to "make accounting work". If a value needed for accounting is missing, capture it **additively** (new nullable column via a new migration, or a snapshot written by the posting service).
- If posting a journal entry fails, the **original business transaction must still succeed**. Posting failures are logged and retried / surfaced in an "Unposted Transactions" queue — they must never block a sale at the till.
- Posting must be **idempotent**: one source record → at most one active journal entry. Use a unique key on `(source_type, source_id, event)`. This is critical because **offline sales sync later** and may be retried.

---

## 3. Step 0 — Discovery Before Writing Any Code (No Changes)

Before implementing anything, the agent must investigate and document (in the PR / summary) how the following currently work. **Do not assume table or column names from this document — confirm them.**

1. **Sales** — `Sale`, `SaleOrder` models: where totals, tax, `discount_amount`, payment method(s), split payments and customer/debt flags are stored. How offline sales are synced (`OfflineSyncController`).
2. **Cost of goods** — how cost is currently derived. Note: [`ReportController::profitLoss()`](../app/Http/Controllers/Pos/ReportController.php) currently uses the item's **current** `buy_price` × base units, so historical profit changes when buy price changes. Check whether `sale_orders` stores any cost snapshot, and how batches (`batch_tracking_enabled`, `ItemBatch`) record cost.
3. **Returns** — `SaleReturnItem` / `SaleReturnController`: refund method, restocking behaviour.
4. **Customer debt** — debt sales, debt payments / settlements, the debt ledger (`Reports/CustomerDebt`, `DebtLedger`).
5. **Purchases & vendors** — `PurchaseOrderController`, `VendorController`: when stock is received, whether purchases are paid / unpaid / partially paid, and whether vendor balances exist at all.
6. **Expenses** — `ExpenseController`, `ExpenseCategoryController`: payment source, categories, branch scoping.
7. **Inventory movements** — `InventoryController` (adjustments), `StockTransferController`, expiry write-offs, `StockConflictController`.
8. **Till sessions** — `TillSessionController`: opening float, expected vs counted cash, variance, cash drops.
9. **Multi-tenancy** — how `organization_id` / `branch_id` scoping and `current_branch()` work; how org portal reports aggregate branches (`OrgReportController`).
10. **Plan capabilities** — `plan_capabilities` table and `PlanCapabilityController` / feature enforcement middleware, so accounting can be gated by plan.
11. **Permissions** — spatie roles/permissions, `RoleService`, `pos.role:*` middleware patterns.

Produce a short discovery summary listing every event that should post a journal entry and where it is emitted.

---

## 4. Database Schema (New Migrations Only)

> [!CAUTION]
> The project is live in production. **NEVER edit existing migration files.** Every table and column below must be created through a **new timestamped migration** (`php artisan make:migration ...`).

### 4.1 New tables

| Table | Purpose | Key columns |
|---|---|---|
| `accounts` | Chart of Accounts | `id`, `organization_id`, `branch_id` (nullable = org-level), `code`, `name`, `type` (asset / liability / equity / revenue / expense), `sub_type`, `parent_id`, `is_system`, `is_active`, `normal_balance` (debit / credit) |
| `journal_entries` | Header of each double-entry posting | `id`, `organization_id`, `branch_id`, `entry_number`, `entry_date`, `description`, `source_type`, `source_id`, `event`, `status` (posted / reversed / draft), `reversal_of_id`, `financial_period_id`, `created_by`, `approved_by`, timestamps |
| `journal_lines` | Debit/credit lines | `id`, `journal_entry_id`, `account_id`, `debit` (decimal 15,2), `credit` (decimal 15,2), `memo`, `customer_id` (nullable), `vendor_id` (nullable) |
| `financial_periods` | Month locking | `id`, `organization_id`, `branch_id`, `starts_on`, `ends_on`, `status` (open / closed), `closed_by`, `closed_at` |
| `bank_accounts` | Cash, bank and clearing accounts | `id`, `branch_id`, `account_id`, `name`, `bank_name`, `account_number` (masked), `type` (cash / bank / pos_clearing) |
| `posting_rules` | Configurable mapping of event → accounts | `id`, `organization_id`, `branch_id`, `event`, `debit_account_id`, `credit_account_id`, `payment_method` (nullable) |
| `bank_reconciliations` *(Phase 5)* | Statement matching | `id`, `bank_account_id`, `statement_date`, `statement_balance`, `status` |

**Indexes / constraints:**
- Unique `(source_type, source_id, event)` on `journal_entries` where `status = posted` (enforce idempotency in the service if partial indexes aren't available on MySQL).
- Index `(branch_id, entry_date)` on `journal_entries`; `(account_id)` on `journal_lines`.

### 4.2 Additive columns on existing tables (new migrations)

| Table | Column | Why |
|---|---|---|
| `sale_orders` | `unit_cost_snapshot` decimal(15,2) nullable | Freezes cost at time of sale so COGS and historical P&L never drift when `buy_price` changes (Decision 2). Old rows stay `null` and fall back to current behaviour. |
| `expense_categories` | `account_id` nullable | Maps each expense category to its expense account. |
| `plan_capabilities` | `feature_accounting` boolean default `false` | Plan gating (§9). The migration sets it to `true` for **Silver, Gold and Enterprise** (Decision 4). |
| `pos_settings` | `is_accounting_vat_enabled` boolean default `false` | Branch-level On/Off switch for VAT/tax tracking in the books (Decision 3). This is the **only** accounting switch at branch level. |
| `pos_settings` | `accounting_started_at` timestamp nullable | Date the branch's books start (Decision 6). Set automatically the first time accounting becomes active for the branch; never changed afterwards. |

Add the two new `pos_settings` columns to `PosSettings::$fillable`, `casts()`, and the defaults in `forBranch()` / `current()`, following the same pattern used for `is_camera_scanner_enabled`.

Only add a column if discovery (§3) confirms the value isn't already stored.

---

## 5. Default Chart of Accounts (Seeded per Branch / Organization)

Seeded **per branch** (Decision 1) automatically the first time accounting becomes active for that branch (plan includes `feature_accounting`). Seeding also sets `accounting_started_at`. It must run only once per branch (idempotent). System accounts (`is_system = true`) can be renamed but not deleted. Account codes are identical across branches so the organization roll-up can group by code.

Activation happens:
- **For existing Silver+ branches:** via a one-off command `php artisan accounting:activate` run after deployment (also safe to run again).
- **For branches that upgrade to Silver+ later, or new branches:** lazily on the first posting or first visit to the Accounting section.

| Code | Account | Type |
|---|---|---|
| 1000 | Cash on Hand | Asset |
| 1010 | Bank | Asset |
| 1020 | POS / Card Clearing | Asset |
| 1100 | Accounts Receivable (Customer Debt) | Asset |
| 1200 | Inventory | Asset |
| 1300 | Inter-Branch Receivable | Asset |
| 2000 | Accounts Payable (Vendors) | Liability |
| 2100 | VAT / Tax Payable | Liability |
| 2200 | Inter-Branch Payable | Liability |
| 3000 | Owner's Capital | Equity |
| 3100 | Retained Earnings | Equity |
| 4000 | Sales Revenue | Revenue |
| 4100 | Sales Returns | Revenue (contra) |
| 4200 | Sales Discounts | Revenue (contra) |
| 5000 | Cost of Goods Sold | Expense |
| 5100 | Inventory Shrinkage / Expiry Write-off | Expense |
| 5200 | Cash Over / Short | Expense |
| 6xxx | **One account per existing expense category** | Expense |

---

## 6. Automatic Posting Rules (Existing Event → Journal Entry)

Every entry must balance (Σ debit = Σ credit). Amounts come from the **saved** source record — never recomputed with different logic.

| # | Existing event | Debit | Credit |
|---|---|---|---|
| 1 | Cash / transfer / card sale | Cash on Hand / Bank / POS Clearing (by payment method; split payments → multiple lines) | Sales Revenue (net of tax) + VAT Payable |
| 2 | Same sale — cost side | Cost of Goods Sold | Inventory |
| 3 | Sale on credit (debt customer) | Accounts Receivable (+ `customer_id` on line) | Sales Revenue + VAT Payable |
| 4 | Sale discount | Sales Discounts | (reduces revenue line of the same entry) |
| 5 | Customer debt payment | Cash / Bank / POS Clearing | Accounts Receivable |
| 6 | Sale return / refund | Sales Returns + VAT Payable | Cash / Bank / AR (refund method) |
| 7 | Return — restocked cost | Inventory | Cost of Goods Sold |
| 8 | Purchase order received | Inventory | Cash / Bank (**treated as paid at receipt** — Decision 7) |
| 9 | Vendor payment | *Not posted for now* — purchases are already treated as paid at receipt (Decision 7). Reserved for future vendor balance tracking. | — |
| 10 | Expense recorded | Expense category account | Cash / Bank |
| 11 | Stock adjustment (decrease) / expiry write-off | Inventory Shrinkage | Inventory |
| 12 | Stock adjustment (increase) | Inventory | Inventory Shrinkage (gain) |
| 13 | Stock transfer out (sending branch) | Inter-Branch Receivable | Inventory |
| 14 | Stock transfer in (receiving branch) | Inventory | Inter-Branch Payable |
| 15 | Till closed — shortage | Cash Over / Short | Cash on Hand |
| 16 | Till closed — surplus | Cash on Hand | Cash Over / Short |
| 17 | Cash drop / bank deposit | Bank | Cash on Hand |
| 18 | Opening balances (wizard) | Asset accounts | Owner's Capital / Liabilities |

**Edits & deletions of source records:** never edit a posted entry. Post a **reversing entry** (`reversal_of_id`) and, if needed, a new corrected entry.

**Cost basis for rule 2 (Decision 2 — cost snapshot):**
1. When a sale is saved, store `unit_cost_snapshot` on each `sale_orders` row:
   - if the line was sold from a specific batch with a recorded cost → that batch's cost;
   - otherwise → the item's `buy_price` at that moment (converted to the unit sold).
2. COGS always uses `unit_cost_snapshot`.
3. Legacy rows where it is `null` (sales made before this feature) fall back to the current `buy_price`.

The snapshot is written **additively** after the sale is saved (or alongside it); it must not change the sale's totals, pricing or stock logic.

**VAT handling (Decision 3):**
- If the branch has `is_accounting_vat_enabled = true` → tax collected is posted to **VAT / Tax Payable** (rules 1, 3, 6) and the VAT report is available.
- If it is `false` → no VAT lines are posted; the full sale amount goes to Sales Revenue and the VAT report is hidden for that branch.
- Switching it changes only **future** postings; past entries are never rewritten.

---

## 7. Posting Engine (Technical Design)

- `app/Modules/Accounting/` — keep the whole module self-contained (Models, Services, Listeners, Controllers, Policies).
- `PostingService::post(string $event, Model $source): ?JournalEntry`
  - Resolves accounts via `posting_rules` (falls back to system defaults).
  - Validates balance; throws if unbalanced (caught and logged — see §2).
  - Idempotent on `(source_type, source_id, event)`.
  - If the transaction date falls in a **closed** financial period → post it into the **current open period** with a memo such as `"Backdated from 2026-09-14 (period closed)"` (Decision 5). The original transaction date is kept on the entry for reference.
  - Never post anything dated before the branch's `accounting_started_at` (Decision 6) — such source records are skipped, not queued.
- **Triggering:** Laravel model observers or domain events dispatched **after commit** (`afterCommit`) on Sale, SaleReturn, Expense, PurchaseOrder (received), debt payment, stock adjustment, transfer, till close.
- **Queue:** posting runs on a queued job so the till request is never slowed down. Failed jobs land in an **Unposted Transactions** screen with a "Retry" button.
- **Offline sales:** posted when the sale is persisted on the server by the sync endpoint — never in the browser.
- **Rounding:** all money as `decimal(15,2)`; any rounding difference goes to a dedicated rounding line, never silently dropped.

---

## 8. Reports

All reports filter by date range and branch; organization admins also get a **consolidated** view across branches. All exportable to XLSX / CSV using the existing `streamExcelOrCsv` pattern and printable to PDF.

| Report | Notes |
|---|---|
| Trial Balance | Debit / credit totals per account; must balance |
| General Ledger | Account movements with running balance; each line links back to the source sale / expense / PO |
| Income Statement (Full P&L) | Revenue − Returns − Discounts − COGS = Gross Profit − Operating Expenses = **Net Profit** |
| Balance Sheet | Assets = Liabilities + Equity, with current-period profit rolled into equity |
| Cash Flow Statement | Direct method from cash/bank account movements |
| AR Aging | Customer debt buckets: 0–30 / 31–60 / 61–90 / 90+ days |
| AP Aging | Vendor balances by the same buckets |
| VAT / Tax Report | Output VAT on sales minus VAT on returns for the period |
| Bank Reconciliation *(Phase 5)* | Match ledger to bank statement |

> [!NOTE]
> The existing `Reports/ProfitLoss` page stays as-is (it is a quick gross-profit view). The new Income Statement lives in the Accounting section.

---

## 9. Plan Gating, Permissions & Navigation

Accounting is active for a branch when **both** are true:

1. **Plan allows it** — `feature_accounting = true` on the organization's plan. Enabled by default for **Silver, Gold and Enterprise** (Decision 4); editable from the SuperAdmin **Plan Capabilities** page. Enforce with the existing feature-enforcement middleware pattern.
2. **User has permission** — see below.

There is **no branch-level switch to turn accounting off** (Decision 3). If an organization downgrades below Silver, posting stops; existing books are kept and remain viewable read-only if they upgrade again.

**Branch settings UI (Decision 3 — VAT only):**
- Add an **Accounting** card to branch settings ([Settings/Index.vue](../resources/js/Pages/Settings/Index.vue)) and the SuperAdmin branch view ([SuperAdmin/Branches/Show.vue](../resources/js/Pages/SuperAdmin/Branches/Show.vue)) with **one** toggle:
  - **Track VAT / Tax in the books** (`is_accounting_vat_enabled`).
- The card also shows (read-only) the books start date (`accounting_started_at`).
- If the plan does not include accounting, show the toggle disabled with an "Available on Silver and above" upgrade hint.
- Add the validation rule in `Pos/SettingsController` and `SuperAdmin/BranchController`.

**Organization roll-up (Decision 1):** organization reports combine all of the organization's branches that have accounting active, and clearly list which branches are included and each branch's start date.
- **Permissions (spatie, branch-scoped):**
  - `view_accounting` — view reports & ledger
  - `manage_chart_of_accounts`
  - `manage_journal_entries` — manual journals
  - `approve_journal_entries`
  - `close_financial_periods`
- **Optional role:** `Accountant` (view + manual journals, no POS selling permissions).
- **Navigation:** a new **Accounting** sidebar section in the branch admin layout and the organization portal, hidden when the plan lacks the capability or the user lacks `view_accounting`.

---

## 10. Admin Tools

- **Setup wizard (optional):** shown the first time a branch admin opens Accounting — map expense categories → map payment methods to Cash / Bank / POS Clearing → **enter opening balances (optional, can be skipped)**. The chart of accounts is already seeded automatically; the wizard never blocks posting.
- **Opening balances (Decision 6):** a single balanced journal entry dated on `accounting_started_at` (e.g. cash on hand, bank balance, stock value, owner's capital). Can be entered or corrected later.
- **Manual journal entries:** owner capital, loans, depreciation, corrections; optional approval flow.
- **Period close / reopen:** closing locks the month; reopening requires `close_financial_periods` and is audit-logged. Late transactions for closed months post into the current open period with a memo (Decision 5).
- **Activation command:** `php artisan accounting:activate {--branch=}` — seeds accounts and sets `accounting_started_at` for every Silver+ branch that isn't active yet. Idempotent.
- **No historical backfill** (Decision 6): transactions before `accounting_started_at` are never posted. A re-post command `php artisan accounting:repost {--branch=} {--from=} {--to=} {--dry-run}` exists only to retry **failed postings on or after** the start date. Must be idempotent and chunked.
- **Unposted Transactions screen:** lists failed postings with reason and retry.

---

## 11. Interaction With Existing Features (All Unchanged)

| Feature | Behaviour after this module |
|---|---|
| POS selling, cart, pricing, tax, discounts | Unchanged |
| Hardware & camera barcode scanning | Unchanged |
| Offline selling & sync | Unchanged; entries posted server-side after sync |
| Till sessions | Unchanged; close event additionally posts variance |
| Existing reports (Daily Sales, End of Day, Profit & Loss, Customer Debt, Expenses, Low Stock, Expiry) | Unchanged |
| E-commerce reservations | Unchanged; confirmed e-commerce sales post like normal sales |
| AI credits, subscriptions, plan capabilities | Unchanged (plus one new capability flag) |
| Branch settings | Unchanged, plus one new toggle (Track VAT in the books) and a read-only books start date |

If the organization's plan is below Silver, no posting happens and the POS behaves exactly as it does today.

---

## 12. Testing Checklist

**Unit / Feature (PHPUnit):**
- [ ] Every posting rule in §6 produces a **balanced** entry with the correct accounts and amounts.
- [ ] Split-payment sale posts one debit line per payment method.
- [ ] Posting the same source twice creates only **one** entry (idempotency).
- [ ] Offline-synced sale posts exactly once, even if sync is retried.
- [ ] Editing / voiding a source record creates a reversing entry; original is untouched.
- [ ] Posting failure does **not** roll back or block the sale.
- [ ] A transaction dated in a closed period is posted into the current open period with a memo containing the original date.
- [ ] Transactions dated before `accounting_started_at` are never posted.
- [ ] `unit_cost_snapshot` is saved on new sales; COGS uses it; changing `buy_price` later does not change historical COGS.
- [ ] Trial Balance always balances; Balance Sheet satisfies Assets = Liabilities + Equity.
- [ ] Branch isolation: branch A users cannot see branch B ledger; org admins see consolidated view.
- [ ] Plan without `feature_accounting` gets 403 / hidden navigation.
- [ ] Silver, Gold and Enterprise plans have `feature_accounting = true` after migration; Starter and Bronze do not.
- [ ] Every Silver+ branch becomes active automatically (via `accounting:activate` or lazily); Starter/Bronze branches post nothing.
- [ ] Activation seeds the chart of accounts and sets `accounting_started_at` exactly once (running it again changes nothing).
- [ ] Opening balances entry is optional, balanced, and dated on `accounting_started_at`.
- [ ] Downgrading below Silver stops posting; existing books remain readable after re-upgrade.
- [ ] Purchase order received posts Dr Inventory / Cr Cash-Bank (paid at receipt); no Accounts Payable lines are created.
- [ ] `is_accounting_vat_enabled = true` posts VAT Payable lines; `false` posts the full amount to revenue.
- [ ] COGS uses batch cost when the line was sold from a batch, otherwise `buy_price` at sale time.
- [ ] Organization roll-up includes all active branches and lists their start dates.
- [ ] Permission checks on every accounting route.
- [ ] Re-post command is idempotent, ignores records before the start date, and `--dry-run` writes nothing.

**Regression:**
- [ ] Existing POS, till, offline sync, returns, expense, purchase and report tests still pass.
- [ ] `npm run build` succeeds; accounting pages are lazy-loaded chunks.

---

## 13. Explicitly Out of Scope

- Integrations with QuickBooks, Xero, Sage, Akaunting or any third-party accounting software.
- Payroll.
- Fixed-asset register with automatic depreciation schedules (manual journals only for now).
- Multi-currency accounting / FX revaluation.
- Budgeting and forecasting.
- Tax filing / submission to tax authorities.
- Changing any existing POS business logic, UI flow or report.

---

## 14. Phased Delivery Plan

| Phase | Scope | Done when |
|---|---|---|
| **1. Foundation** | Migrations (§4) incl. VAT toggle, `accounting_started_at` and Silver+ plan flag; VAT toggle UI (§9); per-branch chart of accounts seeding + `accounting:activate` (§5); `PostingService` (§7); permissions; Accounting sidebar shell | Running `accounting:activate` seeds every Silver+ branch once; manual test entry posts and balances |
| **2. Sales side** | Rules 1–7, 15–17: sales, discounts, credit sales, debt payments, returns, till variance, cash drops; `unit_cost_snapshot` | Sales-side tests pass; zero POS regressions |
| **3. Purchase & inventory side** | Rules 8, 10–14: purchase orders (paid at receipt), expenses, adjustments, expiry write-offs, transfers | Purchase-side tests pass |
| **4. Reports** | Trial Balance, General Ledger, Income Statement, Balance Sheet, Cash Flow, AR aging, VAT + exports (AP aging deferred — Decision 7) | Reports reconcile with each other |
| **5. Advanced** | Period close/reopen (closed-period memo rule), manual journals + approval, bank reconciliation, optional opening balances wizard, re-post command, org consolidation, Unposted Transactions screen | Full checklist (§12) green |

---

## 15. Decisions & Remaining Questions

### Confirmed by the owner

1. **Books scope:** ✅ Branch-level books, rolled up into organization reports.
2. **Cost method:** ✅ Owner asked for the best option for a non-accountant → **cost snapshot at time of sale** (batch cost when sold from a batch, otherwise `buy_price` at that moment). Full FIFO costing is out of scope for now.
3. **VAT On/Off:** ✅ The per-branch switch is **for VAT only**. Accounting is on automatically for every Silver+ branch; there is no branch switch to turn it off.
4. **Plan tiers:** ✅ **Silver and above** (Silver, Gold, Enterprise) get `feature_accounting`.
5. **Closed periods:** ✅ Backdated transactions for a closed month are posted into the **current open period with a memo**.
6. **Start date:** ✅ Books start from the **date accounting becomes active for the branch**, with **optional opening balances**. No historical backfill.
7. **Vendor payables:** ✅ Purchases are **treated as paid at receipt** for now. Vendor balances / AP aging are deferred.

All questions are answered — the specification is ready for Phase 1.
