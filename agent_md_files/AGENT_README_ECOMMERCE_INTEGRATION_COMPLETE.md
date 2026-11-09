# POS System Ecommerce Inventory Integration Agent README

## 1. Purpose

This document defines how the existing live multi branch POS system should integrate with a separately deployed standalone ecommerce application.

The ecommerce application is not being added inside the POS application. It remains its own independent single store system with its own database, customer experience, business rules, checkout, reporting, payment logic, shipping logic, discounts, coupons, referrals, marketing features, and online order management.

The POS is multi branch, but each standalone ecommerce installation connects to exactly one selected POS branch. An ecommerce installation must never automatically span, combine, or switch between multiple POS branches. If another branch needs its own online store, that branch should be connected to its own separately deployed ecommerce installation unless a future requirement explicitly introduces a multi store ecommerce product.

The POS remains the authority for physical inventory and the standard item pricing data that is shared with the ecommerce application.

The integration must be additive and must not rebuild, replace, or route ecommerce orders through the existing POS cashier sale process.

The ecommerce application will be recloned from the existing ecommerce project and can be extensively modified to support this integration. The current POS is already live, so the POS side must be changed carefully and minimally.


## 1A. Organization Layer Context (Read Before Proceeding)

This POS system has a separate Organization Layer README describing a client-account layer that sits above branches: Platform Super Admin (Skynet Digital) manages multiple client Organizations, each with one or more branches. Everywhere this document says "Super Admin," read that as the Platform Super Admin role — the same role, same permissions, same identifier in code, just named precisely now that the Organization Layer exists.

This module must only be built once the Organization Layer README has reached at least Phase 1 (the `organizations` table exists and every branch has an `organization_id`). It does not need to wait for Organization Phase 4 or Phase 6, since those are about delegating access to Organization Admins, which has no bearing on this Super-Admin-only feature.

An ecommerce installation represents one client's online store. It connects to one branch, and that branch already belongs to one Organization (per the Organization Layer's `branches.organization_id`). Section 8.1 below reflects this directly in the data model.


## 2. Confirmed Architecture

There are two independent applications with different tenancy models.

The POS is multi branch. The ecommerce application is standalone and represents one online store. Each ecommerce installation is paired with one and only one POS branch selected by Super Admin.

### 2.1 POS application

The POS owns:

* Item identity
* Branch ownership
* Base unit information
* Additional item units from the existing flexible units system
* Standard selling prices
* Wholesale prices where applicable
* Front store physical quantity
* Back store physical quantity
* Stock movement history
* IMEI or serialized inventory where enabled
* Batch and expiry inventory behavior where enabled
* Physical shop sales
* Existing POS reports
* Existing POS discounts
* Existing POS cashier checkout
* Existing offline checkout and synchronization

### 2.2 Ecommerce application

The ecommerce application owns:

* Online product name
* Online product slug
* Online descriptions
* Product images and galleries
* Online categories
* Brands and online merchandising information
* SEO fields
* Online customer accounts
* Shopping cart
* Wishlist if supported
* Online checkout
* Online payment processing
* Shipping and delivery
* Delivery addresses
* Online order status
* Online order reports
* Online customer reports
* Online payment reports
* Online shipping reports
* Ecommerce discounts
* Coupon codes
* Referral program
* Loyalty program if added later
* Online promotions
* Featured products
* Online publishing status
* Reviews if supported
* All other ecommerce specific behavior

The POS must not duplicate these ecommerce responsibilities.


## 2.3 Multi Branch POS Versus Standalone Ecommerce Boundary

This boundary is critical and must be preserved throughout the implementation.

The POS platform may contain many branches:

```text
Skynet POS
    Branch A
    Branch B
    Branch C
```

A standalone ecommerce deployment represents only one online store and connects to only one of those branches:

```text
Standalone Ecommerce Store A
        |
        | authenticated inventory integration
        v
Skynet POS Branch A
```

Another branch that requires online shopping should use a separate ecommerce deployment:

```text
Standalone Ecommerce Store B
        |
        | authenticated inventory integration
        v
Skynet POS Branch B
```

The ecommerce application must never receive the POS branch directory, browse other branches, aggregate stock across branches, choose a branch during checkout, or move an order from one branch to another.

The selected branch is fixed by the Super Admin connection configuration. All item sync, pricing, front store availability, reservations, confirmations, releases, IMEI operations, and inventory movement logs are scoped only to that connected branch.

This keeps the ecommerce application simple and standalone while preserving the POS multi branch architecture.


## 3. Core Principle

The two systems share inventory identity, standard prices, and availability, but they do not share their sales engines.

An online order is not a POS cashier sale.

The ecommerce application must never call the normal POS checkout endpoint, POS cart process, cashier sale controller, receipt process, POS discount engine, or cashier payment logic in order to complete an online order.

The ecommerce application communicates with a dedicated inventory integration API.

The integration API performs only the inventory responsibilities required to safely reserve, confirm, release, and audit stock used by the ecommerce application.


## 4. Existing POS Item Table Must Remain Intact

This is a critical guardrail.

The current item table must not be redesigned for ecommerce integration.

The existing quantity fields remain exactly as they are:

```text
front_store_qty
back_store_qty
```

Do not add any of the following fields to the items table:

```text
reserved_qty
available_qty
online_qty
ecommerce_qty
online_reserved_qty
```

The integration must use separate new tables.

The current POS inventory model remains the source of truth.


## 5. Ecommerce Uses Front Store Quantity Only

The ecommerce system sells from the POS front store inventory.

The authoritative physical quantity for ecommerce availability is:

```text
items.front_store_qty
```

The ecommerce system must not use:

```text
items.back_store_qty
```

when calculating online availability.

Example:

```text
front_store_qty = 0
back_store_qty = 100
```

The ecommerce application must treat the item as unavailable for online sale.

The 100 units in the back store only become available online after the existing POS stock movement process transfers the required quantity into the front store.

Do not create any automatic back store to front store transfer for ecommerce orders.


## 6. Reservation Based Inventory Model

The ecommerce application must not permanently reduce front store quantity merely because a customer adds an item to cart or begins checkout.

A separate reservation system must protect stock while an online order is being processed.

The POS physical quantity stays in:

```text
items.front_store_qty
```

The active reserved quantity is calculated from the new reservation records.

The effective sellable quantity is calculated dynamically as:

```text
Available Quantity = front_store_qty minus Active Reserved Quantity
```

Available Quantity is a calculated value.

It must not be stored as another column on the items table.


## 7. Recommended New POS Module

Keep the ecommerce integration isolated from the existing POS code as much as possible.

Recommended structure:

```text
app/Modules/CommerceIntegration/

Config/
Controllers/
Models/
Services/
Routes/
Migrations/
Events/
Listeners/
Policies/
CommerceIntegrationServiceProvider.php
```

Suggested responsibilities:

```text
Controllers
    CatalogApiController
    AvailabilityApiController
    ReservationApiController
    IntegrationSettingsController

Models
    CommerceIntegration
    CommerceReservation
    CommerceReservationItem
    CommerceInventoryMovement

Services
    CommerceCatalogService
    InventoryAvailabilityService
    InventoryReservationService
    InventoryConfirmationService
    InventoryMovementService

Routes
    commerce_api.php
```

The exact folder structure may be adjusted to match the existing project convention, but the integration should remain clearly separated from normal POS checkout logic.


## 8. New Database Tables

Because the POS is live, all POS database changes require new migration files.

Do not edit old migrations.

### 8.1 commerce_integrations

Purpose: stores the connection configuration for an external ecommerce store.

Suggested fields:

```text
id
branch_id
organization_id
name
source_key
is_active
api_client_id
api_secret_hash or encrypted credential reference
last_connected_at
created_at
updated_at
```

Notes:

* `organization_id` is derived automatically from the selected branch's organization at the moment Super Admin creates the connection (`organization_id = branch.organization_id`). It is not a second, independently chosen field in the connection flow — it exists purely for query efficiency, reporting, and the tenant isolation checks in section 37A and section 50.16.
* One POS branch can have at most one active standalone ecommerce integration in the current design
* One standalone ecommerce installation can be connected to exactly one POS branch
* `branch_id` should be unique for active commerce integration mapping so the same branch cannot accidentally be connected twice
* The integration credential must be permanently scoped to the selected branch and must not be allowed to switch branches through an API request parameter
* Supporting one ecommerce installation across multiple POS branches is explicitly out of scope
* If another POS branch needs online shopping, connect that branch to another standalone ecommerce deployment
* Credentials must never be stored as plain readable text
* Prefer Laravel Sanctum or another proper server to server authentication mechanism
* Do not reuse a cashier or admin web login session as API authentication

### 8.2 commerce_reservations

Purpose: parent record representing one external ecommerce order reservation.

Suggested fields:

```text
id
branch_id
commerce_integration_id
external_order_reference
status
reserved_at
expires_at
confirmed_at
released_at
created_at
updated_at
```

Recommended status values:

```text
reserved
confirmed
released
expired
```

The combination of branch, integration, and external order reference should be unique so repeated API calls cannot accidentally create duplicate reservations for the same online order.

### 8.3 commerce_reservation_items

Purpose: stores the item lines belonging to a reservation.

Suggested fields:

```text
id
commerce_reservation_id
item_id
item_unit_id nullable
ordered_quantity
quantity_in_base_units
base_quantity_reserved
created_at
updated_at
```

Example:

If one Roll contains 10 base units and the ecommerce customer orders 2 Rolls:

```text
ordered_quantity = 2
quantity_in_base_units = 10
base_quantity_reserved = 20
```

All inventory calculations must ultimately use base quantity so the existing POS stock model remains correct.

### 8.4 commerce_inventory_movements

Purpose: provides a dedicated audit trail for inventory changes originating from the ecommerce integration if the existing POS stock movement log cannot represent this cleanly.

Before creating this table, inspect the existing stock movement or inventory history system.

If the existing stock movement system already supports a source and external reference without going through the cashier sale process, reuse it instead of creating a duplicate log.

If a dedicated table is required, suggested fields are:

```text
id
branch_id
item_id
item_unit_id nullable
commerce_integration_id
external_order_reference
movement_type
base_quantity
front_store_quantity_before
front_store_quantity_after
created_at
```

Suggested movement types:

```text
reservation_confirmed
reservation_released
reservation_expired
online_return_restocked
manual_integration_adjustment
```


## 9. Standard Product Mapping

The ecommerce application stores the POS item reference.

Example ecommerce product fields:

```text
id
pos_branch_id
pos_item_id
online_name
slug
description
online_category_id
brand_id
published
featured
```

The ecommerce product can use a completely different customer facing name from the POS item name.

Example:

POS name:

```text
Samsung Galaxy A56 256GB
```

Online name:

```text
Samsung Galaxy A56 5G 256GB Smartphone
```

Both still refer to the same POS item through:

```text
pos_item_id
```

Do not use item name as the integration key.

Do not use online slug as the integration key.

The POS item identifier and branch identifier are the authoritative mapping values.


## 10. Online Product Attributes Stay in Ecommerce

The following information must not be added to the POS item table just because the ecommerce site needs it:

* Online name
* Online slug
* Long ecommerce description
* Ecommerce gallery images
* Online category hierarchy
* SEO title
* SEO description
* Featured status
* Homepage placement
* Customer rating
* Review data
* Referral configuration
* Coupon configuration
* Shipping classification
* Online publishing status

The recloned ecommerce application can be redesigned freely to store these values in its own database.


## 11. Category Independence

POS categories and ecommerce categories are independent.

A POS item may belong to:

```text
Phones
```

while the ecommerce store displays it under:

```text
Electronics
Mobile Phones
Android Phones
```

The ecommerce category system should not modify the POS category system.

The ecommerce application simply maps the POS item into the online category structure it needs.


## 12. Price Ownership

The standard item selling price comes from the POS.

For the current integration, the ecommerce application should treat the POS standard price as the normal online product price.

The ecommerce application may cache the latest synchronized value for performance, but the POS remains the authority for that standard price.

If the POS standard selling price changes, the ecommerce copy should update during synchronization.

For additional item units, use the existing POS flexible unit prices exactly as configured.

Do not calculate a variant price by multiplying the base price.

The ecommerce application may sell whichever supported POS unit levels are intentionally exposed online.


## 13. Ecommerce Discounts Do Not Belong to POS

The ecommerce application manages its own final customer pricing behavior.

This includes:

* Ecommerce discount campaigns
* Coupon codes
* Referral discounts
* Referral rewards
* Loyalty rewards
* Flash sales
* Bundle offers
* Customer specific promotions
* Free shipping promotions
* Any future ecommerce marketing incentive

Example:

POS standard price:

```text
10000
```

Ecommerce coupon:

```text
20 percent discount
```

Online customer pays:

```text
8000
```

The POS does not need to reproduce, validate, store, or calculate that coupon.

The POS integration only needs the inventory reservation and confirmation information.

Do not send ecommerce coupon logic into the existing POS Item Discount, Group Discount, or Overall Discount engine.

Do not allow ecommerce discounts to modify the POS standard item price.


## 14. Ecommerce Reporting Remains Independent

Do not add ecommerce sales reports, shipping reports, payment reports, referral reports, coupon reports, customer reports, or delivery reports into the POS merely because the two systems are connected.

The ecommerce application remains responsible for reporting on its own activity.

The POS only needs enough information to audit why physical inventory changed.

For example, the POS inventory log may show:

```text
Source: Ecommerce
Reference: WEB1048
Item: Peak Milk
Quantity Out: 3
```

Detailed online order information remains inside the ecommerce application.


## 15. API Must Be Independent From POS Sale Process

This is a critical requirement.

Do not reuse the POS cashier checkout controller as the ecommerce API.

Do not create a fake cashier transaction to represent an ecommerce order.

Do not place ecommerce orders into the normal POS cart.

Do not run ecommerce orders through the POS receipt flow.

Do not apply normal POS checkout discounts to ecommerce orders.

Do not require a cashier session for ecommerce API requests.

The integration API talks directly to the inventory integration services.

The existing POS sale process continues operating normally for physical shop sales.


## 16. Recommended API Versioning

Use a dedicated versioned API namespace.

Example:

```text
/api/commerce/v1/
```

This allows the ecommerce integration contract to evolve without breaking the normal POS web routes or future integrations.


## 17. Recommended Catalog API

### 17.1 List items

Conceptual endpoint:

```text
GET /api/commerce/v1/items
```

The ecommerce application can use this for initial item linking and synchronization.

Return only fields that are genuinely needed for integration.

Suggested response data:

```text
item_id
branch_id
pos_name
barcode
sku if used
status
base_unit_name
standard_selling_price
wholesale_price if needed
front_store_qty
additional_units
track_by_imei if enabled
batch_tracking_enabled if enabled
updated_at
```

Do not expose back store quantity as ecommerce sellable inventory.

Back store quantity may be omitted entirely from the ecommerce API unless there is a clear administrative reason to display it.

### 17.2 Single item

Conceptual endpoint:

```text
GET /api/commerce/v1/items/{item_id}
```

### 17.3 Availability

Conceptual endpoint:

```text
GET /api/commerce/v1/items/{item_id}/availability
```

Suggested response:

```text
front_store_qty
active_reserved_quantity
available_quantity
```

These values are calculated from the current POS inventory and active commerce reservations.


## 18. Reservation API

Prefer one reservation request containing all order lines instead of requiring the ecommerce application to reserve every line independently.

Conceptual endpoint:

```text
POST /api/commerce/v1/reservations
```

Suggested request concept:

```text
external_order_reference
expires_at
items
    item_id
    item_unit_id nullable
    ordered_quantity
```

The POS integration service calculates the required base quantity using the current POS item unit conversion.

Do not trust a base quantity supplied by ecommerce without validating it against POS data.

The server must verify that every requested item belongs to the integration branch.

The server must verify that each requested unit belongs to the correct item.

The server must verify that enough effective quantity is available before accepting the reservation.


## 19. Reservation Must Be Atomic

An ecommerce order may contain several products.

Reservation should be all or nothing.

Example:

Customer orders:

```text
Item A quantity 2
Item B quantity 1
Item C quantity 4
```

If Item C does not have enough stock, do not reserve Item A and Item B while leaving Item C failed unless the ecommerce application explicitly supports partial fulfilment.

For the initial implementation, use one database transaction so the complete reservation either succeeds or fails together.


## 20. Reservation Concurrency Protection

Two customers may attempt to buy the final available quantity at the same time.

The reservation service must prevent over reservation caused by simultaneous API requests.

Use a database transaction and appropriate row locking around the item availability calculation.

The implementation must calculate:

```text
front_store_qty minus active reservation base quantity
```

inside the protected transaction before creating the new reservation.

Do not rely only on an earlier availability value that the ecommerce application cached.

The final reservation API call is the authoritative stock check.


## 21. Reservation Lifecycle

### 21.1 Reserved

When ecommerce checkout requires stock protection:

```text
status = reserved
```

The POS item front store quantity is not reduced yet.

Example:

```text
front_store_qty = 20
active_reserved_quantity = 4
available_quantity = 16
```

The items table still contains:

```text
front_store_qty = 20
```

### 21.2 Confirmed

When the ecommerce application determines that the inventory should now be permanently consumed, it confirms the reservation.

Conceptual endpoint:

```text
POST /api/commerce/v1/reservations/{reference}/confirm
```

The confirmation service:

* Validates that the reservation is still active
* Starts a database transaction
* Locks the relevant inventory rows
* Deducts the confirmed base quantity from `front_store_qty`
* Records the ecommerce inventory movement
* Marks the reservation as confirmed
* Commits everything together

Example before:

```text
front_store_qty = 20
reserved = 4
available = 16
```

Example after confirmation:

```text
front_store_qty = 16
reserved = 0
available = 16
```

### 21.3 Released

If the ecommerce order is cancelled before inventory confirmation:

Conceptual endpoint:

```text
POST /api/commerce/v1/reservations/{reference}/release
```

Only the reservation status changes.

Do not increase front store quantity because it was never deducted.

Example:

```text
front_store_qty = 20
reserved = 4
```

After release:

```text
front_store_qty = 20
reserved = 0
available = 20
```

### 21.4 Expired

Reservations should support expiration so stock is not locked forever when an online checkout is abandoned.

A scheduled process should mark overdue active reservations as expired.

Expired reservations stop counting toward active reserved quantity.

The exact reservation duration can be configurable later, but the architecture must support `expires_at` from the beginning.


## 22. Idempotency Is Mandatory

API requests may be retried because of network failure, server timeout, payment gateway callback retry, or ecommerce worker retry.

The same ecommerce order must not reserve twice.

The same reservation must not confirm twice.

The same release must not release stock twice.

Use the external order reference plus integration identity and a proper idempotency strategy.

Repeated successful calls should return the existing result instead of creating duplicate inventory effects.


## 23. When Ecommerce Should Reserve Stock

Do not reserve stock merely when a customer adds an item to cart.

Shopping carts are often abandoned.

Reservation should occur at a meaningful checkout stage chosen by the ecommerce application, for example when the customer proceeds to payment or when an order is formally created.

The ecommerce application owns that decision.

The POS only provides the reservation mechanism.


## 24. When Ecommerce Should Confirm Stock

The ecommerce application owns the order lifecycle and decides when an online reservation becomes a permanent inventory deduction.

Examples could include:

* Payment confirmed
* Cash on delivery order accepted by the store
* Order approved for fulfilment

The POS must not attempt to infer ecommerce order status.

The ecommerce application explicitly sends the confirmation request.


## 25. Cancellation After Confirmation

Cancellation before confirmation simply releases the reservation.

Cancellation or refund after confirmation is different because the physical stock has already been deducted.

Do not automatically add the quantity back merely because the ecommerce system issued a refund.

A refund does not always mean the physical product has returned to usable inventory.

Restocking after a confirmed ecommerce order must be an explicit action after the returned item is physically received and accepted.

A future endpoint may support:

```text
POST /api/commerce/v1/returns/restock
```

If implemented, it must create a proper inventory movement and increase `front_store_qty` only after the ecommerce return workflow explicitly confirms physical restocking.


## 26. Physical POS Checkout Must Respect Active Online Reservations

The existing physical POS sale process must not be replaced or routed through the ecommerce integration.

However, while the POS is online, it must not sell stock already actively reserved for ecommerce.

Create or reuse a single inventory availability service that calculates:

```text
available_quantity = front_store_qty minus active_commerce_reservations
```

The physical POS availability validation should use this effective quantity while online.

This should be the smallest possible touchpoint to existing checkout behavior.

The normal POS sale still performs its existing stock deduction logic.

The ecommerce integration does not call that sale logic.


## 27. Offline POS Compatibility

The POS already supports offline cashier sales.

Do not damage that architecture.

A cashier terminal that is completely offline cannot receive a new ecommerce reservation created on the server while the terminal is disconnected.

This is an unavoidable disconnected device limitation.

Example:

```text
front_store_qty = 5
```

The ecommerce system reserves all 5 while a cashier device is offline with an older cached value of 5.

That offline cashier may still physically sell one unit before reconnecting.

Do not attempt to solve this by removing offline checkout.

When the device reconnects, the existing synchronization and stock conflict handling architecture should reconcile the resulting discrepancy.

When online data refreshes, the local checkout cache should receive the latest effective availability.

Do not create a second offline synchronization engine exclusively for ecommerce reservations.


## 28. Catalog Synchronization

The ecommerce application should synchronize selected POS item information rather than directly reading the POS database.

Recommended synchronization data:

* POS item identifier
* Branch identifier
* Barcode if useful online
* SKU if used
* POS display name for administrative mapping
* Base unit name
* Standard selling price
* Wholesale price only if the ecommerce use case needs it
* Additional item units and their independent prices
* Current effective availability
* Item active status
* IMEI tracking status where relevant
* Updated timestamp

The ecommerce application stores its own online presentation data separately.


## 29. Synchronization Strategy

The design should support both periodic synchronization and future push notifications.

For the first implementation, a practical approach is:

* Initial catalog import from the POS API
* Scheduled ecommerce synchronization for changed items and prices
* Availability refresh at suitable intervals
* Final live availability check through the reservation API during checkout

A future enhancement may add POS webhooks so price or inventory changes notify the ecommerce application immediately.

Do not make webhooks a hard requirement for the first working integration if scheduled synchronization plus live reservation validation is sufficient.


## 30. Ecommerce Can Rename Items Freely

The ecommerce application may change:

* Online product name
* Online slug
* Online description
* Online category
* Images
* SEO information
* Marketing copy

without modifying the POS item.

The link remains the POS item identifier.

If the POS item name later changes, the ecommerce system may update the administrative mapping display while preserving the online customer facing name unless the ecommerce administrator chooses otherwise.


## 31. Flexible Unit Support

The integration must understand the current flexible item unit architecture.

The POS base unit remains on the items table.

Additional units remain in `item_units`.

Each additional unit has its own conversion quantity and independent prices.

The ecommerce application may expose selected POS unit levels as online purchasing options.

Example:

```text
Base Unit
1 Piece
Price 250

Additional Unit
1 Roll
quantity_in_base_units 10
Price 2250
```

If an ecommerce customer orders 2 Rolls:

```text
base_quantity_reserved = 20
```

Do not calculate the Roll price as 10 multiplied by the base price.

Use the actual POS Roll price.


## 32. IMEI Tracked Items

The integration must not break existing IMEI behavior.

For an IMEI tracked item, available online quantity should be based on the currently available front store IMEI records according to the existing POS IMEI rules, not on a manually invented quantity.

The ecommerce storefront normally should not expose IMEI numbers to customers.

Reservation may reserve a quantity of serialized units.

Before permanent confirmation, the integration must ensure that the exact serialized unit or units can be allocated according to the existing POS IMEI rules.

Do not duplicate IMEI state handling in the ecommerce application.

Do not route the online order through the POS cashier sale process merely to mark an IMEI as sold.

Instead, the commerce integration service should reuse the existing IMEI inventory service or model operations directly and safely.

If the current IMEI implementation has no reusable service, extract the minimum reusable inventory operation rather than calling the cashier checkout controller.


## 33. Batch and Expiry Tracked Items

If batch tracking is enabled for an item, ecommerce confirmation still reduces the real front store quantity through the commerce inventory confirmation service.

Any existing batch side effect for stock decrease should be reused after the core stock deduction, consistent with the existing batch tracking principle that the core stock update happens first.

Do not make ecommerce confirmation depend on a cashier facing batch selection flow.

Do not introduce full FEFO enforcement through this integration unless that separate future phase has already been implemented system wide.


## 34. Barcode Behavior

Barcode remains an item level POS property.

The ecommerce application may store the barcode value for search, administration, or product data display if useful.

Do not create a second barcode authority inside ecommerce.

Do not modify barcode generation rules for this integration.


## 35. Inventory Movement Audit

Every confirmed ecommerce stock deduction must be traceable.

At minimum record:

```text
branch
item
base quantity
source ecommerce
external order reference
timestamp
front store quantity before
front store quantity after
```

The inventory audit record is not an ecommerce sales report.

Its purpose is to answer questions such as:

```text
Why did this item quantity reduce by 3?
```

Expected answer:

```text
Ecommerce order WEB1048
```

Detailed order, payment, shipping, coupon, referral, and customer information remains in ecommerce.


## 36. Super Admin Branch Ecommerce Integration Management

Ecommerce connection management is a Super Admin only function. It belongs inside the existing Super Admin Branch Directory and the dedicated per branch management space already used to manage each branch.

The intended flow is:

```text
Super Admin
    → Branch Directory
        → Open Branch
            → Ecommerce Integration
```

From that branch specific Ecommerce Integration section, Super Admin can:

The connection is a strict one to one mapping for the current design: the selected POS branch is linked to one standalone ecommerce installation, and that ecommerce installation is not allowed to access any other branch.

* Connect that POS branch to an ecommerce site
* Enable or disable the ecommerce integration for that branch
* Enter or update the connected ecommerce store name and integration details
* Generate, rotate, revoke, or replace API credentials
* Test the ecommerce connection
* Show the connected store name
* Show connection status
* Show last successful API activity or synchronization
* View active inventory reservations for that branch
* View confirmed ecommerce inventory movements for that branch
* View expired or released reservations
* Search by external ecommerce order reference
* Disconnect the ecommerce site from the branch when required

Do not create ecommerce connection controls in the Branch Admin settings screen.

Do not create ecommerce connection controls for Cashiers or any other branch role.

Do not add ecommerce order fulfilment, shipping, coupon management, customer management, ecommerce discounts, referrals, or ecommerce reporting to this POS management area. Those remain inside the ecommerce application.


## 37. Access Control and Ownership

Only Super Admin can create, change, enable, disable, test, reconnect, disconnect, or rotate credentials for a branch ecommerce integration.

This authority must be enforced server side and must not rely only on hiding buttons in the user interface.

Branch Admin must not be able to:

* Connect an ecommerce site
* Change the connected ecommerce site
* Enable or disable the ecommerce integration
* View, generate, rotate, revoke, or copy API credentials
* Disconnect the ecommerce site
* Modify integration endpoints or authentication settings

Cashiers and other branch staff must have no access to ecommerce integration configuration or credentials.

If operational visibility is needed later, a separate read only permission can be introduced for selected branch users to view reservation or inventory movement records. That read only visibility must never grant integration configuration authority.

For the current implementation, connection management remains strictly Super Admin only.


## 37A. Tenant Isolation Across Organizations

The Organization Layer README requires an automated tenant isolation test suite proving one Organization can never reach another Organization's data. Commerce Integration must be included in that same guarantee.

A commerce integration authenticated for a branch belonging to Organization A must never be able to read, reserve, confirm, release, or view inventory movement for a branch belonging to Organization B. This already follows from the existing branch isolation rules in this document (section 50.15), since Organizations do not share branches. Add one explicit additional test confirming isolation holds even when a query is written against `commerce_integrations.organization_id` directly rather than `branch_id`, in case a future reporting or dashboard screen queries the organization-scoped column instead of the branch-scoped one.


## 38. API Security

The ecommerce API is server to server and must be protected accordingly.

Requirements:

* Authenticated integration credentials
* Branch scoped authorization
* Rate limiting
* Input validation
* Request logging
* No cross branch item access
* No plain text long lived secret stored unnecessarily
* Credentials can be revoked or rotated
* Sensitive internal POS fields are never exposed simply because the ecommerce server is authenticated

Every item and reservation operation must verify branch ownership server side.

The branch must be resolved from the authenticated ecommerce integration itself. The ecommerce client must not be trusted to choose or change `branch_id` on each request. Even if a request contains a branch identifier, the server must reject any value that does not match the branch permanently assigned to that integration credential.

Never trust `branch_id` from the request without matching it to the authenticated integration.


## 39. Failure Behavior

Browsing the ecommerce site may continue using recently synchronized product information when the POS API is temporarily unavailable.

However, final stock reservation should fail safely if the POS inventory service cannot verify and reserve quantity.

Do not accept an online order that claims stock is reserved when the reservation API actually failed.

The ecommerce application should show an appropriate stock verification or temporary availability message and allow the customer to retry.

This is safer than creating an order that may oversell unknown stock.


## 40. Item Deactivation and Missing Items

If a POS item becomes inactive or unavailable, the ecommerce synchronization should mark the mapped online product as unavailable for new purchase.

Do not automatically delete the ecommerce product because it may contain valuable descriptions, images, SEO history, and previous order relationships.

The ecommerce application may keep the product page while disabling purchase.

If the POS item reference no longer exists, the ecommerce system should surface an integration warning for administration rather than silently remapping it to another item.




## 40A. Ecommerce Mapping Registry Required for Safe Item Deletion

The POS must know which POS items are currently mapped to products or inventory variants in the connected ecommerce installation.

Do not copy the ecommerce catalog into POS.

Instead, maintain a lightweight integration mapping registry whose only purpose is to identify which POS items are actively referenced by the connected ecommerce application.

Recommended table:

```text
commerce_item_mappings
```

Suggested fields:

```text
id
branch_id
commerce_integration_id
item_id
external_product_reference
external_variant_reference nullable
is_active
registered_at
last_seen_at
unregistered_at nullable
created_at
updated_at
```

This table is integration metadata only.

It must not contain:

```text
online product description
online images
online category
SEO data
online customer data
online discounts
shipping data
```

Those remain ecommerce responsibilities.


## 40B. Mapping Registration

When ecommerce creates a POS linked online product or maps an ecommerce inventory variant to a POS item, ecommerce must register that mapping with the POS integration API.

The POS must validate:

* The authenticated commerce integration is active
* The POS item belongs to the branch assigned to that integration
* The item exists
* The item can be referenced by that integration
* The external product reference is present
* The external variant reference is stored when the mapping represents a true ecommerce variant

The ecommerce application must never be allowed to register a mapping against another POS branch.


## 40C. Mapping Update and Unregister

When ecommerce explicitly remaps a product or variant:

```text
old POS item
to
new POS item
```

the ecommerce application must update the POS mapping registry.

The old mapping becomes inactive only after the new mapping is successfully established.

When an ecommerce product is converted to Ecommerce Only inventory, deleted, or otherwise stops using a POS item, ecommerce must explicitly unregister the active mapping.

Do not consider a mapping removed merely because synchronization has not happened recently.

A temporary ecommerce outage must not cause POS to assume that the mapping disappeared.


## 40D. Hard Delete Protection for Mapped POS Items

Before hard deleting any POS item, the POS must check the ecommerce mapping registry.

If an active mapping exists, hard deletion must be blocked.

Example message:

```text
This item is currently linked to the connected ecommerce store and cannot be permanently deleted.

Disable the item or remove the ecommerce mapping first.
```

Do not silently disconnect the ecommerce mapping.

Do not automatically tell ecommerce to choose another item.

Do not delete ecommerce product data from POS.


## 40E. Active Reservation Protection

Before hard deletion, POS must also check for unresolved ecommerce reservations involving the item.

If the item belongs to a reservation with status:

```text
reserved
```

or any other unresolved active state, deletion must be blocked.

Example message:

```text
This item has active ecommerce inventory reservations.

Resolve or release the reservations before attempting permanent deletion.
```

An item must never disappear while ecommerce believes quantity is reserved against it.


## 40F. Preferred Item Lifecycle

For a live integrated POS, disabling or archiving is safer than permanent deletion.

Recommended normal lifecycle:

```text
Active
to
Inactive or Archived
```

When the item becomes inactive:

* Keep the POS item record
* Keep inventory audit history
* Keep ecommerce mapping metadata
* Ecommerce synchronization reports the item as inactive
* Ecommerce disables new purchase for the mapped product or variant
* Existing ecommerce order history remains unaffected

Disabling the item must not delete the ecommerce product.


## 40G. Permanent Deletion Preconditions

If the existing POS application still supports true hard deletion, permanent deletion should only be allowed after all of the following are true:

* No active ecommerce mapping exists
* No active ecommerce reservation exists
* No unresolved ecommerce inventory operation exists
* Historical commerce movement records will remain valid
* Existing POS audit requirements permit the item to be permanently deleted

If historical records depend on the item identity, prefer archive or soft deletion instead of hard deletion.


## 40H. Historical Commerce Audit Preservation

Deleting or archiving a POS item must never cascade delete:

* Confirmed ecommerce reservation history
* Released reservation history
* Expired reservation history
* Commerce inventory movement history
* External ecommerce order references
* Integration audit logs

Historical records must remain understandable after the product is no longer sold.


## 40I. POS Item Disable Versus Missing

The integration must distinguish:

```text
inactive
```

from:

```text
missing
```

Inactive means the POS item still exists but cannot currently be sold.

Missing means the ecommerce system requested a previously mapped POS item identifier and POS can no longer find the record.

For a properly controlled deletion workflow, an actively mapped item should normally become inactive rather than missing.


## 40J. Emergency Missing Item Scenario

The ecommerce application may still encounter a missing POS item because of:

* Manual database intervention
* Data restoration problems
* Legacy records created before deletion protection
* Integration corruption
* An administrative bypass outside the normal application workflow

The POS API must return an explicit not found or missing item response.

Do not return another item with a similar name, barcode, SKU, or price.


## 40K. Do Not Automatically Reuse Deleted Item Identity

If a deleted item is recreated later as a new POS record, it has a new POS item identity.

The integration must not automatically treat the new item as the old mapped item merely because:

```text
name matches
barcode matches
SKU matches
price matches
```

The ecommerce administrator must explicitly remap the online product or variant to the new POS item.


## 40L. Variant Mapping Protection

A true ecommerce variant may map to its own POS item.

Example:

```text
Blue Small
to
POS Item 501

Blue Medium
to
POS Item 502
```

If POS Item 502 is actively registered as the mapping for Blue Medium, POS must block hard deletion of Item 502.

Other variant mappings remain unaffected.


## 40M. Mapping Registry API Behavior

The POS commerce integration should expose authenticated operations conceptually equivalent to:

```text
register item mapping

update item mapping

unregister item mapping

list active mappings for current integration
```

Exact routes should follow the existing project API conventions.

All mapping operations must be branch scoped through the authenticated commerce integration.


## 40N. Mapping Reconciliation

Catalog synchronization should also help verify mapping health.

If ecommerce reports its active mappings, POS may reconcile its mapping registry.

Reconciliation must be explicit.

Do not delete a mapping simply because `last_seen_at` is old.

A full mapping reconciliation request may deactivate mappings that ecommerce explicitly confirms are no longer active.


## 40O. Super Admin Visibility

Inside:

```text
Super Admin
Branch Directory
Selected Branch
Ecommerce Integration
```

the Super Admin may view mapping health information such as:

```text
number of mapped POS items
items currently inactive
items with active reservations
mapping synchronization warnings
```

Super Admin does not need to manage online product names, descriptions, categories, images, variants, or discounts from POS.


## 40P. Deletion Protection Tests

Test all of the following:

* Active mapped POS item cannot be hard deleted
* Inactive mapped POS item remains mapped and ecommerce becomes unavailable for purchase
* Unmapped item can follow normal POS deletion rules
* Item with active reservation cannot be deleted
* Releasing reservation alone does not bypass an active product mapping
* Ecommerce unregister removes the active mapping
* Remapping moves protection from old POS item to new POS item
* Variant mapping protects the exact mapped POS item
* Hard delete does not cascade delete commerce audit history
* Recreated POS item with same name is not automatically remapped
* Super Admin can see mapping health
* Branch Admin cannot bypass Super Admin integration rules through mapping endpoints




## 40Q. Initial Branch Catalog Retrieval

After Super Admin connects a POS branch to a standalone ecommerce installation, the commerce integration API must allow that ecommerce installation to retrieve the eligible item catalog for only the authenticated branch.

The ecommerce application must not need to recreate POS items manually.

The catalog response should provide enough information for ecommerce administration and future mapping, including where available:

```text
POS item id
POS item name
barcode
item reference or SKU
active status
base unit
standard base selling price
effective online availability
IMEI tracking flag
POS item updated timestamp
additional selling units
additional unit ids
additional unit names
quantity_in_base_units
independent additional unit prices
```

Do not expose another branch through this catalog request.

Do not expose sensitive POS cost information unless a future requirement explicitly requires it.


## 40R. Initial Catalog Sync Does Not Publish Ecommerce Products

The POS API only exposes eligible branch inventory records.

It does not decide which POS items become public ecommerce products.

The ecommerce application may synchronize every eligible POS item into its local POS catalog without creating public online products for all of them.

This preserves the separation between:

```text
POS inventory catalog
```

and:

```text
published ecommerce catalog
```


## 40S. Incremental Catalog Retrieval

After the initial catalog fetch, the POS commerce API should support efficient incremental synchronization where practical.

Recommended behavior:

```text
items changed since timestamp
```

or an equivalent cursor based approach.

This allows ecommerce to discover:

```text
new POS items
price changes
availability changes
item activation changes
selling unit changes
```

without downloading the full branch catalog every time.


## 40T. Full Catalog Refresh

The POS commerce API must also permit a controlled full branch catalog refresh for:

```text
first connection
manual recovery
mapping reconciliation
major synchronization repair
```

A full catalog refresh remains branch scoped through the authenticated integration.


## 40U. Ecommerce Mapping Modes

The POS integration registry must support more than one ecommerce mapping shape.

At minimum, support:

```text
simple product mapping
```

where one ecommerce product points to one POS item.

Also support:

```text
variant mapping
```

where one ecommerce product may contain several ecommerce variant combinations and each inventory significant variant may point to its own POS item.

The POS does not need to understand the ecommerce merchandising meaning of:

```text
Blue
Medium
128GB
Black
Large
```

It only needs to know the external ecommerce mapping reference associated with the POS item.


## 40V. Simple Mapping Registration

When ecommerce imports a POS item as a simple online product, ecommerce registers a mapping such as:

```text
external product reference
to
POS item id
```

The POS stores the integration relationship in the commerce mapping registry.

The POS does not need ecommerce product descriptions, images, categories, SEO content, or customer facing names.


## 40W. Variant Mapping Registration

When ecommerce creates a true inventory variant, the mapping registry must support an additional external variant reference.

Conceptually:

```text
external product reference
external variant reference
to
POS item id
```

Example:

```text
Online Product
Nike Polo

Variant
Blue plus Medium

Mapped POS Item
502
```

The POS only stores enough information to protect Item 502 from unsafe deletion and to identify the ecommerce reference.


## 40X. Mapping Reassignment

The commerce API must support explicit reassignment from one POS item to another.

Recommended safe flow:

```text
validate replacement POS item
register or update new mapping
confirm new mapping is active
deactivate old mapping
```

Do not remove the old mapping before the replacement has been validated.

This avoids leaving ecommerce without a valid inventory reference during remapping.


## 40Y. Simple Product to Variant Product Transition

The POS mapping registry must support an ecommerce product that was originally mapped as a simple product later being converted into a variant product.

Example initial state:

```text
External Product A
to
POS Item 501
```

Later ecommerce may change to:

```text
External Product A
Variant Blue Small
to
POS Item 501

External Product A
Variant Blue Medium
to
POS Item 502

External Product A
Variant Black Small
to
POS Item 503
```

The POS does not redesign its own inventory.

Only the integration mapping relationships change.


## 40Z. Transactional Mapping Transition

A simple to variant mapping transition must not leave the POS registry in a partially converted state.

Recommended integration behavior:

```text
validate all requested new POS item mappings
register the new variant mappings
confirm success
only then retire the old simple mapping if it is no longer needed
```

If the transition fails partway through, preserve the previously valid mapping state and report the error to ecommerce.


## 40AA. Active Reservation Protection During Mapping Changes

Do not allow a mapping transition or reassignment to invalidate an unresolved active ecommerce reservation.

If the old mapping has an active reservation:

```text
reserved
```

or another unresolved state, the commerce API should reject or defer the mapping change unless the reservation can be safely reconciled.

The ecommerce application should resolve or expire the reservation before changing the inventory identity used for future sales.


## 40AB. Existing Reservation Identity Must Remain Stable

A reservation created against POS Item 501 must continue referring to POS Item 501 even if the ecommerce product is later remapped for future sales.

Do not rewrite historical or active reservation item identities because a mapping changed.


## 40AC. Mapping Unregister

The commerce API must support explicit mapping unregister.

Unregister is used when ecommerce:

```text
deletes or retires an online mapping
converts a POS linked product to Ecommerce Only
replaces an old mapping after successful remap
```

Unregister must not delete POS item history or reservation history.


## 40AD. POS Catalog Search for Ecommerce Mapping

The commerce API should provide branch scoped search suitable for ecommerce mapping screens.

Useful search inputs include where available:

```text
POS item name
barcode
item reference
SKU
```

Search results may return:

```text
item name
item id
standard price
effective availability
barcode
base unit
active status
```

The ecommerce administrator must never need to type a raw POS item id manually.


## 40AE. New POS Item Discovery

When new POS items are created after the ecommerce connection exists, they should appear during the next catalog synchronization as new unmapped catalog records.

The POS does not automatically publish them online.

Ecommerce decides whether to:

```text
import as a simple online product
use for a variant mapping
ignore for online sales
```


## 40AF. POS Selling Units During Catalog Sync

When the POS item has additional selling units, the commerce catalog response should include those units automatically.

The ecommerce administrator should not manually map:

```text
Single
Roll
Carton
```

when they already belong to the selected POS item.

Each returned unit should retain:

```text
POS item unit id
unit name
quantity_in_base_units
independent standard price
active status where applicable
```


## 40AG. Mapping Transition Audit

Record mapping lifecycle actions such as:

```text
mapping created
mapping reassigned
simple mapping converted to variant mappings
mapping unregistered
mapping reconciliation performed
```

Audit records should include:

```text
branch
commerce integration
POS item
external product reference
external variant reference where applicable
action timestamp
actor or integration source where available
```

Do not store ecommerce customer data in this audit.


## 40AH. Initial Catalog and Remapping Tests

Test all of the following:

* First ecommerce connection can fetch only its authenticated branch catalog
* Full catalog sync returns eligible POS items
* Incremental sync returns changed or new items
* POS selling units are returned with the parent item
* Simple product mapping can be registered
* Variant mapping can be registered
* Ecommerce can search POS items without typing ids
* Mapping reassignment validates the replacement item first
* Simple mapping can transition safely to several variant mappings
* Active reservations prevent unsafe mapping transition
* Historical reservation identity does not change after remap
* Mapping unregister removes deletion protection only after successful unregister
* New POS items appear as unmapped catalog records after synchronization
* POS does not automatically publish ecommerce products


## 41. Price Changes While Customer Is Shopping

The ecommerce application may show a cached POS standard price while the customer browses.

Before finalizing an order, ecommerce should use the latest synchronized price according to its own checkout rules.

The ecommerce application owns how it handles a price change that happens after an item enters the cart.

The POS does not need to manage the online cart price lifecycle.

The reservation API is primarily concerned with inventory quantity, not recreating ecommerce pricing rules.


## 42. Online Promotion Isolation

If ecommerce sells an item below the POS standard price because of:

* Coupon
* Referral reward
* Flash promotion
* Loyalty reward
* Bundle discount

this must not update the POS standard selling price.

The POS inventory movement records the quantity movement and external reference only.

The ecommerce reporting system records the financial result.


## 43. No Shared Database Requirement

Do not connect the ecommerce application directly to the POS database.

The systems should communicate through the authenticated API.

This permits:

* Separate deployment
* Separate databases
* Separate release cycles
* Easier future scaling
* Easier integration with other ecommerce systems later
* Reduced risk that a POS schema change breaks the ecommerce application

The two systems may be hosted on the same server initially, but the architecture must not depend on that.


## 44. Future Multi Channel Capability

Build the API generically enough that the POS is not permanently tied to this one ecommerce codebase.

The same inventory integration architecture may later support:

* Another custom ecommerce storefront
* Shopify connector
* WooCommerce connector
* Marketplace connector
* Mobile shopping application

The POS should therefore think in terms of an authenticated external commerce integration, not a hardcoded ecommerce website name.


## 45. Migration Rules for the Live POS

The POS is live.

All new tables must be created through new migrations.

Do not edit the original items migration.

Do not add reservation columns to the items table.

Do not rename `front_store_qty`.

Do not rename `back_store_qty`.

Do not change the meaning of either existing quantity field.

Before deployment:

* Take a full production database backup
* Test migrations on a staging copy
* Test reservation concurrency
* Test confirmation rollback
* Test release behavior
* Test branch isolation
* Test physical checkout while reservations exist
* Test offline checkout reconnect behavior


## 46. Required Transaction Safety

Every reservation confirmation must be atomic.

The following operations belong in one database transaction:

```text
Validate reservation
Lock inventory record
Validate current state
Deduct front_store_qty
Update serialized or batch side effects where applicable
Write inventory movement log
Mark reservation confirmed
```

If any critical step fails, roll back the entire confirmation.

Do not allow quantity to reduce while the reservation remains marked unconfirmed.

Do not mark a reservation confirmed while quantity deduction failed.


## 47. Required Validation

At minimum validate:

* Authenticated integration belongs to the branch
* POS item exists
* POS item belongs to the branch
* Item is active and sellable
* Requested additional unit belongs to the item
* Quantity is positive
* Required base quantity can be calculated
* Effective available quantity is sufficient
* Reservation reference is unique and idempotent
* Reservation is in the correct status before confirm or release
* Confirmed reservation cannot be confirmed again
* Released reservation cannot later be confirmed without an explicit new reservation
* Expired reservation cannot silently confirm unless business rules explicitly allow restoration


## 48. Suggested Integration Events

Internal Laravel events may make the module easier to extend without coupling it tightly to existing controllers.

Examples:

```text
CommerceReservationCreated
CommerceReservationConfirmed
CommerceReservationReleased
CommerceReservationExpired
CommerceInventoryChanged
```

These can later power:

* Webhook notifications
* Integration monitoring
* Admin notifications
* Synchronization tasks

Do not require external webhooks for the first release unless needed by the recloned ecommerce implementation.


## 49. Logging and Observability

Log integration failures clearly.

Important failures include:

* Authentication failure
* Unknown item reference
* Cross branch request
* Insufficient available stock
* Duplicate reservation request
* Confirmation of released reservation
* Database transaction failure
* IMEI allocation failure
* Synchronization failure

Logs should include the external order reference where available.

Never log raw API secrets.


## 50. Testing Scenarios

The agent must test at minimum the following.

### 50.1 Standard item reservation

```text
front_store_qty = 20
reserve 4
expected available = 16
expected stored front_store_qty still = 20
```

### 50.2 Reservation confirmation

```text
front_store_qty = 20
reserved = 4
confirm
expected front_store_qty = 16
expected active reserved = 0
```

### 50.3 Reservation release

```text
front_store_qty = 20
reserved = 4
release
expected front_store_qty = 20
expected active reserved = 0
```

### 50.4 Reservation expiry

An expired reservation no longer reduces calculated availability.

### 50.5 Multiple order lines

If one item cannot be reserved, the complete reservation transaction rolls back.

### 50.6 Concurrent final stock reservation

If two ecommerce customers simultaneously request the final available quantity, only the valid quantity is reserved.

### 50.7 Duplicate request retry

Retrying the same external order reference does not duplicate reservation quantity.

### 50.8 Duplicate confirmation retry

Retrying confirmation does not deduct stock twice.

### 50.9 Front store and back store separation

```text
front_store_qty = 0
back_store_qty = 100
```

Expected ecommerce availability:

```text
0
```

### 50.10 Flexible unit

If one Roll equals 10 base units and customer orders 2 Rolls, reserve and confirm 20 base units.

### 50.11 Ecommerce discount isolation

POS standard price remains unchanged after an ecommerce coupon or referral discount.

### 50.12 Different online name

Changing online product name does not modify POS item name and does not break item mapping.

### 50.13 Physical POS sale while online reservation exists

Online POS availability respects active ecommerce reservations without changing the normal cashier sale pipeline.

### 50.14 Offline cashier conflict

An offline cashier can continue using the existing offline architecture. On reconnection, any stock conflict created by a newer online reservation is handled through the existing reconciliation mechanism rather than crashing or deleting a valid physical transaction.

### 50.15 Branch isolation

An ecommerce integration authenticated for Branch A cannot read, reserve, confirm, release, or modify Branch B inventory.

### 50.16 Organization isolation

An ecommerce integration belonging to Organization A's branch cannot read, reserve, confirm, release, or view inventory movement for any branch belonging to Organization B, including when a query is made directly against `organization_id` rather than `branch_id`.


## 50A. Plan Gating — Ecommerce Integration Is a Gated, Quoted Feature

The Skynet pricing sheet lists, under Enterprise: "Optional integrations and API work quoted separately." This means ecommerce integration is not a standard feature automatically available on every plan — it is a custom, quoted add-on, and should be gated accordingly rather than left open to every plan by default.

Implementation, tying into the Plan Feature Enforcement README (Part 2):

* Add `feature_ecommerce_integration` to the `plan_capabilities` table. Default `false` for Starter, Bronze, Silver, and Gold. Default `true` for Enterprise.
* Since this is explicitly "quoted separately," also support a per-organization override — either a dedicated `organization_feature_overrides` table, or a simple boolean directly on the `organizations` table — so Super Admin can manually enable ecommerce integration for a specific client even when their plan would not normally include it, matching how a custom quote actually works commercially.
* The Ecommerce Integration section inside Branch Directory (section 36) must only be reachable and usable if the branch's Organization has this capability enabled (by plan default or by override) — same enforcement pattern as every other plan-gated feature in Part 2, enforced server side.
* Commerce Integration does not call into the AI module and does not consume AI credits (Part 3). No change needed there.


## 51. Explicitly Out of Scope for the POS Integration

Do not build the following inside the POS as part of this task:

* Ecommerce customer account management
* Online cart
* Online wishlist
* Ecommerce checkout UI
* Online payment gateway processing
* Shipping management
* Delivery tracking
* Ecommerce order administration
* Ecommerce order reports
* Coupon management
* Referral management
* Loyalty management
* Online promotion engine
* Ecommerce images
* Ecommerce category management
* Ecommerce SEO management
* Product reviews
* Abandoned cart management
* Online email marketing

Those remain responsibilities of the separate ecommerce application.


## 52. Existing Features That Must Not Be Broken

The integration must preserve all current POS behavior, including:

* Branch isolation
* Current item table structure
* `front_store_qty`
* `back_store_qty`
* Existing back store to front store workflow
* Flexible item units
* Independent unit prices
* Barcode scanning
* IMEI tracking
* Batch and expiry behavior
* POS discounts
* Existing overall discount
* Offline cashier sales
* Offline synchronization
* Stock conflict handling
* Held sales
* Notifications
* Printer behavior
* Reports
* AI features
* Existing role and permission enforcement

The ecommerce integration is an additive inventory channel, not a rewrite of the POS.

Additional deletion safety rules:

* Active ecommerce mappings must block POS hard deletion
* Active ecommerce reservations must block POS hard deletion
* Disabling or archiving is preferred over permanent deletion
* Historical commerce audit records must survive item retirement
* A recreated item must never inherit an old ecommerce mapping automatically



## 53. Final Architecture Summary

```text
SKYNET POS

Owns
Items
Standard Prices
front_store_qty
back_store_qty
IMEI Inventory
Batch Inventory
Physical POS Sales
POS Reports

        |
        |
        v

COMMERCE INTEGRATION API

Catalog Sync
Availability
Reservations
Confirmation
Release
Inventory Audit

        |
        |
        v

ECOMMERCE APPLICATION

Owns
Online Product Presentation
Online Names
Images
Categories
Customers
Cart
Checkout
Payments
Shipping
Orders
Discounts
Coupons
Referrals
Online Reports
```

The most important implementation rule is:

```text
POS is the inventory authority.
Ecommerce is the online commerce authority.
The API connects inventory only.
```

The POS `items` table remains intact.

The ecommerce system uses `front_store_qty` for inventory availability.

Reservations live in separate integration tables.

The ecommerce application does not use the POS cashier sale process.

The ecommerce application owns its own discounts, coupons, referrals, shipping, order handling, payments, and reports.

The POS only reserves physical inventory, confirms inventory deduction, releases reservations, and records enough inventory history to explain why stock changed.
