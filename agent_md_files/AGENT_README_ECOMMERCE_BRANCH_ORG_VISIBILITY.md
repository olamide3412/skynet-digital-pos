# POS System — Branch & Organization Visibility into Ecommerce Activity (Agent README)

**Scope of this document:** A targeted addition on top of the already-executed `AGENT_README_ECOMMERCE_INTEGRATION_COMPLETE.md`. This builds the capability that document's own §37 explicitly flagged as deferred: *"If operational visibility is needed later, a separate read only permission can be introduced for selected branch users to view reservation or inventory movement records."* That's exactly what this document now specifies, extended to Organization level too, and including `commerce_item_mappings` which wasn't mentioned in that original deferral.

**What does NOT change:** Connection management, credential generation/rotation/viewing, and the ability to connect/disconnect/enable/disable the integration itself **remain strictly Super Admin only**, exactly as §36/§37 already establish. This document grants visibility into **operational data** (reservations, movements, mappings) — never configuration or credential authority.

---

## 1. Goal

Let Branch Admin see (and, for two narrow cases, take a limited corrective action on) their own branch's ecommerce activity — `commerce_reservations`, `commerce_inventory_movements`, and `commerce_item_mappings` — and let Organization-level views aggregate this across an organization's branches, without touching connection/credential authority at all.

---

## 2. Branch Admin: New "Ecommerce Activity" Section

### 2.1 Where It Lives
- A new section/tab within **Branch Admin's own branch panel** (their normal settings/dashboard area — not Super Admin's Branch Directory, which stays exactly as-is for connection management).
- Gated behind a new permission (per the existing RBAC system already established in this project): **`view_ecommerce_activity`** — toggleable per role, off by default for Cashiers, assignable to Branch Admin or trusted staff as the Branch Admin sees fit (consistent with how other permissions in this project are already delegated within a branch).

### 2.2 What It Shows (Read-Only Core)
- **Reservations** (`commerce_reservations`): active, expired, and released reservations for that branch — item, quantity, external order reference, status, created/expires timestamps.
- **Inventory Movements** (`commerce_inventory_movements`): confirmed stock deductions/returns originating from ecommerce orders for that branch — item, quantity, movement type, external order reference, timestamp.
- **Item Mappings** (`commerce_item_mappings`): which of that branch's items are currently mapped to the connected ecommerce catalog, including `is_active`, `registered_at`, `last_seen_at`, `unregistered_at` — so Branch Admin can see which of their items are actually live online without needing to ask Super Admin or log into the ecommerce admin separately.
- All three lists should be **searchable** (by item name/barcode, by external order reference) and **filterable** (by status, by date range) — reuse the existing reporting UI patterns already established elsewhere in this project (date range pickers, filters) rather than building a one-off layout.

### 2.3 What It Allows (Two Narrow, Specific Actions — Not Full Management)
Beyond read-only viewing, Branch Admin (with the permission in §2.1) can take exactly two corrective actions, both clearly scoped and logged:

- **Manually release a stuck/expired reservation** — if a reservation is clearly expired or clearly stuck (e.g., an abandoned online checkout that never confirmed or released itself), Branch Admin can manually release it to free the reserved stock back to front-store availability. This does not touch the ecommerce side's order state — it's a POS-side stock correction only.
- **Manually deactivate a stale item mapping** — if a mapping clearly no longer reflects reality (e.g., the POS item was deleted or the mapping is obviously broken), Branch Admin can mark it `is_active = false` on the POS side, to stop it from being treated as "still live online" in POS-side reporting. This does **not** reach into the ecommerce catalog and does **not** unpublish anything there — it only corrects the POS-side record of the mapping's status.
- **Nothing else is writable from this screen.** No creating new mappings, no editing reservation quantities, no touching `commerce_inventory_movements` (that's a confirmed historical log — it must stay fully read-only, never editable, consistent with how financial/transactional history is treated everywhere else in this project).
- Both actions should be logged (who did it, when, which record) for the same accountability reasons already established elsewhere in this project (e.g., the branch edit activity log from the Super Admin Branch Panel README).

---

## 3. Organization-Level Visibility

### 3.1 For Platform Super Admin (Available Now, No Dependency)
- Super Admin already sees everything per the original document's §36 — this is just a **convenience aggregation view**, not new authority: within the Organization management screens (per the Organization Layer README), add an **"Ecommerce Activity"** tab showing reservations/movements/mappings **aggregated across every branch belonging to that organization**, not just one branch at a time. Useful the moment an organization has more than one branch.
- This reuses the exact same underlying data and query logic as §2 — just scoped by `organization_id` (via `branch -> organization`) instead of a single `branch_id`.

### 3.2 For Organization Admin (Only Once Delegated — Organization Layer Phase 6)
- If/when Organization Admin access is delegated (per the Organization Layer README's Phase 6), the same `view_ecommerce_activity` permission concept extends naturally — an Organization Admin with this permission sees the aggregated view from §3.1 for their own organization only, same read-only-plus-two-actions model as Branch Admin, just scoped wider.
- **This part has a real dependency and should not be built until Organization Phase 6 is actually reached** — §3.1 (the Platform Super Admin convenience view) has no such dependency and can be built now.

---

## 4. Data Model

**No new tables required.** This reuses `commerce_reservations`, `commerce_inventory_movements`, and `commerce_item_mappings` exactly as already defined in the original document (§8.2, §8.4, §40A) — this document only adds new **read queries** (scoped by `branch_id` or `organization_id`) and **two narrow write actions** (release reservation, deactivate mapping) on top of existing tables.

- Add an `activity_log` entry type (or reuse an existing generic activity log if one already exists in this project) for the two write actions in §2.3, so who-did-what-when is recorded.

---

## 5. What Should NOT Change

- Connection management, credential generation/rotation/viewing, and connect/disconnect/enable/disable authority stay **exclusively Super Admin**, exactly as §36/§37 of the original document already establish — nothing in this document grants Branch Admin or Organization Admin any of that.
- `commerce_inventory_movements` stays fully read-only for everyone except the system itself writing confirmed movements — no role gets edit/delete access to this log, ever.
- No change to the actual sync/reservation/confirmation logic already specified in the original document — this is a visibility and narrow-correction layer on top, not a change to how ecommerce orders are processed.

---

## 6. Notes for the Agent

- Enforce the `view_ecommerce_activity` permission (and the two write actions) **server-side**, consistent with the RBAC principle already established throughout this project — not just hidden UI elements.
- Reuse the Organization Layer's tenant-isolation guarantees (§37A/§50.16 of the main document) for the new organization-scoped queries in §3.1 — a Branch Admin or Organization Admin must never be able to see another organization's ecommerce activity through this screen, same guarantee as everywhere else.
- Test explicitly: Branch Admin sees only their own branch's data; releasing a reservation actually frees the stock correctly; deactivating a mapping doesn't touch the ecommerce side; Platform Super Admin's organization-level aggregate view correctly combines multiple branches once an organization has more than one.

---

## 7. Open Questions

1. **Scope of "manage" confirmed:** Does releasing a stuck reservation and deactivating a stale mapping (§2.3) cover everything you meant by "manage," or is there a specific additional action you had in mind beyond these two?
2. **Permission granularity:** Should `view_ecommerce_activity` be one single permission covering all three data types (reservations, movements, mappings) together, or would you prefer it split into separate permissions (e.g., a Branch Admin could be given reservation visibility without mapping visibility)? Recommend starting with one combined permission for simplicity, splitting later only if a real need arises.
3. **Organization Admin priority:** Given §3.2 depends on Organization Layer Phase 6 (not yet reached, per the recommended path in that document), should this document's Organization-level work for now just be §3.1 (Platform Super Admin's aggregate view), with §3.2 revisited once Phase 6 is actually underway?
