# POS System — Plan AI Credit Metering (Agent README 3 of 3)

**Prerequisite:** Requires Part 1 (`subscription`, scoped to `organization_id`) to exist. Part 2's `plan_capabilities` table needs to exist too (this document extends it with two columns), though Part 2's full enforcement logic does not need to be complete first — per the Organization Layer README's §12 recommended path, only the table itself is a hard dependency.

**This is Part 3 of 3** in the Plan/Feature Enforcement work:
- **Part 1:** Plan Subscription Tracking & Visibility — separate document, must be built first.
- **Part 2:** Feature & Limit Enforcement — separate document; only its `plan_capabilities` table is a hard dependency for this document.
- **Part 3 (this document):** Track and meter AI credit usage per **each organization's** active plan monthly allocation.

**Scope correction (supersedes the earlier single-deployment version of this document):** The Starter plan's "account-wide" credit pool is now an **organization-wide** pool, not installation-wide. With two existing organizations, each gets its own independent pool and its own independent monthly reset, entirely separate from one another.

---

## 1. Goal

Track and enforce monthly AI credit usage — Chat Assistant questions, Reorder Prediction runs — against **each organization's** active plan allocation, with correct handling of the two different pooling models the pricing sheet uses, kept fully separate between organizations.

---

## 2. Two Different Pooling Models (Per the Pricing Sheet)

- **Starter:** credits are a single **organization-wide** pool — 25/month total for that organization, regardless of how many branches it has (Starter only allows 1 branch anyway, per Part 2).
- **Bronze, Silver, Gold:** credits are allocated **per branch**, per month (e.g., Silver = 100 credits, per branch, per month) — so a 3-branch Silver organization effectively has 300 credits/month total, but tracked and reset independently per branch within that organization.
- **Enterprise:** custom/agreed allocation per organization — treat as unmetered (no hard cap) by default unless a specific number is manually configured for that organization (see Open Questions §6.1).

**Isolation requirement:** one organization's credit usage or remaining balance must never be visible to, combined with, or affected by another organization's usage — each organization's pool (whether organization-wide or per-branch) is entirely self-contained.

---

## 3. Data Model

### Extend `plan_capabilities` (from Part 2) with:
| Column | Type | Notes |
|---|---|---|
| `ai_credit_pool_type` | enum: account_wide / per_branch | Starter = account_wide (now meaning organization-wide), Bronze/Silver/Gold = per_branch |
| `ai_credits_monthly` | integer, nullable | null = unmetered (Enterprise, by default) |

> This table stays **per plan**, same as in Part 2 — the organization-scoping happens in how usage is tracked and checked (§3's `ai_credit_usage` table and §4), not in `plan_capabilities` itself.

### Seed values (per the attached pricing sheet)
| Plan | Pool Type | Monthly Credits |
|---|---|---|
| Starter | account_wide (→ organization-wide) | 25 |
| Bronze | per_branch | 25 |
| Silver | per_branch | 100 |
| Gold | per_branch | 300 |
| Enterprise | — | null (unmetered by default) |

### `ai_credit_usage` (new table)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `branch_id` | bigint, FK → branches, nullable | null for an organization-wide pool (Starter); set for per-branch pools (Bronze+) |
| `used_at` | timestamp | |
| `feature` | string | which AI action consumed it, e.g., `chat_query`, `reorder_prediction` |
| `credits_used` | integer | usually 1, kept flexible in case some actions cost more later |

- **Organization is always derivable** — for per-branch pools, through `branch -> organization`; for organization-wide pools (Starter), the consuming action must still know which branch/user triggered it (for `branch_id`, kept for traceability even though the *pool* is organization-wide) and which organization that branch belongs to, for the purposes of checking and decrementing the correct organization's single pool.

---

## 4. Tracking Usage

- Every AI action that consumes a credit (a Chat Assistant question, a Reorder Prediction run — per the AI Features README and its New Query Capabilities extension) logs one unit of consumption:
  - **Organization-wide pool (Starter):** check and decrement against the total used by *any* branch under that organization this month (trivial today, since Starter only allows 1 branch — but the logic must be written generally, not hardcoded to one branch).
  - **Per-branch pool (Bronze+):** check and decrement against that specific branch's own usage this month, regardless of other branches in the same organization.
- Monthly reset: credits available reset at the start of each calendar month — see Open Questions §6.2 for whether this should instead align to each organization's own plan start-date anniversary.

---

## 5. What Happens When Credits Run Out

- When an organization (Starter) or a specific branch (Bronze+) has used its full monthly credit allocation, the AI features (Chat Assistant, Reorder Prediction) should show a clear message (e.g., "Monthly AI credit limit reached — resets on [date]") rather than silently failing or erroring.
- **Recommendation: start with a hard block** (no further AI use until reset) — simpler and safer than tracking overage, and revisit once the pricing sheet's mentioned "additional AI credit packs" add-on actually exists as a purchasable feature (not built yet). See Open Questions §6.3.

---

## 6. Notes for the Agent & Open Questions

### Notes for the Agent
- Since AI features already live in their own self-contained module (per §0.2 of the AI Features README), this credit-metering logic fits naturally inside that same module — extend it there rather than building a separate credits system elsewhere.
- Build a small, reusable helper that is **organization-aware from the start** (e.g., `$branch->organization->hasAiCreditsRemaining($branch)`, `$branch->organization->consumeAiCredit($branch, $feature)`) — the helper internally decides whether to check/decrement the organization-wide pool or the specific branch's pool based on that organization's `ai_credit_pool_type`, so callers (Chat Assistant, Reorder Prediction) don't need to know which model applies.
- This document assumes Part 1 (`subscription`, organization-scoped) and Part 2's `plan_capabilities` table already exist — extend `plan_capabilities` rather than creating a competing table.
- **Test isolation explicitly:** confirm that exhausting one organization's credits (whichever pool type) has zero effect on a different organization's available credits, and that a Starter organization's single pool is correctly shared across branches *within that organization only* if it somehow had more than one branch in the future.

### Open Questions
1. **Enterprise AI credits:** Should Enterprise truly be unmetered by default, or should there always be a configurable number entered manually per Enterprise organization, even if very high, so usage is at least visible/trackable?
2. **Credit reset cycle:** Should the monthly credit reset align to the calendar month (1st of every month) regardless of when each organization's plan started, or align to each organization's own monthly anniversary (e.g., reset on the 14th of each month if that organization's plan started on March 14th)? Since the two existing organizations will likely be set up on different dates, this decision affects whether their reset cycles end up staggered or synchronized.
3. **Credit overage behavior:** Confirming the §5 recommendation (hard block once credits run out) is acceptable for now, versus allowing continued use while flagging the overage for later follow-up.
