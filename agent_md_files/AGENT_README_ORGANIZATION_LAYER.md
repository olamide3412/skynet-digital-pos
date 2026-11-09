# POS System — Organization Layer (Client Accounts) — Phased Integration (Agent README)

**Read this whole document before touching any code.** This is a structural change to the top of the hierarchy of a **live system**. It is deliberately split into small phases, each independently shippable and reversible. **Do not combine phases. Do not skip verification steps. Stop after each phase and wait for confirmation before starting the next.**

---

## 1. Why This Exists

The system was built assuming one Super Admin manages all branches, and that Super Admin effectively *is* the client. The real business model is different:

- **Skynet Digital** operates the platform and sells it to multiple separate client businesses.
- Each client business may have **one or many branches** and needs to manage them together.
- Each client is on its own **plan** (branch limit, user limit, features, AI credits, renewal date).

Mapping "two branches to a user" has nowhere clean to attach a plan, a renewal date, or a branch limit. The fix is a proper middle layer: the **Organization**.

---

## 2. Target Hierarchy

```
Platform Super Admin (Skynet Digital)          <- the EXISTING "Super Admin" role, unchanged in code
  └── Organization (one per paying client)     <- NEW: holds plan/subscription/branch limit later
        ├── Organization Admin(s)              <- NEW role: manages their own organization's branches
        └── Branch (many)                      <- EXISTING, gains organization_id
              ├── Branch Admin                 <- unchanged
              └── Cashier / staff              <- unchanged
```

### Terminology map (important for reading every earlier README)
| Term in earlier READMEs | Meaning from now on |
|---|---|
| "Super Admin" | **Platform Super Admin** (Skynet Digital). Same role, same powers, same identifier in code. Nothing is renamed. |
| "Branch Admin" | Unchanged. |
| *(new)* "Organization Admin" | A client-side admin who manages **their own organization's** branches. Powers are granted gradually (Phase 6). |

**All earlier "Super Admin only" capabilities stay Platform-only** unless Phase 6 explicitly delegates them: creating/disabling branches, editing slugs, per-branch toggles (offline mode, AI, held sales visibility, batch tracking), Global Item Pool, Import Library, mail settings, AI provider settings, broadcast notifications, homepage pricing management, plan/subscription changes.

---

## 3. How the System Knows Which Organization a Branch Belongs To

- Every branch gets an `organization_id`.
- **Creating a branch always requires choosing an organization** (Platform Super Admin picks it from a list; if an Organization Admin is ever allowed to create branches in Phase 6, their own organization is assigned automatically and cannot be changed).
- The branch-limit check (later, in the plan enforcement work) becomes: *"How many branches does this specific organization have? Would one more exceed its plan's `max_branches`?"*
- Data tenancy does **not** change: sales, items, stock, users etc. remain scoped by `branch_id` exactly as today. The organization is a grouping **above** branches, resolved via `branch -> organization`. See guardrails below.

---

## 4. Guardrails — Never Do These

1. **Do not rename the existing Super Admin role or permission identifiers.** Only documentation/labels change (optionally UI labels, last, cosmetic only).
2. **Do not change any branch URL or the slug scheme.** Branch URLs (`/felix-enterprise`) stay exactly as they are and stay **globally unique**. Changing URLs would break bookmarks, the PWA service worker scope, offline caches, and splash screens. Do not nest branch URLs under an organization.
3. **Do not add `organization_id` to transactional tables** (sales, items, stock, held sales, expenses, batches, etc.). Only `branches` and (for Organization Admins) `users`. Everything else derives through `branch_id`.
4. **Do not touch offline sync, IndexedDB structures, or service worker logic.**
5. **Do not make `branches.organization_id` NOT NULL before Phase 5**, and never before the backfill is verified.
6. **Do not reduce any existing Platform Super Admin power.**
7. **All migrations before Phase 5 are additive only** (new tables, new nullable columns). No drops, renames, or type changes.
8. **New routes must not collide with branch slugs.** Branch routes live at the root (`/{branch-slug}`), so any new prefix (e.g. `/org`) could shadow a real branch. See Phase 0.

---

## 5. Standard Verification Checklist (run after EVERY phase, on staging first, then production)

Log in as Platform Super Admin, Branch Admin, and Cashier, then confirm:
- Login works for all three; branch URLs load; splash screens behave
- Complete an online sale; complete an offline sale and confirm it syncs
- Hold and resume a sale (online and offline)
- Discounts, IMEI item sale, and a barcode scan still work
- Reports load; expense log works; notifications badge works
- AI chat answers a question; a receipt number lookup works
- Super Admin can still create/edit/disable a branch and its users, and reset a password
- No new errors in application logs

**If any item fails: stop, roll back that phase, report the failure. Do not continue.**

---

## Phase 0 — Audit & Safety Net (no behavior change)

**Goal:** Know exactly what depends on "Super Admin = top of everything" before changing anything.

**Do:**
1. Take a full database backup and restore it to a staging environment. All phases are rehearsed on staging with production-like data first.
2. Audit and document (create `docs/organization-audit.md`) every place that references Super Admin or lists branches/users across the platform: middleware, policies/gates, route groups, seeders, Blade/Vue conditionals, API endpoints, exports, queued jobs, scheduled commands (expiry alerts, reorder checks, notification jobs), notification recipient queries and broadcast targeting, the AI module's queries, receipt lookup, mail/AI provider settings, homepage default-branch logic.
3. Classify each finding as **Platform-only / Organization-scoped later / Branch-scoped (unchanged)**.
4. **Reserved slugs:** list all existing branch slugs and check none collide with planned route prefixes or reserved words (`org`, `organization`, `organizations`, `platform`, `admin`, `super-admin`, `api`, `login`, `logout`, `register`, `dashboard`, `settings`). Report any collision immediately — it must be resolved before Phase 4.
5. Add a config feature flag `ORGANIZATIONS_ENABLED` (default `false`). Everything from Phase 3 onward that changes behavior is gated by it.

**Do NOT:** change any behavior or schema.

**Confirmed facts (answered):** The installation currently has **two clients, one branch each** (two branches total, no shared clients), and **every existing Super Admin account belongs to Skynet Digital**. No installation merge is needed. Phase 0 should still verify both facts against the actual database (exactly two branches; list every user holding the Super Admin role and report them so the owner can confirm each one is a Skynet account).

**Safe to stop here?** Yes.

---

## Phase 1 — Additive Schema Only

**Goal:** Create the storage, with nothing reading it yet.

**Migrations (new files only, all additive):**
- `organizations`: `id`, `name`, `contact_name` (nullable), `contact_email` (nullable), `contact_phone` (nullable), `address` (nullable), `status` (enum `active`/`suspended`, default `active` — **not wired to any behavior yet**), `notes` (nullable), timestamps.
- `branches.organization_id`: **nullable**, indexed, foreign key to `organizations` (restrict on delete).
- `users.organization_id`: **nullable**, indexed, foreign key to `organizations`. Used only for Organization Admin accounts; branch staff derive their organization through their branch.
- `organization_activity_log`: `id`, `organization_id` (nullable), `actor_user_id`, `action`, `details` (json), `created_at` — simple audit trail for organization changes (assignments, moves, plan changes later).

**Do NOT:** write any code that reads or writes these yet.

**Verify:** run migrations on staging → full checklist (§5). **Rollback:** roll back these migrations (only new tables/nullable columns, so this is clean).

**Safe to stop here?** Yes. Behavior is identical to today.

---

## Phase 2 — Backfill Existing Branches Into an Organization

**Goal:** Every existing branch belongs to an organization, without changing anything users see.

**Do:**
1. Build an idempotent Artisan command `organizations:backfill` with:
   - `--dry-run` (prints what would happen, changes nothing)
   - `--map=path/to/file.csv` (optional: columns `branch_slug,organization_name` — used if existing branches already belong to different clients)
   - `--rollback` (sets `organization_id` back to null for branches this command assigned; uses the activity log to know which)
2. **The `--map` file is required — there is no "Default Organization" and no fallback that lumps branches together.** The installation has two clients with one branch each, so the map contains two rows (`branch_slug,organization_name`, optionally contact name/email/phone). The command creates one organization per distinct `organization_name` and assigns each branch to its organization. **If any branch with a null `organization_id` is not present in the map, the command must abort and list the unmapped branches** rather than guess. The owner supplies the two slug/name pairs.
3. Log every assignment to `organization_activity_log`.
4. Run on staging with `--dry-run`, review, run for real, verify, then repeat the same on production.

**Verify:** `SELECT COUNT(*) FROM branches WHERE organization_id IS NULL` returns 0; branch and user counts unchanged; full checklist (§5). Do **not** make the column NOT NULL yet.

**Rollback:** `organizations:backfill --rollback`.

**Safe to stop here?** Yes. Still no behavior change.

---

## Phase 3 — Organization Management for Platform Super Admin (behind the flag)

**Goal:** Give Platform Super Admin the tools to manage organizations. Organization Admins still have **no powers**.

**Do:**
1. Add the `organization_admin` role to the existing RBAC with **zero permissions** initially. Do not touch the Super Admin role.
2. Add a Platform Super Admin screen (only when `ORGANIZATIONS_ENABLED` is true): list/create/edit organizations; see each organization's branches; create an Organization Admin account bound to an organization (consistent with existing user-management patterns, including disable and password reset).
3. Add a **"Move branch to another organization"** action with confirmation, written to the activity log. Moving a branch only changes `branches.organization_id` — users, sales, stock, and AI usage history stay attached to the branch.
4. Branch creation: add a **required Organization selector** with **no preselected value and no silent default**. If the field is omitted, the backend **rejects the request with a validation error** rather than assigning any organization. (With different clients in one installation, a silent default could place a new client's branch under another client's organization. Only Skynet creates branches, so one extra required field is a safe, small change.)
5. Enforce the **reserved-slug list** (Phase 0) at branch creation and slug edit.

**Verify:** create a test organization and a test branch under it; existing branches and all roles behave as before; full checklist (§5). **Rollback:** turn `ORGANIZATIONS_ENABLED` off (screens disappear, data remains harmlessly).

**Safe to stop here?** Yes.

---

## Phase 4 — Organization-Scoped Access + Tenant Isolation Tests (behind the flag)

**Goal:** Let an Organization Admin log in and see **only their own organization's** branches — read-oriented — with proven isolation.

**Do:**
1. Add a scope helper (e.g. `Branch::visibleTo($user)`): Platform Super Admin → all branches; Organization Admin → branches where `organization_id` equals their own; branch users → own branch only (**existing behavior, unchanged**).
2. Add an **Organization Dashboard** for Organization Admins: their branches (status, today/week sales summary, unresolved conflicts, expiry/low-stock counts) and an "Open branch" action. **Do not** give them any create/edit/delete power yet.
3. Organization-level reports by **reusing the existing report queries** with `branch_id IN (organization's branches)`. Do not write new calculation logic.
4. Use a route prefix that cannot collide with a branch slug (confirmed in Phase 0).
5. **AI features:** the chat assistant and receipt lookup must scope an Organization Admin to their own organization's branches. Platform Super Admin remains cross-organization.
6. **Required deliverable — an automated tenant-isolation test suite.** For each of: branches, users, sales/receipts (including receipt lookup by number), items, expenses, held sales, notifications, reports and exports, file/receipt downloads, IMEI lookup, AI chat queries — assert that an Organization A admin receives 403/404 for Organization B's data **both by list and by direct ID/URL guessing**, that Platform Super Admin still sees everything, and that branch users are unaffected.

**Verify:** the isolation suite passes fully; checklist (§5). **Rollback:** turn `ORGANIZATIONS_ENABLED` off — Organization routes return 404, all other behavior is as before.

**Safe to stop here?** Yes, but do **not** go on to Phase 5 until Phase 4 has been stable in production for a reasonable period (recommend at least one to two weeks) with no isolation issues. *(If Phase 4 is deferred — see §12 — Phase 5 instead waits on Phase 3 being stable for the same period.)*

---

## Phase 5 — Enforce Integrity

**Goal:** Make the model strict now that it is proven.

**Do:**
1. Pre-check query: confirm zero branches have a null `organization_id`. **Abort if any are found.**
2. New migration: make `branches.organization_id` NOT NULL (foreign key remains restrict-on-delete).
3. Make organization mandatory in branch creation (no silent default any more).

**Rollback:** migration `down()` makes the column nullable again.

**Safe to stop here?** Yes.

---

## Phase 6 — Delegate to Organization Admin (incrementally)

**Goal:** Grant limited powers, each behind its own per-organization toggle set by Platform Super Admin.

| Capability | Suggested default |
|---|---|
| View branches and organization-level reports | On |
| Manage Branch Admin and staff accounts within their organization | On |
| Enter a branch panel with Branch Admin-equivalent access (actions logged) | Off — decide per organization |
| Create branches within the plan limit (organization auto-assigned) | Off — decide per organization |

**Always remains Platform-only:** slug edits, branch disable/enable, per-branch toggles (offline mode, AI, held sales visibility, batch tracking), plan and subscription changes, Global Item Pool and Import Library, mail and AI provider settings, broadcast notifications, homepage/pricing management.

Enable one capability at a time, re-running the isolation suite and checklist after each.

---

## 6. Status of the Plan/Subscription READMEs (Parts 1–3)

**The original three documents (Subscription, Feature Enforcement, AI Credits) were never executed or used.** Nothing from them exists in the live system today — there is no legacy `subscription` table, no `plan_capabilities` table, no `ai_credit_usage` table, and no enforcement logic to unwind or migrate away from. They can be treated as **obsolete drafts, fully superseded**, not as something that needs correcting on top of already-built work.

**They have already been regenerated, organization-scoped, and are ready to use once this document's prerequisites are met:**
- `AGENT_README_PLAN_1_SUBSCRIPTION.md` — subscription now belongs to an **organization** (`organization_id`), not the installation.
- `AGENT_README_PLAN_2_FEATURE_ENFORCEMENT.md` — `max_branches` counted **per organization**; `max_users_per_branch` stays per branch; grandfathering evaluated per organization, isolated from other organizations.
- `AGENT_README_PLAN_3_AI_CREDITS.md` — Starter's pool is now **organization-wide**, not installation-wide; each organization's credit pool is fully isolated from every other organization's.

**Do not execute these three until this Organization Layer README has reached at least Phase 3** (organization management screens exist and are stable) — per §12's recommended path for the current two-client situation, Phase 5 (making `organization_id` required) is also recommended first, though not strictly a hard blocker the way Phase 1 and Phase 3 are. Phase 4 and Phase 6 are **not** required before using the three Plan documents — they only become relevant once a client has more than one branch or needs delegated access.

**Order once this document is ready:** Phase 0 → Phase 1 → Phase 2 → Phase 3 → Phase 5 → Part 1 (Subscription) → Part 3 (AI Credits, since it only needs Part 2's `plan_capabilities` table, not its full enforcement) → Part 2 last (highest-risk enforcement work, done once the other two are stable).

---

## 7. Rollback Matrix

| Phase | Rollback |
|---|---|
| 0 | Nothing to roll back |
| 1 | Roll back the additive migrations |
| 2 | `organizations:backfill --rollback` |
| 3 | `ORGANIZATIONS_ENABLED=false` |
| 4 | `ORGANIZATIONS_ENABLED=false` |
| 5 | Migration `down()` (column back to nullable) |
| 6 | Turn the delegated toggles off |

---

## 8. Forward Note — E-commerce Link

The planned online-store link (single organization, one store) should attach to an **Organization**, with a setting choosing which branch supplies stock. Don't build that link at branch level; it will otherwise need reworking.

---

## 9. Explicit Non-Goals

- No change to branch URLs, slug uniqueness, offline logic, or the transactional data model.
- No automatic lockout on renewal or suspension in this document. The `status` column is stored but not wired to behavior.
- No per-organization private Item Pool or Import Library (see Open Questions).

---

## 10. Summary of What Changes for Whom

- **Platform Super Admin (you):** same powers; gains Organization management; picks an organization when creating a branch.
- **Organization Admin (new):** sees and, gradually, manages only their own organization's branches.
- **Branch Admin / Cashier:** nothing changes.

---

## 11. Open Questions (answer before the indicated phase)

1. **RESOLVED — Existing data:** The installation has two clients with one branch each. No installation merge is needed. The Phase 2 backfill uses a two-row mapping file (see Phase 2).
2. **RESOLVED — Existing Super Admin accounts:** All belong to Skynet Digital, so no account needs converting to an Organization Admin. Phase 0 still verifies this against the actual database.
3. **(Before Phase 6) Branch creation by Organization Admin:** Should clients ever create their own branches within their plan limit, or should branch creation always stay with Skynet?
4. **Branch limit counting:** Should **disabled** branches still count toward an organization's `max_branches`? Recommendation: yes, to prevent gaming the limit.
5. **Entering a branch:** May an Organization Admin operate inside a branch panel as a Branch Admin (with actions logged), or view reports only?
6. **Organization suspension:** Should `suspended` eventually disable all of an organization's branches (like the existing branch disable), or remain informational only?
7. **Private libraries:** Should organizations eventually get their own private Item Pool/Import Library, or does the Platform-owned library stay shared and Skynet-only?
8. **Notifications routing:** Should Organization Admins also receive their branches' alerts (stock conflicts, expiry, reorder), in addition to each Branch Admin?
9. **Multiple Organization Admins:** Is more than one Organization Admin per organization allowed? (Assumed yes.)

---

## 12. Recommended Path for the Current Situation (Two Clients, One Branch Each)

Right now neither client has more than one branch, so nobody needs to manage several branches together yet. That means the organization layer's cross-branch features can wait, while the parts needed for **plans per client** are needed now.

**Do now (in order):**
1. Phase 0 — audit, verify the two facts above, add the feature flag.
2. Phase 1 — additive schema.
3. Phase 2 — backfill with the two-row mapping file (two organizations, one branch each).
4. Phase 3 — organization management screens for Platform Super Admin, with the required (no-default) organization selector on branch creation. Creating Organization Admin accounts can be skipped until a client needs one.
5. Phase 5 — make `organization_id` required, after Phase 3 has been stable for one to two weeks.
6. Then run the three already-regenerated, organization-scoped Plan documents, in this order: `AGENT_README_PLAN_1_SUBSCRIPTION.md` → `AGENT_README_PLAN_3_AI_CREDITS.md` → `AGENT_README_PLAN_2_FEATURE_ENFORCEMENT.md` (enforcement last, since it's the highest-risk of the three). Each client organization gets its own subscription, both set to Silver manually, per Part 1.

**Defer until a client actually has two or more branches:** Phase 4 (organization-scoped access and the isolation test suite) and Phase 6 (delegating powers to Organization Admins). Nothing in the plan and subscription work depends on them, so leaving them out keeps the risk on the live system lower.

**When the first multi-branch client arrives:** create their organization, add their branches under it through the Phase 3 screens, then build Phase 4 before giving that client's Organization Admin any access.
