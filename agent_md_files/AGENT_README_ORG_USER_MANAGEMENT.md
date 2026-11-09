# POS System — Organization Portal: User Management (Agent README)

**Note:** This adds a **Users** area to the **Organization portal**, so an Organization Admin can manage the staff of **their own organization's branches**. The first version covers **viewing users, moving a user to another branch (a transfer), and disabling/enabling a user**. It must be built so that later capabilities (password reset, role and permission updates, editing details, and so on) can be added **the same way they already work for Platform Super Admin**, without redesign. See §9.

**Prerequisites:** The Organization portal with the Organization Admin role, organization-scoped access, and the delegation toggle for managing staff accounts (Organization Layer README, Phases 4 and 6). Verify these exist in Step 0 before building.

---

## 1. Goal

- Organization Admin can see every user across all branches of their organization.
- Organization Admin can **move a user from one branch to another branch in the same organization** (for example a staff transfer).
- Organization Admin can **disable or re-enable** a user.
- Every action is permission-checked on the server, isolated per organization, and logged.
- The structure makes future actions (password reset, role/permission updates, edit details) additive.

---

## 2. Core Principles

1. **Organization isolation is absolute.** An Organization Admin can only see and act on users whose branch belongs to **their own** organization.
2. **No privilege escalation.** An actor can never create, edit or assign anything more powerful than themselves (see §4).
3. **Records stay where they happened.** Moving or disabling a user never moves, edits or deletes sales, till sessions, held sales, IMEI history or any other record. History keeps the original branch and cashier.
4. **One implementation, many entry points.** Reuse the existing Super Admin user-management logic. Do not write a parallel, slightly different version (§9).
5. **Additive and safe.** Existing Super Admin and Branch Admin user management behaves exactly as before.

---

## 3. Step 0 — Discovery First (No Changes)

Report findings before building:
1. How are **Super Admin's disable/enable** (and password reset, role update, edit) currently implemented? Where does that logic live (controllers, services, policies)?
2. How are **roles and permissions** scoped: global, or per branch? Can a role exist in one branch but not another?
3. Does a user belong to **exactly one branch**? (This README assumes yes.) Is the **username/email unique globally or only within a branch**?
4. How does a **disabled** user get blocked today, and are their active sessions ended immediately?
5. How does **offline sync authorize** queued sales? What happens if a queued sale's cashier no longer belongs to the branch it was sold in? (See §8.)
6. Is there a **user limit per branch** check yet (plan enforcement)? If not, find where branch user creation happens so one shared check can be added later.
7. How does the Organization portal currently check the Phase 6 delegation toggle for managing staff accounts?

If anything in §8 would **strand or misattribute offline sales** after a move, stop and report it before building the move.

---

## 4. Who Can Do What

| Actor | Scope | Notes |
|---|---|---|
| **Platform Super Admin** | Any user, any organization | Unchanged. Can also manage Organization Admin accounts. |
| **Organization Admin** | Users in branches of **their own organization** | Manages Branch Admins and branch staff. Cannot touch Organization Admin or Super Admin accounts. |
| **Branch Admin** | Users in **their own branch** | Unchanged from today (create staff, enable/disable). |

**Rules for Organization Admin:**
- Cannot view, edit, move or disable another **Organization Admin** or any **Super Admin** account (Platform Super Admin only).
- Cannot move or disable **themselves**.
- **No escalation:** when roles and permissions are assigned (now at move time, later in full role editing), an actor may only assign roles whose permissions are a **subset of their own**, and may never assign Organization Admin or Super Admin roles.
- All actions are enforced **server-side** through one policy, not just by hiding buttons.
- The screen respects the Phase 6 delegation toggle (**Manage Branch Admin and staff accounts**). If Platform Super Admin turned it off for an organization, move and disable are unavailable there (a read-only list is still fine).

---

## 5. The Users Screen

- **List** of all users in the organization: name, username/email, role(s), branch, status (active/disabled), and last login if the system already records it.
- **Search** by name, username or email. **Filter** by branch, role and status.
- **Server-side pagination** with a capped page size, following the project's convention of never loading everything at once.
- **Row actions:** Move to another branch, Disable, Enable.
- **User detail view** (drawer or page) showing the profile summary, current status, and **branch history** (§10). Build this view as a container that can take more sections later (for example Roles & Permissions, Security). Only render sections that exist now; do not show empty or "coming soon" tabs.

---

## 6. Move User to Another Branch

### 6.1 Flow
1. Organization Admin chooses **Move to another branch** on a user.
2. A dialog asks for:
   - **Destination branch** — only **active branches in the same organization**, excluding the current one.
   - **Role in the destination branch** — **required**. Never silently carry the old role's permissions across; default to the same-named role if one exists there, otherwise the admin must pick.
   - **Reason** (see Open Questions).
3. The dialog shows the **pre-check results** (§6.2) before the admin confirms.
4. On confirm, the move runs as **one atomic transaction** (§6.3).
5. A confirmation shows the user's **new login link** (the destination branch's URL) with a Copy button, because staff log in through their branch's own URL and the old link will no longer work for them.

### 6.2 Pre-Checks
| Check | Type | Behavior |
|---|---|---|
| Destination is in the same organization and active | Block | Cross-organization moves are not allowed by this feature, for anyone. |
| Target is not an Organization Admin / Super Admin, and not the actor | Block | |
| User has an **open till session** | Block | Tell the admin to close it first (a Branch Admin can force-close, per the Opening Till README). A cashier can only have one open session at a time. |
| Username/email would **conflict** with an existing user at the destination (if uniqueness is per branch) | Block | Clear message. |
| Destination branch is at its **user limit** | Block | Only once plan enforcement exists; treat a move as adding a user to the destination (see §12). |
| Role at destination chosen and valid there | Block | |
| User has **held sales** at the current branch | Warn | They stay with the old branch and cannot be resumed from the new one. |
| User may have **unsynced offline sales** | Warn | Ask the admin to have the user sync before moving (see §8). |
| User is the **last active Branch Admin** of the source branch | Warn | Confirm before continuing. |

### 6.3 What Happens on Move (All or Nothing)
1. Update the user's branch.
2. Replace the user's role(s) with the selected destination role(s).
3. Close the previous branch history row and open a new one (§10).
4. **End the user's active sessions** so they must log in again at the new branch.
5. Write the audit log entry (§11).
6. Send the notifications (§11).

If any step fails, nothing is changed.

---

## 7. Disable / Enable

- **Disable** blocks the user from logging in and **ends their active sessions immediately**. It never deletes the user or any history. This matches how disable already behaves for Branch Admin and Super Admin; reuse that exact logic.
- **Enable** restores access.
- Guards: cannot disable yourself, cannot disable Organization Admin or Super Admin accounts, and a warning (not a block) if disabling the **last active Branch Admin** of a branch.
- Record who did it, when, and the reason.

---

## 8. Offline & Session Edge Cases (Must Be Checked, Not Assumed)

- **Queued offline sales across a move:** a cashier may sell offline, then be moved before syncing. The sale already carries the branch it was made in. After the move, those queued sales must still **sync and be attributed to the original branch and original cashier**, and must not be rejected just because the user's *current* branch is different, nor lost, nor attributed to the new branch. If the current sync authorization would reject them, report it (Step 0.5). A simple approach is to authorize queued sales against the user's **branch history** (§10), but confirm the right approach with the owner before building.
- **Disabled user with an offline session:** an offline session can stay valid locally for a while (per the Offline Sales README). Sales the user already made are real and must still sync when they reconnect (flagged for review is fine). They just cannot start a new session once online.
- Both user management screens are administrative actions and need **no offline support**.

---

## 9. Designing for Future Capabilities

The goal is that adding password reset, role and permission updates, edit details or create user later is **one new action, one permission and one button**, with scoping, authorization and audit already in place.

### 9.1 Structure
- **One authorization policy, scope-based:** Platform Super Admin → any user; Organization Admin → users in their organization (excluding Organization Admin/Super Admin accounts); Branch Admin → their own branch. Every user action goes through it.
- **One action class per capability** (for example `MoveUserToBranch`, `DisableUser`, `EnableUser`), each called by whichever portal needs it. Future actions (`ResetUserPassword`, `UpdateUserRoles`, `UpdateUserDetails`, `CreateUser`) follow the same shape.
- **One reusable users screen** driven by the actor's scope and permissions, with action buttons shown per permission, so the Super Admin and Organization portals share components instead of duplicating them.
- Reuse existing Super Admin logic where it exists. If it is tightly tied to Super Admin controllers, do **not refactor it in this task**. Have the new screen call the existing logic for disable/enable unchanged, build only the new move action as a shared action, and note the code for later consolidation.

### 9.2 Capability Matrix
| Capability | Super Admin | Organization Admin | Branch Admin | This task |
|---|---|---|---|---|
| View users | All | Own organization | Own branch | **Build** |
| Disable / enable | Any user | Own organization (not Org Admin/Super Admin accounts) | Own branch (exists) | **Build** |
| Move to another branch | Same-organization moves | Same-organization moves | — | **Build** |
| Password reset | Any user (exists) | Own organization | — | Later |
| Update roles & permissions | Exists | Own organization, no escalation (§4) | — | Later |
| Edit user details | Exists | Own organization | — | Later |
| Create user | Exists | Own organization | Own branch (exists) | Later (see Open Questions) |

### 9.3 Permissions
Define permissions now using a consistent naming pattern (for example `org_users.view`, `org_users.move`, `org_users.disable`), so later ones (`org_users.reset_password`, `org_users.update_roles`, `org_users.edit`, `org_users.create`) simply join the same group. Use the project's existing RBAC. Do not invent a separate system.

---

## 10. Data Model & Migration

- **New table `user_branch_history`:** `id`, `user_id`, `branch_id`, `started_at`, `ended_at` (nullable), `moved_by` (nullable), `reason` (nullable). It records which branch a user belonged to over time, shown on the user detail view and usable for sync authorization (§8) and reporting.
- **No backfill of existing users.** Create history **lazily**: on a user's first move, create the "from" row (started at the user's creation date) and the new "to" row. Users who never move simply have no history rows, which means they have never moved.
- No change to the users table beyond what already supports disable/enable. Confirm in Step 0 whether fields for who disabled a user and why already exist; if not, add them with a new migration.
- Live system: **new migrations only**, never edit old ones. Transfers do not touch any historical records.

---

## 11. Audit & Notifications

- **Audit:** log every move, disable and enable using the existing `organization_activity_log` (action, actor, target user, from/to branch, reason, timestamp).
- **Notifications (existing Notifications system):** when a user is moved, notify the **source** Branch Admin ("[Name] was transferred out") and the **destination** Branch Admin ("[Name] has joined your branch"). When a user is disabled or enabled, notify their current Branch Admin. No email is required.

---

## 12. Interaction With Other Features

- **Plan enforcement (when built):** a move into a branch counts as adding a user there, so the destination's `max_users_per_branch` applies. Route this through one shared helper (for example `canAddUserToBranch`) so user creation and user moves share the same check.
- **Organization isolation tests:** add user management to the tenant isolation suite required by the Organization Layer README (§Phase 4).
- **Till, held sales, IMEI, reports:** unchanged. Records keep the branch and cashier they were created with.
- **Ecommerce, AI credits, discounts, offline cache:** unaffected.

---

## 13. What Must NOT Change

- Existing Super Admin and Branch Admin user management (behavior, permissions, screens).
- Login flow, role and permission model, and the sessions of unaffected users.
- Sales, till session, held sale, IMEI, expense and report records. They are never moved, edited, re-attributed or deleted by a branch move or a disable.
- Platform Super Admin's existing powers.

---

## 14. Out of Scope

Moving users **between organizations**; bulk moves or CSV transfers; password reset, role and permission updates, edit details and create user (design hooks only, §9); deleting users; managing Organization Admin accounts from the Organization portal; impersonation or "log in as"; scheduled (future-dated) moves; email notifications.

---

## 15. Testing Checklist

- **Isolation:** Organization A's admin cannot list, view, move or disable users of Organization B, by list or by ID guessing, and cannot move a user into a branch of another organization.
- **Escalation:** an Organization Admin cannot touch Organization Admin/Super Admin accounts, cannot act on themselves, and cannot assign a role beyond their own permissions.
- **Move:** moves succeed with a valid role at the destination. The user can log in at the new branch URL and not at the old one. Sessions at the old branch end.
- **Blocks:** open till session, username/email conflict, inactive or other-organization destination, and (when present) the destination user limit each block the move with a clear message. Warnings appear for held sales, unsynced sales, and last active Branch Admin.
- **Atomicity:** force a failure mid-move and confirm nothing partially changed.
- **Offline:** queue an offline sale, move the user, then sync. The sale lands in the original branch under the original cashier. A disabled user's queued sales still sync.
- **Disable/enable:** login blocked immediately, active session ended, history untouched, re-enable restores access.
- **History/audit:** branch history, audit entries and notifications are created correctly.
- **Regression:** Super Admin and Branch Admin user management behave exactly as before. Existing records and reports show the correct branch and cashier after a move.
- **Delegation toggle:** with "manage staff accounts" off for an organization, move and disable are unavailable.

---

## 16. Open Questions

1. **Create user on the Organization portal:** should creating users in the organization's branches be part of this task now, or later with password reset and role updates? (Currently listed as later.)
2. **Organization Admin accounts:** should a primary Organization Admin ever be able to manage other Organization Admins? Recommended: Platform Super Admin only.
3. **Reason field:** required or optional for moves and disables? Recommended: required for both (a short text), optional for enable.
4. **Plan user limits:** once plan enforcement exists, should **disabled** users count toward a branch's user limit? Recommended: no, only active users count.
5. **Scheduled moves:** is an immediate move enough for v1 (recommended), or do you want an effective date?
6. **Last active admin:** warn only (as written), or block disabling or moving the last active Branch Admin of a branch?
7. **Super Admin's portal:** should the same Move action also be added to Platform Super Admin's user management (same shared action, same-organization restriction), since one implementation serves both?
8. **Offline sale authorization:** if Step 0.5 shows queued sales would be rejected after a move, confirm the approach (for example authorizing against the user's branch history).
