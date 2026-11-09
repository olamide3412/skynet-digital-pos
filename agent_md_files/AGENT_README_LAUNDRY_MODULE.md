# POS System — Laundry Module (Agent README)

**Note:** Laundry is added as an **optional module controlled by branch settings**. It is **not** a new Business Sector. The **Business Sector dropdown stays exactly as it is** (General Commerce / Retail and Health / Pharmacy). A branch can run **POS only** (every branch today), **POS and Laundry together** (a mixed store), or **Laundry only**. Laundry is built as a **self-contained module with its own Vue pages and layout**. It is **not merged into the POS Vue components**. It reuses the **existing customers**, and a customer is required when taking an order.

**This is a live system.** Every existing branch must behave exactly as it does today. Read §2, §3, §4 and §5 first.

---

## 1. Goal

- Laundry is switched on per branch. A branch that never turns it on sees no change at all.
- A mixed store (shop + laundry) runs both interfaces, with one till and one customer record, and staff can switch between the two without logging out.
- A laundry-only store can turn the POS interface off, so staff only see the laundry interface and shared pages.
- Attendants can register a laundry order for a customer, track it through its stages, take deposit and balance payments, print a ticket, and hand the order over at pickup.
- The laundry interface has its own layout, pages and navigation, kept separate from the POS code.
- Branch Admin gets laundry reports, a service price list, and laundry settings.

---

## 2. Core Rules

1. **Business Sector is untouched.** Do not add a sector value, do not change the dropdown, and do not change any existing sector check. Laundry is controlled only by the interface settings in §4. Because no new sector value exists, no existing code can start treating laundry as retail by mistake.
2. **Separate interface.** Laundry pages, layout, components and composables live in their own folders. **Do not edit or import POS page components.** The only allowed POS-side change is **one extra "Laundry" entry in the existing POS sidebar menu** (`PosLayout.vue` → `allNavLinks`, see §5.3). **Nothing is added to the POS selling screen** (`Pos/Index`) or its header, which is already full. Generic shared UI pieces (buttons, modals, inputs, tables) may be reused.
3. **Server-side checks.** Interface availability is decided on the server at the entry point **before any page renders**, so a user never sees a flash of the wrong interface. Hiding menu items alone is not enough.
4. **Default is unchanged behavior.** Defaults are Laundry **Off**, POS **On**. With those defaults, every code path must behave exactly as today.
5. **Reuse existing data.** Customers, branches, users, roles and permissions, payment methods, till sessions, tax settings, printer settings, notifications and the reporting UI patterns already exist. Reuse them. Do not rebuild them.
6. **New migrations only**, never edit old ones. Laundry data is branch-scoped, like everything else.

---

## 3. Step 0 — Discovery First (No Changes)

Report findings before building anything:
1. **Branch settings mechanism:** how per-branch settings are stored and read today (for example `branch->getSettings()`), and how Super Admin-only vs Branch Admin-editable settings are separated on the branch settings screen. The three new settings in §4 must use this mechanism.
2. **Business Sector (read-only check):** confirm where it is stored and read, so the agent can confirm none of it needs to change. **Do not modify it.**
3. **Entry points:** the post-login redirect, the POS entry route (`pos.index`), and the homepage "Open POS" link.
4. **Retail-only routes:** list every POS selling and retail-only route (POS selling page, items, stock, barcodes, discounts, batches, held sales and similar). This list feeds the POS guard in §5.4.
5. **POS navigation:** the POS page has its own sidebar/header. Which shared pages (Customers, Users, Expenses, Till, Reports, Settings, Notifications) are reachable from it, and which layout does each shared page render with? Confirm how `PosLayout.vue` builds the sidebar from the `allNavLinks` array (`perm`, `feature`, `condition` filters) and how `pos_permissions` / `pos_settings` are shared to the page, since the Laundry menu entry in §5.3 relies on them.
6. **Payments and till (critical):** how are sales and payments stored today, and how does the till session's Expected Cash calculation find cash sales (see §9)? Which payment methods exist?
7. **Held and offline sales:** how to detect held sales and (as far as the server can know) pending offline sales for a branch, for the warning shown when POS is turned off (§4.3).
8. **Customers:** the existing customer model, its fields (phone, address, email, debt balance), how customer search works in the POS, and how the debt ledger posts a balance.
9. **Roles and permissions:** how permissions are seeded and assigned to default roles, whether roles are per branch, and the existing POS access permission (for example `canAccessPos`).
10. **Receipts and printing:** how receipt printing and the 80mm/A4 printer settings are invoked, so laundry tickets use the same direct-print path.
11. Where **branch tax settings** are read.

If the till or payment model makes §9 impossible without large changes to existing code, **stop and report** before building.

---

## 4. Interface Settings (Replaces the Business Sector Change)

### 4.1 The Settings
Three per-branch settings, stored through the existing branch settings mechanism:

| Setting | Values | Default | Who can change it |
|---|---|---|---|
| **Laundry module** | On / Off | **Off** | Platform Super Admin only |
| **POS interface** | On / Off | **On** | Turning **off**: Platform Super Admin only. Turning back on: Platform Super Admin. |
| **Default interface after login** | POS / Laundry | POS | Branch Admin (and Platform Super Admin) |

- **At least one interface must stay on.** The server rejects any change that would leave both POS and Laundry off.
- **Default interface** only matters when both are on. It is shown (and editable) only when both are on. If only one interface is on, that interface is always the landing interface regardless of this value.
- Laundry on/off and POS off are Super Admin only because laundry is a quoted add-on and the change is structural. The default interface is a harmless preference, so Branch Admin can set it.
- Define the settings keys and helpers in **one place** (for example `branch->laundryEnabled()`, `branch->posEnabled()`, `branch->defaultInterface()`), and use them everywhere. Do not scatter raw setting lookups.
- Build the Laundry toggle availability through one function, so a plan capability check (for example `feature_laundry`) can be added later without changes elsewhere. No plan gating now.
- Every change to these settings is written to the existing activity/audit log if one exists (who, when, old value, new value).

### 4.2 Branch Modes

| Mode | Laundry | POS | Behavior |
|---|---|---|---|
| **POS only** (every branch today) | Off | On | Nothing changes. |
| **Mixed store** | On | On | Login lands on the default interface. A "Laundry" entry in the POS sidebar and a "POS" entry in the laundry sidebar move between them without logging out. |
| **Laundry only** | On | Off | POS and retail-only pages are hidden and blocked on the server. Shared pages stay available. |

### 4.3 Safety Rules
- **Turning POS off:** blocked while any till session is open at the branch. The confirmation warns about held sales and possible unsynced offline sales on devices (§3 item 7), and that all users of the branch lose the POS on their next page load.
- **Turning Laundry off:** blocked while the branch has any **open laundry order** (status Received, In Progress or Ready). This prevents customers' clothes records from disappearing from view. The message lists how many open orders exist.
- **Nothing is deleted.** Turning either interface off only hides and blocks it. Turning it back on restores everything (POS data, laundry orders, services, payments, settings).
- **At least one stays on** (§4.1), enforced on the server, not only in the UI.

---

## 5. Interface Routing

### 5.1 Landing After Login
Resolved on the server for the logged-in user:
1. Build the list of interfaces the user can use: **POS** if the branch POS interface is on **and** the user has the POS access permission; **Laundry** if the branch Laundry module is on **and** the user has the laundry access permission.
2. If the branch default interface is in that list, land there.
3. Otherwise land on the other interface in the list.
4. If the list is empty, land on the first shared page the user can access (for example Customers or Till), not on an error page.

The **POS entry route** (`pos.index`) and the **homepage "Open POS" link** resolve the same way: if the POS is off for the branch (or the user lacks POS access), they redirect to the resolved landing page instead.

### 5.2 Laundry Routes
- Laundry routes live under the branch, for example `/{branch}/laundry/...`, using the **existing branch middleware stack** (authentication, branch resolution, disabled-branch handling).
- Add a guard so they are available **only when the branch Laundry module is on**. Otherwise redirect to the POS (or the resolved landing page).
- Each laundry action also checks the matching `laundry.*` permission (§8).

### 5.3 Switching Between Interfaces (Mixed Store)
The POS selling screen is already full, so **no button is added to the POS screen or its header.** Switching uses the sidebar menus that both interfaces already have.

- **From POS to Laundry:** add **one entry** to the existing `allNavLinks` array in `resources/js/Layouts/PosLayout.vue`, placed **directly under the "POS" entry**. It reuses the array's existing filters, so no template change is needed:
  ```js
  {
      label: 'Laundry',
      route: 'laundry.home',
      match: 'Laundry/',
      icon:  '...', // a simple laundry/shirt icon path
      perm:  'laundry.access',
      condition: () => Boolean(page.props.pos_settings?.laundry_enabled),
  },
  ```
  - It appears like any other menu item: full label when the sidebar is expanded, icon with hover tooltip when collapsed, and inside the slide-out menu on mobile (opened from the existing menu toggle).
  - The `condition` hides it unless the branch Laundry module is on, so every existing branch sees no change. The `perm` hides it from users without `laundry.access`. `laundry_enabled` must be added to the shared `pos_settings` prop and `laundry.access` to the shared `pos_permissions` prop.
- **From Laundry to POS:** the laundry sidebar (§7.1) has a **"POS"** entry at the top of its menu, shown only when the branch POS interface is on and the user has POS access.
- Both are plain links to the other interface's home. No logout, no session change.
- Someone with only laundry permissions never sees the "POS" entry, and someone with only POS access never sees the "Laundry" entry (§5.1 step 1).
- In laundry-only mode, the POS sidebar is never shown (the POS guard in §5.4 redirects), so the entry is not needed there.

### 5.4 POS / Retail-Only Guard (Keys on the POS Toggle)
- When the branch **POS interface is off**, the POS selling page and retail-only routes (items, stock, barcodes, discounts, batches, held sales and similar, identified in Step 0) must **redirect to the laundry home on the server side**, and their menu links are hidden.
- The guard keys **only on the POS toggle**, never on the Business Sector. With POS on (the default), it does nothing, so every existing branch is unaffected.
- Keep the list of guarded routes in **one place**.
- **Shared pages** stay available in every mode: Customers, Users, Expenses, Till, Notifications, branch settings and printer settings. In laundry-only mode they are reachable from the laundry navigation.
- **Offline note:** a device with the POS app cached may keep showing the POS shell while offline after POS is turned off. On reconnect, the server guard redirects it. This is acceptable.

---

## 6. Customers (Reuse Existing, Required for Orders)

- Use the **existing customers**. Do not create a second customer table. Orders reference `customer_id`.
- **One customer record across both interfaces.** In a mixed store, a customer's debt ledger is shared between POS and laundry.
- **A customer is required on every laundry order.**
- At intake, the attendant either **searches existing customers** (by name or phone, using the existing search behavior) or **adds a new customer** through a quick-add form: **name and phone required**; address and email optional. Apply the "phone required" rule **only inside the laundry flow**. Do not change the existing customer rules used by the POS.
- Warn when a new customer's phone number already exists in the branch and offer the existing customer instead.
- When a customer is selected, show a **customer details card**: name, phone, address, outstanding debt balance (from the existing debt ledger), and a short summary of their recent laundry orders.
- Store a **snapshot** of the customer's name and phone on the order, so tickets and history stay stable if the customer record is later edited.
- Phone numbers are personal data. Never expose them on any public page.

---

## 7. Laundry Interface

### 7.1 Layout and Navigation (Own Layout Component)
A dedicated laundry layout with its own sidebar and header (branch name, current user, till status, connectivity). **Responsive from the start** (phone, tablet and desktop), because attendants may use a phone or tablet. Navigation:
- **POS** (top of the menu; only when the branch POS interface is on and the user has POS access, per §5.3)
- **Home** (dashboard tiles: received today, in progress, ready for pickup, overdue, cash collected today)
- **New Order**
- **Orders** (board and list)
- **Customers** (existing customers page)
- **Services** (price list)
- **Reports**
- **Settings** (laundry settings)
- Shared admin links that already exist (Users, Expenses, Till, Notifications), shown according to permissions.

### 7.2 Services (Price List)
A simple per-branch catalogue of **service items**. Each has: name (for example "Shirt — Wash & Iron" or "Wash & Fold"), optional category, **pricing type** (**per piece** or **per kg**), price, and active/inactive. Search and category grouping make quick-pick fast at intake. Inactive items are hidden at intake but kept for history. Laundry services are separate from POS items and never appear in POS stock or item lists. (See Open Questions on a garment × service matrix.)

### 7.3 New Order (Intake)
One screen, in its own component:
1. **Customer** (required), per §6.
2. **Lines:** pick a service item, enter **quantity** (pieces) or **weight in kg** (decimal), and an optional **note** for the line (for example "stain on collar"). The price comes from the catalogue. Changing a line price requires a permission.
3. **Due date and time**, defaulting from the branch's default turnaround (a laundry setting).
4. **Order note** (optional).
5. **Totals:** subtotal, optional order-level discount (percentage or fixed, with a permission), **tax using the branch's existing tax setting**, total.
6. **Payment at intake (optional):** deposit of any amount from zero up to the total, using the existing payment methods. The balance due is shown.
7. **Create Order** saves the order, its lines and any deposit in **one database transaction**, then prints the **order ticket** (see §7.7).

Prices on an order are **snapshots**: later catalogue changes never alter existing orders.

### 7.4 Order Statuses
Fixed set: **Received → In Progress → Ready → Collected**, plus **Cancelled**. Every change is recorded (who, when, optional note). The Orders screen offers a **board view** (columns with counts) and a **list view** with search (order number, customer name or phone) and filters: status, due today, **overdue** (due time passed and not collected or cancelled), date range, and unpaid balance. Status changes are permission-based.

### 7.5 Payments
- Record payments on an order at any time (deposit now, balance later), with the existing payment methods. Several payments per order are allowed. Overpaying is blocked.
- Each payment records amount, method, who recorded it, when, and the **till session** it belongs to (§9).
- **Cancelling** an order that has payments requires recording **refund entries** (default: full refund, partial allowed with a reason). Refunds are stored as separate refund payments. Cancelling needs a permission, and an order cannot be cancelled after it is collected.

### 7.6 Pickup
"Mark Collected" records the time, the user, and optionally the name of the person who actually collected. By default the **balance must be zero** to release an order. Releasing an unpaid order is allowed only with a permission, and the balance is then posted to the customer's existing debt ledger if that is available (see Open Questions). Ready orders left uncollected beyond a threshold (a laundry setting, for example 14 days) are flagged **Unclaimed** on the board and in reports.

### 7.7 Ticket / Receipt
Uses the existing printer settings (80mm and A4) and direct printing. Contents: branch name, address and phone, order number, date, customer name and phone, due date, each line (service, quantity or kg, price, line total, note), subtotal, discount, tax, total, paid, balance, a "present this ticket at pickup" line, and a configurable footer. It can be **reprinted any time** and always shows the current paid and balance amounts.

### 7.8 "Order Ready" Message
When an order becomes **Ready**, show a button that opens a **WhatsApp click-to-chat link pre-filled** with a message (default text with the customer name, order number, balance and branch name; the template is editable in laundry settings). There is no provider cost and nothing is sent automatically. **Phone numbers must be normalized** to international format using a default country code setting (a local number starting with 0 breaks WhatsApp links unless the leading 0 is replaced with the country code).

### 7.9 Reports
Reusing the existing reporting patterns (date range and filters), permission-based:
- **Orders:** counts and value by period and by status.
- **Payments received:** by day and by payment method (including refunds).
- **Outstanding balances:** orders with money owing, by customer.
- **Overdue and Unclaimed** orders.
- **Top services** by quantity and revenue.

### 7.10 Laundry Settings (Branch Admin)
Default turnaround, unclaimed-after days, order number prefix, WhatsApp message template, default country code, receipt footer, allow price override, allow unpaid pickup. These appear **only when the branch Laundry module is on**. (The Laundry on/off toggle itself is Super Admin only, per §4.1.)

---

## 8. Permissions

Use the project's existing permission system. Add a `laundry.*` group: **access laundry interface**, create orders, update status, cancel orders, record payments, refund, release unpaid pickup, override price, manage services, view reports, manage settings.
- Seed them idempotently. **Branch Admin gets all of them. Cashier gets** access laundry interface, create orders, update status, record payments and mark collected.
- Custom roles do not receive them automatically.
- All checks are enforced on the **server**, not just by hiding buttons.
- **Per-user interface access** follows permissions (§5.1): the existing POS access permission controls the POS, `laundry.access` controls laundry. A user with only laundry permissions never sees the POS or the "POS" menu entry in the laundry sidebar, and their landing page falls back to whatever they can access.
- Granting these to existing default roles is harmless because laundry routes are only available when the branch Laundry module is on (§5.2), which is Off by default.

---

## 9. Payments, Till and Reports Integration (Critical)

Laundry money must flow through the **same cash accountability** as the POS, or till reconciliation and revenue reports will silently leave laundry out.
- **One till for a mixed store.** One cash drawer covers both interfaces. POS payments and laundry payments go into the **same till session**, so Expected Cash at close includes everything. Do not create a separate laundry till.
- **Required outcome:** cash received for laundry (deposits and balance payments) during a till session **must appear in that session's Expected Cash at close**, and cash refunds must reduce it. Laundry payments must appear in the existing payment-method reporting.
- **Require Till Opening:** if the branch has the "Require Till Opening" setting on (per the Opening Till README), the laundry interface must honor it. An attendant must open a till before taking orders or payments, using the **existing** till session logic (reuse it, do not rebuild it). A till opened from either interface is the same session. Payments are tagged with the till session.
- **How:** Step 0 decides between (a) laundry payments writing a matching record into the existing sales/payment structure the till already reads, or (b) extending the till's Expected Cash calculation to also read laundry payments. Pick the option that changes existing code **least**, and confirm it with the owner if it is not obvious.
- Revenue view: orders by **order date**, collections by **payment date**. Do not mix them.

---

## 10. Data Model (New Migrations, All Branch-Scoped)

- **`laundry_services`:** `id`, `branch_id`, `name`, `category` (nullable), `pricing_type` (per_piece / per_kg), `price`, `is_active`, `sort_order`, timestamps.
- **`laundry_orders`:** `id`, `branch_id`, `order_number` (unique per branch), `customer_id`, `customer_name`, `customer_phone` (snapshots), `status`, `received_at`, `due_at`, `collected_at`, `collected_by_user_id`, `collected_by_name` (nullable), `created_by`, `subtotal`, discount fields, `tax_amount`, `total`, `note`, timestamps.
- **`laundry_order_items`:** `id`, `order_id`, `service_id` (nullable), `service_name`, `pricing_type`, `unit_price` (snapshots), `quantity` (decimal, for kg), `line_total`, `note`.
- **`laundry_order_status_history`:** `order_id`, `from_status`, `to_status`, `changed_by`, `changed_at`, `note`.
- **`laundry_payments`:** `id`, `order_id`, `branch_id`, `type` (payment / refund), `amount`, `method`, `till_session_id` (nullable), `recorded_by`, `paid_at`, `reference` (nullable), `note`.
- **Interface settings** (`laundry_enabled`, `pos_enabled`, `default_interface`): reuse the existing branch settings mechanism. If a schema change is unavoidable, use a **new migration** with defaults Off / On / POS, so every existing branch keeps today's behavior. **No change to the Business Sector column.**
- **Laundry settings:** reuse the existing branch settings mechanism, or a small `laundry_settings` table if none exists.
- **Order numbers** (for example `LDY-000123`, with the configured prefix) must be generated **race-safe**: two attendants creating orders at the same moment must never get the same number.
- The **amount paid** is the sum of payments (single source of truth). Do not keep a separate running total that can drift, unless it is updated in the same transaction as every payment.
- Indexes at least on branch with status and due date, branch with customer, and branch with order number.

---

## 11. Code Organization

Follow the project's module pattern, kept fully separate from the POS code. For example:
- **Backend:** `app/Modules/Laundry/` (controllers, models, actions/services, policies, requests, routes, migrations).
- **Frontend:** `resources/js/Pages/Laundry/`, `resources/js/Layouts/LaundryLayout.vue`, `resources/js/Components/Laundry/`, laundry-specific composables.
- **The only edits to existing code:** the interface settings keys and helpers, the branch settings screen (the three toggles with their safety checks, and the laundry settings section), the post-login/entry landing resolver, the POS/retail-only route guard keyed on the POS toggle, the single "Laundry" entry in the `allNavLinks` array of `PosLayout.vue` (plus `laundry_enabled` in the shared `pos_settings` prop and `laundry.access` in `pos_permissions`), permission seeds, and any wiring needed by §9. Keep every one of these small and listed in the final report.

---

## 12. Offline

Laundry is **online-only in this version.** If the device is offline, the laundry interface shows a clear message and does not queue or fake anything. The existing offline sales, held sales and sync features are not extended to laundry.
- **Mixed store:** while offline, the laundry page offers a **"Go to POS"** button (when the POS is on and the user has POS access), so the POS and its offline selling keep working.
- **Laundry only:** the laundry page shows the offline message only.

---

## 13. What Must NOT Change

- The Business Sector dropdown, its values, and every existing sector check.
- POS Vue components and the retail and pharmacy flows (apart from the single "Laundry" sidebar menu entry in §5.3). The POS selling screen and its header get **no** new elements.
- Behavior of any branch with the default settings (Laundry Off, POS On).
- Existing customer rules used by the POS.
- Offline sync, the service worker, till session logic (reused, not altered, beyond what §9 strictly requires), tax and printer logic.
- Existing POS reports.

---

## 14. Out of Scope (Later)

Garment tags and scannable labels, photos and damage notes, express pricing, delivery and pickup scheduling, packages, subscriptions and loyalty, offline support for laundry, wash/dry/iron sub-stages, automatic SMS or WhatsApp sending, supply inventory, organization-wide laundry reports, AI chat integration, garment × service price matrix, CSV import of services, a public "track my order" page, central plant processing between branches, and differentiating Retail from Pharmacy.

---

## 15. Testing Checklist

- **Defaults / regression:** with Laundry Off and POS On, every role at a Retail and a Pharmacy branch lands on the POS exactly as before. No laundry menu, button or route is visible or reachable.
- **Business Sector:** the dropdown and its values are unchanged, and no sector-dependent behavior changed.
- **Mixed store:** login lands on the default interface; changing the default changes the landing page; the "Laundry" entry in the POS sidebar (expanded, collapsed and mobile) and the "POS" entry in the laundry sidebar work both ways without logout; the POS selling screen layout is unchanged; no flash of the wrong interface.
- **Laundry only:** POS and retail-only routes redirect to the laundry home on the server; their menus are hidden; shared pages (Customers, Users, Expenses, Till, Notifications, settings) remain reachable.
- **Per user:** a user with only laundry permissions never sees the POS or the "POS" menu entry; a user with only POS access never sees laundry; a user with neither lands on a shared page they can access.
- **Settings rules:** both interfaces cannot be turned off together; POS off is blocked with an open till session and shows the held/offline sales warning; Laundry off is blocked while open orders exist; turning either back on restores all data; Branch Admin can change only the default interface; Laundry on/off and POS off are rejected for non-Super Admins on the server.
- **Orders:** create an order for an existing and a new customer. Phone is required. A duplicate phone triggers the warning. Kg quantities accept decimals. Totals, discount and tax are correct. A later price list change does not alter old orders.
- **Payments:** deposit then balance, multiple payments, overpayment blocked, cancel with refund, and pickup blocked while a balance remains (and allowed with the permission, posting to the debt ledger if available).
- **Till:** in a mixed store, POS and laundry cash land in the **same** till session and both appear in Expected Cash at close; cash refunds reduce it. "Require Till Opening" is honored in the laundry interface, and a till opened in one interface is the same session in the other. Payments appear in payment-method reports.
- **Shared customer:** a customer's debt ledger shows balances from both POS and laundry.
- **Statuses:** history is recorded, overdue and unclaimed flags are correct.
- **WhatsApp link:** local numbers starting with 0 become international format.
- **Concurrency:** two attendants creating orders at once never share an order number.
- **Printing:** the ticket prints through the existing printer settings on 80mm and A4, and reprints show current paid and balance.
- **Permissions:** Branch Admin and Cashier get the right access, and server-side checks block everything else.
- **Isolation:** one branch never sees another branch's orders, services or payments.
- **Offline:** the laundry interface shows an offline message and does nothing unsafe; in a mixed store the "Go to POS" button works and offline POS selling continues.
- **Responsive:** usable on phone and tablet widths.

---

## 16. Decisions Made (Change If You Disagree)

- Laundry is a **module toggled by branch settings**, not a Business Sector. The sector dropdown is unchanged.
- Three settings: Laundry module (default Off), POS interface (default On), default interface (POS / Laundry). At least one interface stays on.
- Laundry on/off and POS off are **Platform Super Admin only**; the default interface can be set by Branch Admin.
- The retail-only guard keys on the **POS toggle**, not the sector.
- A mixed store shares **one till** and **one customer record / debt ledger** across both interfaces.
- Interface access per user follows permissions (POS access permission and `laundry.access`).
- Switching uses the **sidebar menus**, not a new button: one "Laundry" entry under "POS" in the POS sidebar, and a "POS" entry in the laundry sidebar. Nothing is added to the POS selling screen.
- Fixed statuses (Received, In Progress, Ready, Collected, Cancelled).
- Branch tax setting applies to laundry orders.
- Laundry honors the "Require Till Opening" rule.
- Laundry is online-only in this version; a mixed store offers "Go to POS" while offline.
- Customer name and phone are required for laundry orders.

---

## 17. Open Questions

1. **Price list model:** a flat list of service items (recommended for now), or a garment-type × service price matrix?
2. **Unpaid pickup:** allow with a permission and post the balance to the customer's debt ledger (recommended, if the ledger can take it), or always require full payment?
3. **Discounts:** a simple order-level discount with a permission (as written), or none in this version?
4. **Supplies:** does a laundry shop need item/stock tracking for detergents and supplies now? Recommended no for now: track supply costs through Expenses.
5. **Turning POS back on:** Platform Super Admin only (as written), or may Branch Admin re-enable it since it restores existing behavior?
