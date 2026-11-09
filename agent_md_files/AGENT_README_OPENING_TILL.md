# POS System — Opening Till / Starting Till (Agent README)

**Note:** This introduces a formal **Till Session** concept that the existing reporting already assumed but never actually had a defined starting point for — the original Reporting README already lists a **"Cash Reconciliation (End-of-Day/Till Report)"** and a **"Shift Report,"** both described "per cashier/shift," but nothing in this project so far actually defines how a shift/till session begins. This README fills that gap at the start of the process; the reconciliation/reporting side already specified continues to be the natural destination for this data, not something to rebuild.

---

## 1. Goal

Let a cashier declare their starting cash float before selling, so every sale during their shift is tied to a specific till session — giving the existing Cash Reconciliation and Shift reports something real to reconcile against (expected cash vs. actual cash counted), instead of just aggregating sales with no defined shift boundary.

---

## 2. Branch Setting: Require Till Opening

- Add a branch-level toggle — **"Require Till Opening Before Selling"** — consistent with how other optional features in this project are controlled per branch (tax, offline mode, batch tracking, etc.).
- **Default: on**, since this is core cash-accountability behavior most shops will want, but it stays a toggle so a branch that doesn't need shift-based cash tracking isn't forced into it.
- When **off**: checkout behaves exactly as it does today — no till prompt, no change to existing behavior. This is the safety net for not breaking anything already working.
- When **on**: a cashier cannot reach the main checkout screen until they've opened a till session for that login (see §3).

---

## 3. Opening the Till

- When a cashier logs in (or returns to the POS screen with no currently open till session for them), and the branch setting in §2 is on, show an **"Open Till"** prompt before the checkout screen loads:
  - **Opening Cash Amount** — the starting float the cashier is declaring they have in the drawer, entered manually by the cashier.
  - Confirms branch and cashier automatically (no manual entry needed there).
- On submit, create a new `till_sessions` record (§6) with `status = open`, `opened_at = now()`, and the entered opening amount.
- Only after this is the cashier taken to the normal checkout screen.

---

## 4. During the Session

- Every sale completed while a till session is open gets tagged with that session's ID (`till_session_id` added to the sales table — see §7).
- This applies identically whether the sale happens online or offline (per the existing Offline Sales architecture) — the till session itself should also be **opened while offline if needed**, cached locally and synced once connectivity returns, consistent with how everything else in offline mode already works. A cashier starting their shift during a network outage shouldn't be blocked from opening till.
- **Scope confirmed: a till session is cashier + branch** — not tied to a specific register/terminal. If a branch has multiple physical tills, each is still just "this cashier's session at this branch," consistent with the rule in §3.1 that a cashier can only have one open session at a time, anywhere.
- Mid-shift cash can be added to or removed from the till — see §4A.

---

## 4A. Mid-Shift Cash Movements (Add / Remove)

- Support **"Add Cash"** and **"Remove Cash"** actions on an open till session — e.g., a manager drops extra change into the drawer mid-shift, or removes excess cash for safekeeping.
- This is **not limited to the cashier who owns the session** — it's gated by a **permission** (e.g., `manage_till_cash_movements`), consistent with how other actions in this project are permission-based rather than role-hardcoded. Whoever holds that permission (typically Branch Admin, but configurable per the existing RBAC system) can add or remove cash from any open till session at their branch.
- Each movement records: `till_session_id`, `type` (add/remove), `amount`, an optional `reason`/note, `performed_by` (the user who made the entry), and `created_at`.
- These movements **factor into the Expected Cash calculation at close** (§5) — see the updated formula below.

---

## 5. Closing the Till — Reuses Existing Reconciliation, Doesn't Duplicate It

- When the cashier is done for the shift, a **"Close Till"** action lets them enter the **actual cash counted** in the drawer.
- The system calculates **Expected Cash**:
  `Expected Cash = Opening Amount + Cash Sales − Cash Refunds + Cash Added − Cash Removed`
  (cash sales/refunds from sales tagged to this session per §4; cash added/removed from the §4A movement records tied to this session)
- Show the variance (actual counted vs. expected) clearly — over, short, or exact — and save it against the till session.
- **Printed till slip:** add a setting — **"Print Till Slip"** — **On/Off**, controlling whether opening and closing a till session prints a physical slip via the existing printer settings (same printer configuration already used for sales receipts). When on, print a short slip at both open (opening amount, cashier, timestamp) and close (closing amount, expected amount, variance, cashier, timestamp). Default off, since not every branch will want this.
- **This is exactly the data the existing Cash Reconciliation (End-of-Day/Till Report) already expected to show** — that report should now pull from real `till_sessions` records instead of whatever looser assumption it worked from before. No new report needs to be built; the existing one gets a real data source to report from.
- On close: `status = closed`, `closed_at = now()`, store the counted amount and calculated variance.

---

## 6. Data Model

### `till_sessions` (new table)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `branch_id` | bigint, FK → branches | |
| `cashier_id` | bigint, FK → users | |
| `opening_amount` | decimal | |
| `opened_at` | timestamp | |
| `closing_amount` | decimal, nullable | set only on close |
| `expected_amount` | decimal, nullable | calculated at close time, per §5 |
| `variance` | decimal, nullable | `closing_amount - expected_amount`, calculated at close time |
| `status` | enum: open / closed | |
| `closed_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamps | |

### `till_cash_movements` (new table)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `till_session_id` | bigint, FK → till_sessions | |
| `type` | enum: add / remove | |
| `amount` | decimal | |
| `reason` | string, nullable | optional note |
| `performed_by` | bigint, FK → users | the user who made the entry, per the `manage_till_cash_movements` permission in §4A |
| `created_at` | timestamp | |

### Sales table
- Add `till_session_id` (nullable — nullable specifically so existing historical sales, and any branch with the §2 setting off, aren't forced to have one) FK → `till_sessions`.

---

## 7. Branch Admin Oversight

- Branch Admin should be able to **view all till sessions** for their branch — open and closed, who opened them, when, and their eventual variance once closed — as its own simple list/report, or folded into the existing Shift Report.
- Branch Admin should be able to **force-close a till session** on a cashier's behalf (e.g., a cashier forgot to close out, or left for the day without doing so) — this requires entering a counted amount just like a normal close, logged clearly as a Branch-Admin-initiated close rather than a self-close, for accountability.

---

## 8. Migration (Live System)

- New migration for `till_sessions`, plus the nullable `till_session_id` column on the sales table — no existing sales data is altered, and no existing branch is forced into this behavior unless the §2 setting is explicitly turned on.

---

## 9. What Should NOT Change

- With the branch setting off, checkout behaves exactly as it already does today — zero change.
- No change to existing discount, tax, offline sync, IMEI, or variant-pricing logic — till sessions only wrap around the existing sale flow with a session reference, they don't alter how a sale itself is processed.
- The existing Cash Reconciliation and Shift reports are not rebuilt — they gain a proper data source (`till_sessions`) to report from instead of whatever they inferred without one.

---

## 10. Notes for the Agent

- Keep the Open Till prompt fast and minimal — one field, no friction, since this sits directly in front of a cashier's ability to start selling.
- This should work exactly the same whether online or offline — reuse the existing offline-first local-storage-then-sync pattern already established for held sales and checkout, rather than building a separate offline mechanism for till sessions specifically.
- Test explicitly: opening a till offline, selling offline, closing the till offline, then reconnecting and confirming the full session (open, sales, close) syncs correctly as one coherent record — not fragmented pieces that arrive out of order.

---

## 11. Resolved Decisions (For Reference)

1. **Scope:** Till session is cashier + branch, not tied to a specific register/terminal (§4).
2. **Mid-shift cash movements:** Supported, gated by a permission rather than hardcoded to a specific role (§4A).
3. **Printed till slip:** A simple On/Off setting (§5).
4. **One session per cashier:** Confirmed — a cashier cannot have more than one open till session at a time, system-wide.

## 12. Open Questions

1. **Cash movement reason — required or optional:** Should the `reason` field on an Add/Remove cash movement (§4A) be required (forcing accountability for every entry), or optional as currently modeled?
2. **Till slip default:** Should "Print Till Slip" default on or off for newly created branches? Currently written as default off — confirm this is the right starting point.
