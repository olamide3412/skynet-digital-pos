# POS System — Public Branch Catalog (Shareable, Read-Only Price List) (Agent README)

**Note:** This adds a **public, read-only page** where a branch can share its current **front-store items and selling prices** (including every unit variant) with customers through a link. It is opt-in per branch and can be switched on or off in settings. It is a **price list, not a store**: no cart, no ordering, no customer accounts, no payments.

**This page is public, so security comes first.** Anything sent to the browser is visible to anyone. Read §2 and §11 before writing code.

---

## 1. Goal

- A Branch Admin turns the feature on, copies a link, and shares it (WhatsApp, social media, printed QR) with customers.
- Customers open the link on their phone without logging in and can browse, search and filter the branch's available items and see the selling price of every unit variant.
- The page looks polished and shows the branch's details (logo, name, address, contact).
- It never exposes private business data, and it never slows down the live POS.

---

## 2. Core Principles

1. **Opt-in and off by default.** Nothing becomes public until a Branch Admin deliberately turns it on.
2. **Strictly read-only.** GET requests only. No form posts, no writes, no mutation of any kind from this page.
3. **Whitelist, never blacklist.** The response contains only the fields listed in §5. Never pass full database models to the page (see §11).
4. **Protect the live POS.** The public page must never be able to slow checkout, so it uses paginated queries, short-lived caching and rate limiting (§10).
5. **Additive only.** No change to the POS selling page, pricing, discounts, stock logic, offline sync or the offline item cache.

---

## 3. Discovery First (No Changes)

Report findings before building:
1. POS items already support an **image** (though not every item has one). Where are they stored, and is that location publicly reachable or private storage?
2. Which **branch profile fields** exist (name, logo, address, phone, WhatsApp, etc.)? Use only fields that are already customer-facing (for example, those printed on receipts).
3. How is **category** stored on items?
4. Is there an existing **"available quantity"** calculation (front-store quantity minus active ecommerce reservations)? Reuse it. Do not invent another formula.
5. How are existing **branch-level toggles** stored? Reuse that mechanism.
6. What is the service worker's scope, and does it apply a navigation fallback to the app shell? (See §4.3.)
7. What is the existing **currency formatting** helper?

---

## 4. Setting, Link & Routing

### 4.1 Branch Setting
- A branch-level toggle, **"Public Catalog"** (On/Off), in the Branch Admin's settings area.
- **Default Off** for all existing and new branches.
- When On, the settings screen shows the **public link** with a **Copy** button and a **QR code** for the link:
  - The QR code can be **downloaded as an image** (PNG) and **printed** through a simple print-friendly view (for example for the shop counter or window).
  - It is generated **by the application itself**. Do not send the link to any third-party QR service.
  - It always encodes the **current** link, so it must update automatically if the slug changes.
- Platform Super Admin can also switch it off for any branch (override), per the usual pattern.
- Changing the setting is an online admin action and needs no offline support.

### 4.2 Public URL
- Use a dedicated route that **does not require login** and cannot collide with branch slugs, for example `/catalog/{branch-slug}`.
- **Add the chosen prefix to the reserved-slug list** (per the Organization Layer README) and verify no existing branch already uses it. Register this route before the generic branch routes.
- The link is the pretty, slug-based link (decided: no unguessable token). If Super Admin ever changes the slug, the public link changes too, and **links already shared and QR codes already printed will stop working**. Include this in the existing slug-change warning.
- **Unavailable state:** if the toggle is off, the branch is disabled, or the slug does not exist, return the **same generic "Catalog not available" page** for all three cases. The on/off and branch-disabled checks run on **every request** and are never served from cache.

### 4.3 Keep It Out of the POS App Shell
- The public page must **not** load the POS JavaScript bundle and must not register or depend on the POS service worker.
- Exclude the catalog route from any service-worker navigation fallback or precache, so a staff device with the installed POS PWA opening the link sees the catalog, not the POS shell.

---

## 5. What the Page Shows

### 5.1 Branch Header
Branch **logo, name, address**, and the **contact phone** where the branch already stores one. Nothing internal: no organization name, IDs, staff names, or email unless it is already customer-facing. No WhatsApp button and no new contact fields are added (decided out of scope).

### 5.2 Item Cards
For each eligible item (§6):
- **Image** when the item has one. Many items will not, so a clean, tasteful **placeholder** is required for every item without an image. The layout must look consistent with and without images.
- **Item name** and **category**.
- **Selling prices for all variants**:
  - The base unit with its unit name and selling price (for example, "Sachet — ₦250").
  - Each **active** variant from `item_units`, in `sort_order`, with unit name, selling price, and the pack size where helpful (for example, "Roll (10 sachets) — ₦2,250").
- Consumer **selling price only**.

### 5.3 Never Shown, Anywhere (Including the HTML Source)
Cost price, wholesale price, stock quantities (no numbers), back-store information, barcodes, IMEI numbers, batch or expiry data, discounts, internal IDs, branch/organization internals.

---

## 6. Item Eligibility Rules

An item appears only if **all** of these are true:
- It is an **active** item that can currently be sold at the POS.
- It has **front-store** stock available. Use the existing available-quantity logic (front-store minus active ecommerce reservations if that integration exists). **Back-store stock never counts.**
- It has a selling price greater than zero.
- It is not hidden (see §7).
- It is not blocked from sale by the existing legacy `expiry_date` rule.

Items that are out of stock are **hidden entirely**. There are no greyed-out "out of stock" cards.

**Variant availability:** show the base unit when available stock is at least 1 base unit. Show each variant only when available stock covers at least one of that variant (stock ≥ `quantity_in_base_units`). A "Roll of 10" must not be advertised when only 6 sachets are left.

For IMEI-tracked items, availability is the count of in-stock IMEIs. Never show the IMEIs.

---

## 7. Per-Item "Hide From Public Catalog"

Publishing the whole front store with no way to exclude anything is risky (test items, placeholder prices, prescription medicines, sensitive products).
- Add a simple **"Hide from public catalog"** checkbox on the item create/edit screen. Default: not hidden.
- It is a server-side field only. **Do not add it to the offline item cache**, the CSV import template, or the ecommerce sync.

---

## 8. Search, Filter, Sort & Pagination

- **Search** by item name (case-insensitive, partial match).
- **Filter** by category (show only categories that currently have visible items; items without a category grouped as "Other").
- **Sort**: name (A–Z) and price (low to high / high to low, using the base unit selling price).
- **Pagination is server-side.** Default **24 items per page**, hard maximum **48**, as named constants. Never load the whole catalog.
- Search, filter, sort and page are all **query parameters**, so any filtered view is itself a shareable link. Changing search or filter resets to page 1.
- Show the result count and current range, and friendly empty states ("No items match your search").
- Make search and filter work as plain GET forms (no JavaScript required), with optional enhancement on top.

---

## 9. Look & Feel

- **Mobile-first.** Customers will mostly open the link from a WhatsApp message on a phone, often on a weak connection.
- Branded header with logo, name, address and contact, then a sticky search bar and category chips.
- Responsive card grid: 2 columns on phones, 3 to 4 on larger screens. Each card has the image (lazy-loaded), name, category tag, a prominent base price, and the other variants listed compactly (expandable if there are many).
- Clean pagination controls, skeleton or empty states, and a footer with the branch details and a short note: **"Prices and availability may change. Please confirm at the shop."**
- Follow the app's existing Tailwind design language (including dark-mode support if the rest of the app uses it) so it feels like part of the product. Optional small "Powered by" line using the platform's configured company name (dynamic, never hardcoded).
- **Link previews:** set Open Graph and meta tags (title: branch name + "Price List", short description, image: branch logo) in the **server-rendered HTML**, so the link previews nicely when pasted into WhatsApp.
- Images: serve appropriately sized versions, lazy-load them, and never expose private storage paths.

---

## 10. Performance & Abuse Protection

- **Cache** each query result per branch and parameter set for a short TTL (default **60 seconds**, a named constant). Prices and availability may therefore lag by up to a minute, which is acceptable.
- **Rate-limit** by IP (default **60 requests per minute**, a named constant), with a friendly "too many requests" page.
- Select only the needed columns, paginate at the database level, and use existing indexes (branch, name, category).
- Read-only access only. The page must not trigger sync, calculations, or writes.
- A shared link can suddenly bring many visitors. The page must never be able to slow down checkout at the branch.

---

## 11. Security & Privacy

- **Never pass Eloquent models (or full arrays of them) to the view or to frontend props.** Build the response through an explicit whitelist transformer that outputs only the fields in §5. If this page uses Inertia/Vue, remember that every prop is visible in the page source, so a model passed through would leak cost and wholesale prices.
- Escape all item and branch text (names may contain unexpected characters).
- **Tenant isolation:** the page resolves the branch from the URL and queries only that branch. One branch's slug must never surface another branch's items.
- Send `noindex` (meta tag and `X-Robots-Tag` header) so prices are not crawled by search engines. This is a fixed decision, not a setting.
- No cookies, tracking or analytics are required for this page.

---

## 12. Technical Approach & Code Organization

- **Recommended: a lightweight server-rendered page** (for example a Blade view with minimal JavaScript) rather than the app's Vue/Inertia bundle. It loads faster on weak phones, renders Open Graph tags that WhatsApp can read (link-preview crawlers do not run JavaScript), keeps the POS bundle and service worker out of the picture, and reduces data-leak risk. Another approach is acceptable only if the head/meta tags are server-rendered and no extra data is exposed.
- Keep it **self-contained**, following the project's module pattern (for example `app/Modules/PublicCatalog/`: routes, controller, query service, response transformer, views, config).
- The only edits to existing code: the new branch settings section (toggle, link, copy button), the per-item hide checkbox on the item form, and the reserved-slug list.

---

## 13. Data / Migration

- New migration(s) only, never edit old ones.
- A branch-level `public_catalog_enabled` flag, default **false**, using the existing mechanism for branch toggles if there is one.
- An items-level `hide_from_public_catalog` boolean, default **false**.
- Existing branches and items behave exactly as before (nothing is public until a branch turns it on).

---

## 14. What Must NOT Change

- The POS selling page, cart, pricing, discounts (item/group/overall), tax, stock logic, expiry blocking, IMEI handling, held sales, till sessions.
- The offline item cache, offline sync and the service worker.
- The ecommerce integration, including its reservations.
- No write endpoints are added anywhere as part of this feature.

---

## 15. Out of Scope

Ordering, cart or checkout, payments, customer accounts, wholesale pricing, showing stock counts, showing barcodes, individual product detail pages, an organization-wide combined catalog across branches, showing discount prices (the page shows the **regular selling price only**), any tax note on the page, a WhatsApp/contact button or new contact fields, hiding by whole category, and plan gating (not gated for now).

---

## 16. Testing Checklist

- Toggle on and off: the page appears and disappears immediately (not delayed by cache). Disabled branch and unknown slug show the same generic page.
- Only eligible items appear (front-store stock only, price above zero, not hidden, not expired). Variants appear only when stock covers them.
- View the page source and any network responses: no cost price, wholesale price, stock numbers, barcodes, IMEIs, batch data or internal IDs.
- Search, category filter, sort and pagination work in combination, including boundary pages and empty results. URLs are shareable and reload to the same view.
- Items with long names, special characters, no image, and no category display correctly (and text is escaped).
- Slug A never shows branch B's items.
- Behavior under load: rate limiting works, caching works, and checkout at the branch is unaffected.
- WhatsApp link preview shows the branch name and logo.
- QR code: scanning it with a phone camera opens the catalog; PNG download and the print view work; the QR updates after a slug change; no external QR service is called.
- The page shows regular selling prices only, with no tax note and no discount prices.
- A staff device with the installed POS PWA opens the link and sees the catalog, not the POS app.
- Mobile layouts on small phones, and a slow-connection test.
- Regression: the POS selling page, the offline cache and item forms behave as before.

---

## 17. Resolved Decisions (For Reference)

1. **Discounts:** show the regular selling price only.
2. **Tax:** no tax note on the page.
3. **Images:** items support images; items without one show a placeholder.
4. **Out-of-stock items:** hidden.
5. **Search engines:** `noindex`, fixed.
6. **Link:** pretty slug-based link.
7. **Contact options:** no WhatsApp button, no new contact fields.
8. **QR code:** included (§4.1).
9. **Hiding:** per-item only.
10. **Plan gating:** not gated for now.

## 18. Open Questions

None outstanding. Raise any new questions found during Step 0 (§3) before building.
