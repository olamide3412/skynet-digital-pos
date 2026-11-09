# POS System — Plan Subscription Tracking & Visibility (Agent README 1 of 3)

**Prerequisite:** Requires the Organization Layer README to be in place first — specifically Phase 1 (`organizations` table exists) and Phase 3 (organization management screens exist). Per the recommended path in that document's §12, this work can follow right after Phase 3/5, before Phase 4 or Phase 6 are ever built.

**This is Part 1 of 3** in the Plan/Feature Enforcement work:
- **Part 1 (this document):** Track which plan **each organization** is on, its renewal date, and let the right people view it. **No enforcement, no restrictions — purely tracking and display.**
- **Part 2:** Feature & Limit Enforcement (branch/user limits, gated features, grandfathering existing data) — builds on top of this one.
- **Part 3:** AI Credit Metering — builds on top of this one.

Building Part 1 on its own first, before touching any enforcement logic, keeps this low-risk on a live system — nothing about how the app behaves changes yet at this stage.

**Scope correction (supersedes the earlier single-deployment version of this document):** A subscription belongs to an **Organization**, not to the whole installation. This installation currently has **two organizations** (two clients, one branch each), and each will get its own independent plan, renewal date, and credit pool. This document reflects that from the start.

---

## 1. Goal

- Track which Plan **each organization** is subscribed to, along with its renewal date.
- Let **Platform Super Admin** (Skynet Digital — the existing Super Admin role) view and change any organization's active plan at any time.
- Let **Organization Admin** (if/when that role is delegated access, per the Organization Layer README) and **Branch Admin** **view** (read-only) which plan their organization is currently on — no edit access for either.
- Surface a clear, non-blocking notice as an organization's renewal date approaches or passes — no automatic lockout (there's no billing integration here; this is tracked manually).

---

## 2. Data Model

### `subscription` (new table — one active row per organization)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `organization_id` | bigint, FK → organizations | **one subscription per organization**, not per installation |
| `pricing_plan_id` | bigint, FK → pricing_plans | the organization's currently active plan |
| `started_at` | date | when this plan became active for this organization |
| `renewal_date` | date | |
| `status` | enum: active / grace_period / expired | see §4 |
| `changed_by` | bigint, FK → users, nullable | which Platform Super Admin last changed the plan |
| `created_at` / `updated_at` | timestamps | |

- Add a unique constraint on `organization_id` for the "current" row if modeling this as one live row per organization (recommended — simplest), with historical changes tracked separately per below.

### `subscription_history` (new table)
- Same shape as `subscription`, including `organization_id` — every row is a historical record rather than the current state. A new row is added every time Platform Super Admin changes an organization's plan (§3). Useful later for reviewing what plan applied to a given organization at a given point in time (e.g., when reviewing past AI credit usage or feature access for that specific client).

---

## 3. Platform Super Admin: Viewing & Changing an Organization's Plan

- Within the existing **Organization** management screens (per the Organization Layer README, Phase 3), add a **"Subscription"** tab/section per organization:
  - Shows that organization's current plan, renewal date, and status.
  - A simple dropdown/selector to **change that organization's active plan at any time** — selecting a new plan immediately updates `subscription.pricing_plan_id` for that organization and takes effect right away. No approval workflow, no payment integration — this is a manual, trusted action, performed per organization.
  - An editable **renewal date** field, since this is tracked manually rather than through automated billing.
  - Changing the plan logs a new row to `subscription_history` automatically, scoped to that organization.
- Platform Super Admin can do this for **any** organization — there is no cross-organization bulk action needed; each organization's plan is set independently (this matters now, since the two existing clients may end up on different plans or different renewal dates from each other).

---

## 4. Renewal Date Handling — Deliberately Soft, Not Automatic Lockout

- Since there's no payment/billing integration, **do not automatically lock out or restrict an organization just because its `renewal_date` has passed** — that's out of scope for this whole plan/feature body of work, not just this document.
- When an organization's `renewal_date` is within a configurable warning window (e.g., 14 days out) or has already passed, surface a clear notice through the existing Notifications system, as its own type — visible to Platform Super Admin for that organization, and reasonably to that organization's Branch Admin(s)/Organization Admin too, since they can already see plan info per §5.
- `status` can move to `grace_period` automatically once past the renewal date, purely for visibility/reporting, per organization — this must **not** trigger any feature restriction on its own (restriction logic lives entirely in Part 2, and even there, is never tied automatically to renewal status without a deliberate decision).

---

## 5. Read-Only Visibility for Branch Admin / Organization Admin

- Branch Admin should be able to **see** their branch's organization's current plan name and renewal date somewhere reasonable (e.g., their own settings/profile area) — but with **no edit access whatsoever**.
- If/when Organization Admin access is delegated (Organization Layer README, Phase 6), they see the same information for their own organization, also read-only.
- Purely informational for now; once Part 2 exists, this is also where either role would understand why a certain feature isn't available to their organization.

---

## 6. Migration (Live System)

- New migration for `subscription` and `subscription_history` — no existing tables altered beyond what the Organization Layer work already added.
- **Do not auto-create a `subscription` row for either existing organization as part of this migration** — leave both unset. Platform Super Admin assigns each organization's plan manually afterward through the screen in §3 (per the earlier plan to set both existing clients to Silver), at a time of their choosing.

---

## 7. What Should NOT Change

- Nothing about how the app currently behaves changes as a result of this document alone — this is tracking and visibility only. No feature becomes restricted, no branch/user limit is checked, no AI credit is metered yet. That's all Part 2 and Part 3, built and reviewed separately.
- No change to the Organization Layer's own phased rollout — this document assumes that work (at least through Phase 3) is already complete and stable.

---

## 8. Open Questions

1. **Renewal warning window:** Is 14 days before `renewal_date` a reasonable default for the advance notice, or would a different lead time fit better?
2. **Differing renewal cycles across organizations:** Since each organization now has its own independent renewal date, is it acceptable for the two existing clients to end up on different renewal months depending on when each is manually set up, or would you prefer to align them to the same cycle deliberately?
