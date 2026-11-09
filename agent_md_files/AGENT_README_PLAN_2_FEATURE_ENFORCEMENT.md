# POS System — Plan Feature & Limit Enforcement (Agent README 2 of 3)

**Prerequisite:** Requires Part 1 (`subscription`, scoped to `organization_id`) to already exist and be working, which itself requires the Organization Layer README's Phase 1/3 (at minimum) to be in place.

**This is Part 2 of 3** in the Plan/Feature Enforcement work:
- **Part 1:** Plan Subscription Tracking & Visibility — separate document, must be built first.
- **Part 2 (this document):** Actually restricting branches, users, and gated features based on **each organization's** active plan. **This is the highest-risk document of the three — read §3 (Grandfathering) before building anything else.**
- **Part 3:** AI Credit Metering — separate document.

**Scope correction (supersedes the earlier single-deployment version of this document):** Limits and feature access are evaluated **per organization**, not per installation. With two existing organizations (one branch each today), each is checked independently against its own plan.

---

## 1. Goal

Restrict branch creation, staff creation, and access to plan-gated features (discounts, batch tracking, IMEI, AI chat, etc.) based on **the active plan of the organization a branch belongs to** (resolved via `branch -> organization -> subscription -> plan_capabilities`) — **without ever breaking or hiding anything that already exists** on this live system.

---

## 2. Data Model: `plan_capabilities`

The existing `pricing_plan_features` table (from the Homepage Pricing README) holds **display text** for the homepage marketing section — that's for humans to read, not for the system to check against. Enforcement needs a separate, structured, machine-readable table instead. This table is **per plan**, not per organization — many organizations can share the same plan's capability set.

### `plan_capabilities` (new table)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `pricing_plan_id` | bigint, FK → pricing_plans | |
| `max_branches` | integer, nullable | null = unlimited. **Counted per organization** (§4) |
| `max_users_per_branch` | integer, nullable | null = unlimited. Stays per branch, unchanged in meaning |
| `feature_multi_branch` | boolean | effectively `max_branches > 1`, kept explicit for clarity |
| `feature_debt_customer_accounts` | boolean | see Open Questions — ambiguous in source pricing sheet |
| `feature_discounts` | boolean | item/group/overall discount management |
| `feature_batch_expiry_tracking` | boolean | |
| `feature_imei_tracking` | boolean | |
| `feature_barcode_label_printing` | boolean | |
| `feature_inventory_import` | boolean | the CSV/Excel Import Library |
| `feature_advanced_staff_permissions` | boolean | |
| `feature_ai_chat_assistant` | boolean | |
| `feature_ai_reorder_prediction` | boolean | |
| `feature_broadcast_notifications` | boolean | |

> AI credit limits are **not** part of this table — those live entirely in Part 3, since credit metering is a separate concern from on/off feature gating.

### Seed values based on the attached pricing sheet
| Plan | max_branches | max_users_per_branch | multi_branch | debt/customer | discounts | batch/expiry | IMEI | label printing | import tools | advanced permissions | AI chat | AI reorder | broadcast |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Starter | 1 | 5 | No | No | No | No | No | No | No | No | No | No | No |
| Bronze | 2 | 5 | Yes | Yes | Yes | No | No | No | No | No | No | No | No |
| Silver | 5 | 10 | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | No | No | No |
| Gold | 10 | 20 | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Enterprise | 15 (expandable) | Unlimited (null) | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |

> **Two features have a genuine ambiguity worth resolving before seeding — see Open Questions §7.1.**

---

## 3. Grandfathering Existing Data — Read This First

**This is the most important rule in this document, full stop.** Real branches, staff, and feature usage already exist on this live system, across **two separate organizations**, before enforcement is switched on, and each organization's plan is being assigned manually, after the fact (both to Silver, per the current plan).

- **Enforcement only ever blocks new creation going forward — it never removes, disables, or restricts anything that already exists**, and this is evaluated **per organization**, independently:
  - If a given organization currently has more branches than its assigned plan's `max_branches` allows, those existing branches **must continue working exactly as they do today** — enforcement only prevents that organization from creating the *next* branch beyond its own limit, until its plan changes or branches are reduced. (Not currently a concern for either existing client, since both have exactly one branch today — but the rule must hold generally, not just for the current two-branch-total reality.)
  - Same principle for users-per-branch: existing staff accounts beyond the plan's `max_users_per_branch` stay fully functional; only creating a *new* staff account beyond the limit gets blocked, evaluated per branch as before.
  - Same principle for any plan-gated feature (discounts, batch tracking, etc.): if any of these are already in use by a given organization's branch before that organization's plan is assigned, **existing data and configuration must not be hidden, disabled, or deleted.** At most, creating *new* records of that type going forward is what gets reconsidered — and even then, cautiously (see Open Questions §7.2).
- **Until Platform Super Admin manually assigns a plan to a given organization** (i.e., while that organization has no row in `subscription` yet), **no restrictions apply to that organization at all** — treat it as fully unrestricted (equivalent to Enterprise) by default. Restrictions only begin the moment Platform Super Admin deliberately sets a plan **for that specific organization** — this must never leak across organizations (assigning Organization A a plan must never affect Organization B's restriction status).

---

## 4. Enforcement Behavior

- When a plan-gated feature isn't included in the current plan **of the organization that branch belongs to**, the corresponding menu item/screen should be **hidden or clearly marked as unavailable** (e.g., "Available on Silver plan and above") rather than failing with an error if someone stumbles onto it.
- Enforce checks **server-side**, not just hiding UI elements — consistent with the existing RBAC principle already established for roles/permissions elsewhere in this project. A gated feature must be blocked at the controller/policy level too, not just visually hidden.
- Branch/user-limit checks happen at the moment of creation:
  - Creating a new branch: count existing branches **for that organization only**, compare against that organization's plan `max_branches`.
  - Creating a new staff account: count existing users **for that branch only** (unchanged from before), compare against the branch's organization's plan `max_users_per_branch`.
  - Block with a clear message (e.g., "This organization's plan allows up to 5 branches. Upgrade the plan to add more.") rather than a generic error.

---

## 5. Migration (Live System)

- New migration for `plan_capabilities` — no existing tables altered.
- Seed `plan_capabilities` with the values in §2's table, pending resolution of the ambiguous items in Open Questions §7.1.

---

## 6. Notes for the Agent

- Build a small, reusable capability-check helper scoped by organization (e.g., `$organization->hasFeature('batch_expiry_tracking')`, `$organization->canCreateBranch()`) used consistently everywhere a gate is needed, rather than scattering raw plan-comparison logic across many controllers. A branch-level helper (e.g., `$branch->organization->hasFeature(...)`) can simply delegate to the organization-level one.
- Do not build this until Part 1 (`subscription`, organization-scoped) is in place and working, and the Organization Layer's branch→organization relationship (Phase 1–3) is stable.
- **Test the grandfathering behavior (§3) explicitly and deliberately before considering this done:** simulate an organization with more branches/users than the plan being assigned allows, confirm nothing breaks, and confirm only *new* creation beyond that organization's limit is blocked — and confirm this check never looks at or is affected by a *different* organization's branch/user counts.

---

## 7. Open Questions

1. **Two ambiguous features in the source pricing sheet:** The "What Every Plan Includes" baseline lists "Customer management, expenses and standard business reports" as included in *every* plan — but the Bronze section separately lists "Debt and customer account management" as something *added* at Bronze. Similarly, discounts are only explicitly mentioned starting at Bronze. Please confirm: should basic customer records (no debt/credit) be available on Starter, with **debt/credit tracking specifically** gated to Bronze+? And should **discounts genuinely be unavailable on Starter**, given they were already built as a core feature earlier in this project? This needs a clear answer before `plan_capabilities` is seeded correctly.
2. **Should gating ever apply retroactively to a feature already in active use?** Per §3, the recommendation is no — but confirming explicitly: if an existing live organization (about to be set to Silver) is already using something Silver doesn't include, should that stay fully working indefinitely, or should Platform Super Admin get a one-time notice listing anything currently in use that the new plan doesn't cover, per organization?
