# 🧠 AI Agent Prompt — Web-Based Point of Sale (POS) System
## Project: SkyNet POS (Laravel + Vue + Inertia.js)

---

## 0. AGENT INSTRUCTIONS — READ FIRST

You are an expert full-stack developer. Your task is to build a **complete, production-grade web-based Point of Sale (POS) system** using the tech stack and workflow described below. Follow every section precisely. Do not skip sections. Do not add features not listed. Do not remove features that are listed.

When starting from `skynet-ecommerce` (the base starter project), clone it first and scaffold on top of it. If it is not available, scaffold from a clean Laravel + Vue + Inertia.js setup.

---

## 1. TECH STACK

| Layer        | Technology                                      |
|--------------|-------------------------------------------------|
| Backend      | Laravel 12 (PHP 8.2+)                          |
| Frontend     | Vue 3 (Composition API + `<script setup>`)      |
| Bridge       | Inertia.js v2                                   |
| Styling      | Tailwind CSS v3                                 |
| UI Components| shadcn-vue (preferred) or custom Tailwind components |
| Database     | MySQL 8+ / MariaDB 10.4+                        |
| Auth         | Laravel Sanctum (session-based with Inertia)    |
| State Mgmt   | Pinia                                           |
| HTTP Client  | Axios (via Inertia or standalone for async ops) |
| Icons        | Lucide Vue                                      |
| Notifications| Vue Toastification or custom Toast component    |
| Print        | Browser `window.print()` with print-specific CSS|
| Barcode      | `@ericblade/quagga2` or HTML5 camera API        |

---

## 2. PROJECT BOOTSTRAP (skynet-ecommerce)

```bash
# If skynet-ecommerce is available:
git clone https://github.com/olamide3412/skynet-ecommerce.git skynet-pos
cd skynet-pos
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate:fresh --seed
npm run dev

# If NOT available, scaffold fresh:
composer create-project laravel/laravel skynet-pos
cd skynet-pos
composer require inertiajs/inertia-laravel tightenco/ziggy
npm create vue@latest resources/js -- --typescript=no
npm install @inertiajs/vue3 pinia tailwindcss @tailwindcss/forms lucide-vue-next
```

---

## 3. DATABASE SCHEMA

> Generate Laravel migrations for ALL tables below in the exact order listed to respect foreign key constraints.

### 3.1 Core Lookup Tables

```sql
-- categories
id, category_name (unique), created_at, updated_at

-- group_addresses  (item supplier/group tags)
id, name (unique), created_at, updated_at

-- vendors
id, vendor_name, contact_person, phone_number, email,
address (text), country, created_at, updated_at
```

### 3.2 Items & Inventory

```sql
-- items
id, category_id (FK→categories), group_address_id (FK→group_addresses nullable),
item_name, barcode_number (unique), qty (int default 0),
buy_price (decimal 10,2), price (decimal 10,2),
wholesale_price (decimal 10,2), expiry_date (date nullable),
item_description (varchar 255 nullable), price_locked (bool default false),
created_at, updated_at

-- item_menu_grids  (POS quick-access grid config per menu slot)
id, menu_name (unique), menu_text, fore_color, back_color,
font (varchar 255), created_at, updated_at

-- item_grids  (assigns item to a grid slot)
id, item_id (FK→items), menu_name, menu_index (int),
fore_color, back_color, font, created_at, updated_at

-- inventory_transactions
id, item_id (FK→items), transaction_type (enum: sale,purchase,return,adjustment),
qty (int), previous_qty (int), new_qty (int),
reference_id (varchar 50 nullable), notes (text nullable),
user_id (FK→users), created_at, updated_at

-- inventory_adjustments
id, item_id (FK→items), adjustment_type (varchar 50),
quantity (int), reason (varchar 255), adjusted_by (varchar 100),
adjustment_date (timestamp default now)
```

### 3.3 Customers

```sql
-- customers  (renamed from patients_bio, patient references replaced with customer)
id, name, phone, address (default 'NA'), gender,
dob (date nullable), note (mediumtext default 'No Note'),
debt_bal (decimal 10,2 default 0), contact_name (default 'NA'),
contact_phone (default 'NA'), contact_address (default 'NA'),
watch_list (bool default false), created_at, updated_at
FULLTEXT INDEX on phone
```

### 3.4 Sales & Cart

```sql
-- sales
id, customer_id (FK→customers nullable), receipt_id (varchar 50 unique),
items_order_count (int), consultation_fee (decimal 10,2 default 0),
payment_method (varchar 100), bank_transfer (decimal 10,2 default 0),
cash (decimal 10,2 default 0), amount_cost (decimal 10,2),
amount_paid (decimal 10,2), change_bal (decimal 10,2),
purchase_type (enum: Wholesale,Consumer default Consumer),
profit_made (decimal 10,2 default 0),
sale_discount_id (FK→sale_discounts nullable),
discount_amount (decimal 10,2 default 0),
final_total (decimal 10,2), is_debt (bool default false),
user_id (FK→users), created_at, updated_at

-- sale_orders  (line items for a completed sale)
id, sale_id (FK→sales cascade delete), item_id (FK→items nullable),
item_name (varchar 255), selling_price (decimal 10,2),
total_selling_price (decimal 10,2), qty (int),
purchase_type (enum: Wholesale,Consumer default Consumer),
user_id (FK→users), sort_date (datetime default now)

-- sale_discounts
id, discount_type (enum: percentage,fixed), discount_value (decimal 10,2),
start_date_time (datetime), end_date_time (datetime),
applies_to (varchar 50), description (varchar 255),
is_apply (bool default false), user_id (FK→users),
created_at, updated_at

-- sale_return_items
id, sale_id (FK→sales cascade delete nullable), item_id (FK→items),
item_name, qty, price (decimal 10,2), total_price (decimal 10,2),
purchase_type (enum: Wholesale,Consumer default Consumer),
return_reason (varchar 255 nullable), refund_amount (decimal 10,2 nullable),
user_id (FK→users), created_at, updated_at

-- held_sales  (saved / on-hold carts)
id, user_id (FK→users), hold_name (varchar 255 nullable),
status (enum: In-Progress,Held,Cancelled,Completed default Held),
customer_id (FK→customers nullable), created_at, updated_at

-- held_sale_items
id, held_sale_id (FK→held_sales cascade delete),
item_id (FK→items), qty (int), price (decimal 10,2),
item_name (varchar 255 nullable),
purchase_type (enum: Wholesale,Consumer default Consumer),
created_at, updated_at
```

### 3.5 Purchasing & Receiving

```sql
-- purchase_orders
id, vendor_id (FK→vendors nullable), order_date (date),
total_amount (decimal 10,2), status (enum: pending,completed default pending),
user_id (FK→users), created_at, updated_at

-- purchase_order_items
id, purchase_order_id (FK→purchase_orders cascade delete),
item_id (FK→items), quantity (int), price_per_unit (decimal 10,2),
total_price (decimal 10,2), received_qty (int default 0)

-- received_items
id, purchase_order_id (FK→purchase_orders cascade delete),
item_id (FK→items cascade delete), qty_received (int),
cost_per_qty (decimal 10,2), received_date (date),
expiry_date (date nullable), created_at, updated_at

-- vendor_payments
id, vendor_id (FK→vendors), purchase_order_id (FK→purchase_orders),
payment_date (date), payment_amount (decimal 10,2),
payment_method (varchar 50)
```

### 3.6 Debts

```sql
-- debt_payments
id, customer_id (FK→customers cascade delete),
user_id (FK→users), amount (decimal 10,2),
type (enum: debit,credit), narration (varchar 255 default 'NO_NARRATION'),
created_at
```

### 3.7 Users, Roles & Settings

```sql
-- staffs
id, staff_number (varchar 15 unique), firstname, surname,
marital_status, gender, dob (date), phone_number,
state_of_origin, lga, present_qualification,
next_of_kin, phone_of_next_kin, address_of_next_kin,
residential_address, department, staff_position,
monthly_salary (decimal 10,2), bank_account, bank_name,
date_of_appointment (date), photo_path (varchar 100),
status (enum: ACTIVE,RESIGNED,TERMINATED,INACTIVE default ACTIVE),
created_at, updated_at

-- users
id, full_name (varchar 50), username (varchar 30 unique),
password (varchar 255), is_active (bool default true),
is_admin (bool default false), acct_tier (int default 0),
staff_id (FK→staffs unique nullable),
created_at, updated_at

-- users_roles
id, role_name (varchar 100), role (varchar 100), user_id (FK→users cascade delete),
created_at, updated_at

-- logs
id, log (text), user_id (FK→users nullable), created_at

-- settings  (single-row config)
id, is_price_editable (bool default false),
is_qty_deduction (bool default true), out_of_stock (int default 25),
is_check_expiration (bool default true), is_show_buy_price (bool default false),
business_name (varchar 50), business_address (varchar 100),
business_contact_number (varchar 50), business_email (varchar 50),
item_icon_preview (bool default false),
wholesale_profit_percent (decimal 10,2 default 10.00),
consumer_profit_percent (decimal 10,2 default 15.00),
sell_interface (enum: classic,gallery default classic),
business_sector (enum: health,commerce default commerce),
updated_at

-- most_sale_items  (top-selling tracker per user per day)
id, user_id (FK→users), item_id (FK→items),
qty (int default 0), date_created_at (date default now), updated_at
```

---

## 4. ELOQUENT MODELS

Generate a model for every table. Key relationships to define:

- `Item` → `belongsTo` Category, GroupAddress; `hasMany` SaleOrder, InventoryTransaction, ItemGrid
- `Sale` → `belongsTo` User, Customer, SaleDiscount; `hasMany` SaleOrder, SaleReturnItem
- `SaleOrder` → `belongsTo` Sale, Item
- `HeldSale` → `belongsTo` User, Customer; `hasMany` HeldSaleItem
- `Customer` → `hasMany` Sale, DebtPayment, HeldSale
- `User` → `hasMany` Sale, Log, UsersRole; `belongsTo` Staff
- `PurchaseOrder` → `belongsTo` Vendor, User; `hasMany` PurchaseOrderItem, ReceivedItem
- `Settings` → single-row model with static `current()` helper

All models must use `$fillable` (not `$guarded`). Use `$casts` for booleans, decimals, enums, and dates.

---

## 5. LARAVEL BACKEND

### 5.1 Authentication
- Use Laravel Sanctum with session-based auth (Inertia pattern)
- Routes: `POST /login`, `POST /logout`, `GET /` (redirect based on auth)
- Middleware: `auth` on all POS routes

### 5.2 Policies & Role Helpers

Create a `RoleService` with methods:
```php
RoleService::hasRole(string $role): bool
RoleService::getTier(): int
RoleService::canEditPrice(): bool   // is_price_editable setting + tier check
RoleService::canDeleteSale(): bool  // tier >= 3
RoleService::canApplyDiscount(): bool
RoleService::canViewBuyPrice(): bool
```

Roles stored in `users_roles` table. Check against authenticated user.

### 5.3 Resource Controllers (all return Inertia responses except API routes)

**POS**
- `PosController` — `index()` renders POS page with initial data
- `SaleController` — `store()`, `show()`, `index()`, `destroy()` (tier 3+)
- `HeldSaleController` — `store()`, `index()`, `show()`, `update()`, `destroy()`
- `SaleReturnController` — `store()` (process return + restock)

**Items**
- `ItemController` — full CRUD + `search()` for POS autocomplete
- `CategoryController` — full CRUD
- `ItemGridController` — `index()`, `update()` (manage grid layout)

**Customers**
- `CustomerController` — full CRUD + `search()` for POS lookup

**Inventory**
- `InventoryController` — `index()` (transaction log), `adjust()` (manual adjustment)
- `PurchaseOrderController` — full CRUD + `receive()` (mark items received)

**Reports**
- `ReportController` — `dailySales()`, `salesSummary()`, `itemSales()`, `profitReport()`, `stockReport()`, `expiryReport()`

**Settings**
- `SettingsController` — `index()`, `update()`

**Users & Roles**
- `UserController` — full CRUD
- `RoleController` — `update()` per user

**Discounts**
- `SaleDiscountController` — full CRUD

### 5.4 Key Business Logic (Services)

**`SaleService::process(array $data, User $user): Sale`**
```
1. Validate cart is not empty
2. Check item quantities if is_qty_deduction enabled
3. Check item expiry if is_check_expiration enabled
4. Check price_locked on items when price is overridden (require canEditPrice)
5. Calculate totals: amount_cost, profit_made, final_total
6. DB transaction:
   a. Insert sales record
   b. Insert sale_orders (line items)
   c. Deduct qty from items + insert inventory_transactions (type: sale)
   d. Update most_sale_items for the day
   e. If is_debt: insert debt_payment (debit) + update customer.debt_bal
   f. If customer linked: optional log entry
7. Return Sale with receipt_id
```

**`SaleService::generateReceiptId(): string`**
- Format: `RC` + date(`Ymd`) + 4-digit padded sequence (e.g., `RC202504050001`)

**`InventoryService::restock(int $itemId, int $qty, string $ref, User $user): void`**
- Insert inventory_transaction (type: purchase)
- Increment items.qty

**`ReturnService::process(int $saleId, array $items, string $reason, User $user): void`**
```
1. Verify sale exists
2. For each return item: validate qty ≤ original ordered qty
3. DB transaction:
   a. Insert sale_return_items
   b. Re-add qty to items + insert inventory_transaction (type: return)
   c. Log action
```

### 5.5 API Routes (JSON, for async Vue calls)

```
GET    /api/items/search?q=&purchase_type=  → paginated item search (POS)
GET    /api/customers/search?q=             → customer search
GET    /api/held-sales                      → list held carts
POST   /api/held-sales                      → save cart
DELETE /api/held-sales/{id}                 → discard
GET    /api/settings                        → current settings (for POS init)
GET    /api/reports/daily?date=             → daily summary JSON
```

### 5.6 Validation Rules (Form Requests)

- `StoreSaleRequest`: items array required, each item needs id, qty (min 1), price (numeric min 0); payment_method required
- `StoreItemRequest`: item_name, barcode_number (unique), price, buy_price, qty (min 0) required
- `StoreCustomerRequest`: name, phone required; phone unique
- `StorePurchaseOrderRequest`: vendor_id, items array with item_id, quantity, price_per_unit

---

## 6. FRONTEND — VUE + INERTIA PAGES

### 6.1 Layout Structure

```
resources/js/
├── app.js                    # Inertia bootstrap
├── Pages/
│   ├── Auth/
│   │   └── Login.vue
│   ├── Pos/
│   │   └── Index.vue         # Main POS screen
│   ├── Sales/
│   │   ├── Index.vue         # Sales history list
│   │   └── Show.vue          # Sale detail / receipt view
│   ├── Items/
│   │   ├── Index.vue         # Item list + search
│   │   ├── Create.vue
│   │   └── Edit.vue
│   ├── Categories/
│   │   └── Index.vue
│   ├── Customers/
│   │   ├── Index.vue
│   │   └── Show.vue          # Customer profile + purchase history + debt
│   ├── Inventory/
│   │   ├── Index.vue         # Transaction log
│   │   └── Adjust.vue        # Manual adjustment
│   ├── Purchases/
│   │   ├── Index.vue
│   │   ├── Create.vue
│   │   └── Receive.vue
│   ├── Reports/
│   │   ├── DailySales.vue
│   │   ├── ItemSales.vue
│   │   ├── Profit.vue
│   │   ├── Stock.vue
│   │   └── Expiry.vue
│   ├── Discounts/
│   │   └── Index.vue
│   ├── Users/
│   │   └── Index.vue
│   └── Settings/
│       └── Index.vue
├── Components/
│   ├── POS/
│   │   ├── ProductSearch.vue
│   │   ├── ProductGrid.vue      # Gallery mode grid
│   │   ├── Cart.vue
│   │   ├── CartItem.vue
│   │   ├── PaymentModal.vue
│   │   ├── ReceiptModal.vue
│   │   ├── HoldCartModal.vue
│   │   ├── HeldCartsList.vue
│   │   ├── CustomerSelector.vue
│   │   └── ReturnModal.vue
│   ├── UI/
│   │   ├── AppLayout.vue        # Sidebar + topbar shell
│   │   ├── DataTable.vue        # Server-side paging table
│   │   ├── Modal.vue
│   │   ├── ConfirmDialog.vue
│   │   ├── Toast.vue
│   │   ├── RoleGate.vue         # v-if based on role/tier
│   │   ├── Badge.vue
│   │   ├── StatCard.vue
│   │   └── PrintWrapper.vue     # Slot wrapper for print CSS
│   └── Forms/
│       ├── ItemForm.vue
│       ├── CustomerForm.vue
│       └── PurchaseOrderForm.vue
├── Stores/
│   ├── cart.js                  # Pinia: current cart state
│   ├── settings.js              # Pinia: global settings
│   └── auth.js                  # Pinia: current user + roles
└── Composables/
    ├── useCart.js
    ├── useRoles.js
    ├── useCurrency.js           # format numbers as ₦ or configured currency
    └── usePrint.js
```

---

## 7. POS SCREEN — DETAILED SPEC (`Pages/Pos/Index.vue`)

This is the most critical screen. Build it with precision.

### 7.1 Layout (Two-panel)

```
┌────────────────────────────────────────────────────────────────┐
│  TOPBAR: Business Name | Cashier: {name} | Date/Time | [Menu]  │
├──────────────────────────┬─────────────────────────────────────┤
│  LEFT PANEL (60%)        │  RIGHT PANEL (40%)                  │
│  ┌─────────────────────┐ │  ┌───────────────────────────────┐  │
│  │ Search / Scan Bar   │ │  │ Customer (optional selector)  │  │
│  └─────────────────────┘ │  ├───────────────────────────────┤  │
│  ┌─────────────────────┐ │  │ Cart Items List               │  │
│  │ Item Grid (gallery) │ │  │ (scrollable)                  │  │
│  │ OR search results   │ │  ├───────────────────────────────┤  │
│  │ (classic mode)      │ │  │ Subtotal / Discount / Total   │  │
│  └─────────────────────┘ │  ├───────────────────────────────┤  │
│                          │  │ [Hold] [Return] [Pay Now]     │  │
└──────────────────────────┴─────────────────────────────────────┘
```

### 7.2 Item Search & Grid

- Real-time search by `item_name` or `barcode_number` (debounced 300ms, min 1 char)
- Barcode scan: listen for rapid keystrokes ending in Enter (hardware scanner pattern) on the search input
- Gallery mode: render items from `item_grids` as clickable color-coded tiles with `back_color`/`fore_color` applied
- Classic mode: searchable list with item name, price, qty badge
- Show OUT OF STOCK badge when `qty <= settings.out_of_stock`
- Show EXPIRY WARNING (yellow) or EXPIRED (red) based on `expiry_date` and `is_check_expiration`
- Toggle between Classic/Gallery via settings `sell_interface`

### 7.3 Cart Logic (Pinia store)

```js
// cart.js store state
{
  items: [],          // { item_id, item_name, qty, unit_price, purchase_type, line_total }
  customer: null,
  purchase_type: 'Consumer',   // global toggle Consumer / Wholesale
  discount: { type: null, value: 0, applied_id: null },
  consultation_fee: 0,
  payment: { method: 'Cash', cash: 0, bank_transfer: 0, amount_paid: 0 }
}
```

Cart actions:
- `addItem(item)` — add or increment qty; use `wholesale_price` or `price` based on `purchase_type`
- `removeItem(itemId)` — remove line
- `updateQty(itemId, qty)` — clamp to available stock
- `updatePrice(itemId, price)` — only if `canEditPrice`, price not locked
- `setPurchaseType(type)` — recalculate all line prices
- `applyDiscount(discount)` — apply active `SaleDiscount` or manual value (role check)
- `clearCart()` — reset all
- `calculateTotals()` — `amount_cost`, `profit_made`, `final_total`

### 7.4 Payment Modal

Tabs: **Cash** | **Bank Transfer** | **Split** | **Debt**

- Cash: enter amount paid → compute change_bal
- Bank Transfer: enter bank amount
- Split: cash + bank transfer fields, must sum >= final_total
- Debt: links sale to customer, flags `is_debt = true` (requires customer selected)
- Validate: amount_paid >= final_total unless Debt
- On confirm: POST `/sales` with full cart payload
- On success: show ReceiptModal, clear cart

### 7.5 Receipt Modal / Print

Display:
- Business name, address, contact
- Receipt ID, date/time, cashier name
- Line items table: name, qty, unit price, total
- Subtotal, discount, consultation fee, grand total
- Payment method & amount tendered, change
- Customer name (if linked)
- "Thank you" footer

Print behavior:
- `window.print()` with `@media print` CSS that hides everything except `.receipt-print-area`
- Thermal receipt size: 80mm width simulation via CSS (max-width: 300px)

### 7.6 Hold Cart

- Save current cart to `held_sales` + `held_sale_items` via POST `/api/held-sales`
- Name the hold optionally
- Held carts list: modal showing all held carts for current user
- Resume: load held cart back into Pinia store, delete the held record

### 7.7 Return/Refund Modal

- Enter receipt ID or search sale → load sale_orders
- Select items to return, enter qty (≤ original), enter reason
- POST `/sale-returns` → restock inventory, record return, log
- Show confirmation with refund amount

---

## 8. REPORTS — SPEC

| Report             | Route                          | Data                                           |
|--------------------|--------------------------------|------------------------------------------------|
| Daily Sales        | `/reports/daily?date=`         | Total sales count, total revenue, payment breakdown, top items |
| Sales History      | `/sales?from=&to=&user=`       | Paginated sales with filters                   |
| Item Sales         | `/reports/items?from=&to=`     | Items sold, qty, revenue, profit per item      |
| Profit Report      | `/reports/profit?from=&to=`    | Revenue, cost, gross profit, margin %          |
| Stock Report       | `/reports/stock`               | All items with current qty, reorder alerts     |
| Expiry Report      | `/reports/expiry`              | Items expiring within 30/60/90 days            |
| Customer Debt      | `/reports/debts`               | Customers with outstanding debt balances       |
| Cashier Report     | `/reports/cashier?user=&date=` | Per-user daily sales summary                   |

All reports: filterable date range, printable, exportable to CSV (backend generates).

---

## 9. SETTINGS PAGE (`/settings`)

Editable fields:
- Business name, address, contact number, email
- `sell_interface`: Classic / Gallery (radio toggle)
- `is_price_editable`: bool
- `is_qty_deduction`: bool
- `out_of_stock` threshold (int)
- `is_check_expiration`: bool
- `is_show_buy_price`: bool
- `wholesale_profit_percent`, `consumer_profit_percent`
- `business_sector`: health / commerce

Save as `PUT /settings`. Settings are loaded globally in Pinia on app boot.

---

## 10. USER & ROLE MANAGEMENT (`/users`)

User list with:
- Create user (link to staff record optional)
- Edit user (name, username, tier, active status, admin flag)
- Assign roles: multi-select from defined roles list
- Reset password

**Defined Roles (hard-coded list):**
```
PosAccess, PriceEdit, DiscountApply, SaleDelete,
SaleReturn, StockAdjust, ReportView, PurchaseManage,
CustomerManage, UserManage, SettingsEdit
```

Tier levels: 0 (Cashier), 1 (Supervisor), 2 (Manager), 3 (Admin)

---

## 11. CUSTOMER MANAGEMENT (`/customers`)

- List with search, debt balance column, watch_list flag
- Create / Edit customer form
- Customer profile page: purchase history (sales linked to customer), debt history, debt_bal display
- Debt payments: record payment (credit) or charge (debit) against customer
- Watch list toggle (flag for suspicious customers)

---

## 12. INVENTORY MANAGEMENT

### Purchase Orders (`/purchases`)
- Create PO: select vendor, add items with qty and price
- View PO: list items, received qty progress
- Receive items: enter qty received per item, expiry date → updates `items.qty` via InventoryService

### Inventory Log (`/inventory`)
- List all `inventory_transactions` with filters (item, type, date range)
- Manual Adjustment: select item, type (add/remove), qty, reason

### Item Expiry (`/inventory/expiry`)
- List items with `expiry_date` within threshold
- Color code: green (>90d), yellow (30-90d), red (<30d or expired)

---

## 13. NAVIGATION STRUCTURE

```
Sidebar:
├── 🏪 POS (Point of Sale)
├── 📦 Items
│   ├── All Items
│   ├── Categories
│   └── Item Grid Config
├── 👥 Customers
├── 🛒 Sales History
├── 🔄 Returns
├── 📊 Reports
│   ├── Daily Sales
│   ├── Sales Summary
│   ├── Item Sales
│   ├── Profit
│   ├── Stock
│   ├── Expiry
│   └── Cashier
├── 📥 Purchasing
│   ├── Purchase Orders
│   └── Vendors
├── 🏭 Inventory
│   ├── Transaction Log
│   └── Adjustments
├── 💳 Discounts
├── 👤 Users & Roles
└── ⚙️ Settings
```

RoleGate each nav item: hide items user lacks permission to access.

---

## 14. UI/UX REQUIREMENTS

- **Theme**: Dark sidebar, light main content area. Use CSS variables for theming.
- **Responsive**: POS screen is desktop-optimized (min 1024px). Other pages are responsive.
- **POS keyboard shortcuts**:
  - `F2` — focus search bar
  - `F10` — open payment modal
  - `Escape` — close any open modal
  - `F9` — hold cart
- **Currency**: Format all monetary values with configured currency symbol (default ₦). Use `useCurrency()` composable.
- **Date format**: Display as `DD-MMM-YYYY HH:mm` (e.g., `05-Apr-2025 14:30`)
- **Number format**: Use comma separator for thousands (e.g., `1,250.00`)
- **Loading states**: Show skeleton loaders on tables, spinner on POS item grid
- **Empty states**: Friendly empty state illustrations on all empty lists
- **Confirmation dialogs**: All delete and destructive actions require `ConfirmDialog`
- **Toast notifications**: Success (green), Error (red), Warning (yellow), Info (blue)

---

## 15. SEEDERS

Generate seeders for:

```php
// DatabaseSeeder order:
SettingsSeeder::class,   // single row with sensible defaults
UserSeeder::class,        // admin user: username=admin, password=admin123, tier=3, is_admin=true
CategorySeeder::class,    // 5 sample categories
ItemSeeder::class,        // 20 sample items with prices and qty
CustomerSeeder::class,    // 10 sample customers
```

---

## 16. LARAVEL ROUTE STRUCTURE

```php
// routes/web.php

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});

// Authenticated
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy']);

    // POS
    Route::get('/pos', [PosController::class, 'index'])->name('pos');

    // Sales
    Route::resource('sales', SaleController::class)->only(['index','store','show','destroy']);
    Route::post('/sale-returns', [SaleReturnController::class, 'store']);

    // Held Sales
    Route::resource('held-sales', HeldSaleController::class)->except(['edit','update']);

    // Items
    Route::resource('items', ItemController::class);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('item-grids', ItemGridController::class)->only(['index','store','update']);

    // Customers
    Route::resource('customers', CustomerController::class);
    Route::post('/customers/{customer}/debt', [DebtController::class, 'store']);

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);

    // Purchases
    Route::resource('purchases', PurchaseOrderController::class);
    Route::post('/purchases/{purchase}/receive', [PurchaseOrderController::class, 'receive']);
    Route::resource('vendors', VendorController::class)->except(['show']);

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/daily', [ReportController::class, 'daily'])->name('daily');
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/items', [ReportController::class, 'items'])->name('items');
        Route::get('/profit', [ReportController::class, 'profit'])->name('profit');
        Route::get('/stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('/expiry', [ReportController::class, 'expiry'])->name('expiry');
        Route::get('/debts', [ReportController::class, 'debts'])->name('debts');
        Route::get('/cashier', [ReportController::class, 'cashier'])->name('cashier');
        Route::get('/export/{type}', [ReportController::class, 'export'])->name('export');
    });

    // Discounts
    Route::resource('discounts', SaleDiscountController::class)->except(['show']);

    // Users & Roles
    Route::resource('users', UserController::class)->except(['show']);
    Route::put('/users/{user}/roles', [RoleController::class, 'update']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update']);
});

// API Routes (routes/api.php) — also under auth middleware
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/items/search', [ItemController::class, 'search']);
    Route::get('/customers/search', [CustomerController::class, 'search']);
    Route::get('/held-sales', [HeldSaleController::class, 'apiIndex']);
    Route::post('/held-sales', [HeldSaleController::class, 'apiStore']);
    Route::delete('/held-sales/{id}', [HeldSaleController::class, 'apiDestroy']);
    Route::get('/settings', [SettingsController::class, 'apiShow']);
    Route::get('/reports/daily', [ReportController::class, 'apiDaily']);
});
```

---

## 17. ERROR HANDLING

- All controllers: wrap DB operations in `DB::transaction()` for multi-step writes
- Return Inertia errors via `back()->withErrors()` or Inertia's `$page->props.errors`
- API routes: return `response()->json(['error' => '...'], 4xx)`
- Log all caught exceptions via Laravel's logger
- Display user-friendly error messages in the Vue UI (never expose stack traces)

---

## 18. TESTING

Generate the following tests:

**Unit Tests (PHPUnit)**
```
SaleServiceTest: test_receipt_id_generation, test_sale_total_calculation, test_profit_calculation, test_qty_deduction, test_debt_flagging
InventoryServiceTest: test_restock_increments_qty, test_sale_decrements_qty
ReturnServiceTest: test_return_restocks_item, test_return_cannot_exceed_original_qty
RoleServiceTest: test_tier_check, test_role_check
```

**Feature Tests (PHPUnit)**
```
SaleControllerTest: test_cashier_can_create_sale, test_manager_can_delete_sale, test_cashier_cannot_delete_sale
ItemControllerTest: test_create_item, test_update_item, test_item_search_returns_results
CustomerControllerTest: test_create_customer, test_customer_search
SettingsControllerTest: test_admin_can_update_settings, test_cashier_cannot_update_settings
AuthControllerTest: test_login_with_valid_credentials, test_login_fails_with_wrong_password
```

---

## 19. DEPLOYMENT FILES

Generate:

**`Dockerfile`**
```dockerfile
FROM php:8.2-fpm
# Install extensions: pdo_mysql, mbstring, bcmath, gd, zip
# Install Composer
# Copy app, run composer install --no-dev --optimize-autoloader
# Expose 9000
```

**`docker-compose.yml`**
```yaml
services:
  app: (php-fpm)
  nginx: (nginx:alpine, port 80/443)
  db: (mysql:8 or mariadb:10.4)
  node: (for build only, not runtime)
```

**`.github/workflows/deploy.yml`**
```yaml
on: [push to main]
jobs:
  test: composer test
  build: npm run build
  deploy: ssh deploy + artisan migrate --force
```

**`nginx.conf`** — configured for Laravel, serves built assets

---

## 20. IMPLEMENTATION MILESTONES

Execute in this exact order:

| # | Milestone | Deliverables |
|---|-----------|--------------|
| M0 | Project scaffold + DB | Migrations, Models, Seeders, Auth |
| M1 | Items & Categories | CRUD pages, search API |
| M2 | Customers | CRUD pages, search API, debt tracking |
| M3 | POS Core | Cart (Pinia), Classic mode, Payment, Receipt |
| M4 | POS Gallery Mode | Item grid config, gallery UI, barcode scan |
| M5 | Hold, Return, Discounts | Hold cart, return flow, discount engine |
| M6 | Purchasing & Inventory | PO flow, receive items, inventory log |
| M7 | Reports | All 8 report pages + CSV export |
| M8 | Users, Roles, Settings | Role management, settings page, RoleGate |
| M9 | Tests + Deployment | Unit/Feature tests, Docker, CI/CD |

---

## 21. FINAL CHECKLIST FOR AGENT

Before marking complete, verify:

- [ ] All migrations run cleanly: `php artisan migrate:fresh --seed`
- [ ] Login works with seeded admin user
- [ ] POS screen loads items, adds to cart, processes a cash sale
- [ ] Receipt modal displays and prints correctly
- [ ] Hold cart saves and restores
- [ ] Return processes and restocks inventory
- [ ] Daily sales report shows today's sales
- [ ] Settings save and reload on next page visit
- [ ] Role checks block unauthorized actions (delete sale as tier-0 user)
- [ ] All `npm run build` assets compile without errors
- [ ] `php artisan test` passes all generated tests

---

*End of Agent Prompt — SkyNet POS Web System*
