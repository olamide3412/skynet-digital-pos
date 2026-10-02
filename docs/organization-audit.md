# Organization Layer Audit & Safety Net Report (Phase 0)

> Generated as part of **Phase 0** of the Organization Layer (Client Accounts) Integration.  
> Reference document: `agent_md_files/AGENT_README_ORGANIZATION_LAYER.md`.  
> Status: **Phase 0 Complete — No Schema or Behavior Changes**.

---

## 1. Verified Live Facts & Database State

The live database was queried to verify key structural assumptions before making any schema or code changes:

### 1.1 Branch Inventory
| Branch ID | Name | Slug | Status |
|:---:|:---|:---|:---:|
| **1** | Felix Enterprise | `felix-enterprise` | Active |
| **2** | Skynet Digital Hub | `skynet-didtal-hub` | Active |

**Result:** Exactly **two branches** exist, representing two separate client accounts with one branch each. No unexpected or orphaned branches exist.

### 1.2 Platform Super Admin Accounts
| User ID | Name | Email | Branch ID | `is_super_admin` | `is_active` | Account Ownership |
|:---:|:---|:---|:---:|:---:|:---:|:---|
| **3** | Super Admin | `superadmin@skynetpos.com` | `NULL` | `1` | `1` | **Skynet Digital (Platform Operator)** |

**Result:** Exactly **one** Super Admin user exists (`superadmin@skynetpos.com`), and it is confirmed to be Skynet Digital's platform administrator account. No client accounts hold the `is_super_admin` flag.

### 1.3 Reserved Slug Collision Audit
All existing branch slugs were evaluated against planned routing prefixes and system-reserved slugs:
- **Planned / Reserved Slugs:** `org`, `organization`, `organizations`, `platform`, `admin`, `super-admin`, `superadmin`, `api`, `login`, `logout`, `register`, `dashboard`, `settings`, `features`, `industries`, `pricing`, `terms`, `privacy`, `security`, `pos`, `sw.js`, `manifest.json`.
- **Existing Slugs:** `felix-enterprise`, `skynet-didtal-hub`.
- **Collision Check:** **ZERO collisions detected**.
- **Action for Phase 3:** Validation on `branches.slug` in `BranchController` will enforce rejection of all reserved keywords.

---

## 2. Comprehensive Component Audit & Classification

Each component across the platform that touches Super Admin, branches, or tenant scoping has been audited and categorized into one of three classifications:
1. **[Platform-only]**: Stays with Skynet Digital (Platform Super Admin). Never delegated to clients.
2. **[Organization-scoped later]**: Multi-branch aggregation or delegation for Organization Admins (Phases 3, 4, 6).
3. **[Branch-scoped (unchanged)]**: Operates strictly within a single branch context (`branch_id`). Tenancy rules remain untouched.

---

### 2.1 Middleware

| File | Purpose & Scoping Logic | Classification | Target in Target Hierarchy |
|---|---|---|---|
| `app/Http/Middleware/SuperAdminMiddleware.php` | Validates `$user->isSuperAdmin()`. Redirects or aborts 403. | **Platform-only** | Unchanged. Guards `/superadmin/*`. |
| `app/Http/Middleware/BranchScopeMiddleware.php` | Sets Spatie permissions team to `branch_id`. Bypasses check for `$user->isSuperAdmin()`; enforces `$user->branch_id === $branch->id` for staff. | **Branch-scoped (unchanged)** & **Platform-only** bypass | Unchanged. In Phase 6 (if Org Admins enter branches), actions will be logged and scoped. |
| `app/Http/Middleware/ResolveBranchMiddleware.php` | Resolves branch from `{branch}` route parameter and binds `current_branch`. | **Branch-scoped (unchanged)** | Unchanged. |
| `app/Http/Middleware/PosRoleMiddleware.php` | Validates Spatie permissions via `hasPermissionTo()`. | **Branch-scoped (unchanged)** | Unchanged. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Injects `current_branch`, fallback `default_branch` (`Branch::orderBy('id')->first()`), and `pos_permissions`. | **Branch-scoped (unchanged)** / **Platform-only** | Default branch fallback remains Platform/Public only; Org Dashboard will provide scoped navigation. |
| `app/Http/Middleware/AdminMiddleware.php` | Legacy check for ecommerce admin roles. | **Platform-only** | Legacy / unchanged. |

---

### 2.2 Policies, Gates & RBAC

| File | Current Implementation | Classification | Notes for Organization Layer |
|---|---|---|---|
| `app/Providers/AuthServiceProvider.php` | `Gate::define('admin')`, `Gate::define('super_admin')` checking `RoleEnums::SuperAdministrator`. | **Platform-only** | Remains Platform-only. |
| `app/Services/RoleService.php` | `RoleService::can()` returns `true` immediately if `$user->isSuperAdmin()`. Sets Spatie team context to `$user->branch_id`. | **Platform-only** bypass / **Branch-scoped (unchanged)** | In Phase 3, the `organization_admin` role will be introduced with 0 Spatie branch permissions. |
| `config/permission.php` | Spatie Teams enabled (`teams => true`), `team_foreign_key => 'team_id'` (mapped to `branch_id`). | **Branch-scoped (unchanged)** | Branch permissions remain partitioned per branch. |

---

### 2.3 Route Groups & URL Prefix Structure

| Route Prefix | Controller / Handler Scope | Classification | Phase Impact |
|---|---|---|---|
| `/superadmin/*` | Full system control: branches, users, settings, exports, global catalog, broadcasts, pricing plans. | **Platform-only** | In Phase 3, gains `/superadmin/organizations/*` behind `ORGANIZATIONS_ENABLED`. |
| `/superadmin/ai/*` | Global AI settings, cross-branch reorder predictions, cross-branch AI chat assistant. | **Platform-only** | Retains global view. |
| `/{branch}/*` | All daily POS operations: checkout, sales, returns, inventory, shifts, customers, reports. | **Branch-scoped (unchanged)** | Guaranteed completely isolated. Never nested under `/org`. |
| `/{branch}/ai/*` | Branch-specific reorder predictions and AI chat assistant. | **Branch-scoped (unchanged)** | Remains isolated to current branch. |
| `/org/*` or `/organization/*` | Future Organization Admin portal. | **Organization-scoped later** (Phase 4) | Built behind `ORGANIZATIONS_ENABLED`. Will not collide with branch slugs. |
| Public / Storefront (`/`, `/pricing`, `/login`) | Marketing homepage, dynamic pricing plans, global login redirect. | **Platform-only** / Public | Unchanged. |

---

### 2.4 Database Seeders

| Seeder | Current Data Seeded | Classification | Notes |
|---|---|---|---|
| `database/seeders/SuperAdminSeeder.php` | Seeds `superadmin@skynetpos.com` with `is_super_admin = true`. | **Platform-only** | Unchanged. |
| `database/seeders/BranchSeeder.php` | Seeds initial `Felix Enterprise` branch. | **Branch-scoped (unchanged)** | In future seeders, will associate an `organization_id`. |
| `database/seeders/RolePermissionSeeder.php` | Seeds standard Spatie permissions for branch roles (`branch-admin`, `cashier`, etc.). | **Branch-scoped (unchanged)** | Unchanged. In Phase 3, `organization_admin` role added. |
| `database/seeders/PricingPlanSeeder.php` | Seeds marketing homepage pricing plans. | **Platform-only** | Unchanged. |
| `database/seeders/GlobalItemSeeder.php` | Seeds global item catalog for branches to import. | **Platform-only** | Unchanged. Shared library stays platform-owned. |

---

### 2.5 Blade & Vue Frontend Conditionals

| Component | Checks | Classification | Migration Note |
|---|---|---|---|
| `resources/js/Layouts/SuperAdminLayout.vue` | Platform Super Admin sidebar navigation links. | **Platform-only** | In Phase 3, displays "Organizations" menu item when `ORGANIZATIONS_ENABLED` is true. |
| `resources/js/Pages/AI/ChatAssistant.vue` | `isSuperAdmin = computed(() => !!page.props.auth?.user?.is_super_admin)` toggles cross-branch branch selector. | **Platform-only** / **Organization-scoped later** | Phase 4 will allow Org Admins to see their own organization's branches in the selector. |
| `resources/js/Pages/AI/ReorderPredictions.vue` | `isSuperAdmin` toggles cross-branch filter and branch columns. | **Platform-only** / **Organization-scoped later** | Scoped to organization branches in Phase 4. |
| `resources/js/Pages/Error.vue` | Redirects `is_super_admin` to `superadmin.dashboard`, otherwise branch dashboard. | **Platform-only** / **Branch-scoped** | Org Admin will redirect to Org Dashboard in Phase 4. |

---

### 2.6 API Endpoints & Exports

| Endpoint | Controller / Method | Classification | Notes |
|---|---|---|---|
| `superadmin/branches/{branch}/export-items` | `BranchController@exportItems` | **Platform-only** | Platform export for given branch. |
| `superadmin/branches/{branch}/export-sales` | `BranchController@exportSales` | **Platform-only** | Platform export for given branch. |
| `superadmin/branches/{branch}/export-logs` | `BranchController@exportLogs` | **Platform-only** | Platform export for given branch. |
| `superadmin/logs/export` | `ActivityLogController@export` | **Platform-only** | Global system audit log export. |
| `superadmin/reports/expenses/export` | `CrossBranchExpenseController@export` | **Platform-only** | Cross-branch expenses. Scoped version in Phase 4. |
| `/{branch}/api/*` | POS API: items, imeis, customers, held sales, offline sync. | **Branch-scoped (unchanged)** | Completely branch-isolated. Zero changes needed. |

---

### 2.7 Queued Jobs & Scheduled Console Commands

| Command / Job | File | Classification | Details |
|---|---|---|---|
| `app/Console/Commands/BackfillItemUnitsCommand.php` | Maintenance utility for units. | **Platform-only** | Maintenance. |
| `app/Console/Commands/ImportItemsCommand.php` | CLI tool for importing item catalogs. | **Platform-only** | CLI utility. |
| `app/Console/Commands/SeedFeatureSettings.php` | Seeds branch feature settings. | **Platform-only** | Maintenance. |
| `routes/console.php` (`inspire`) | Laravel default. | N/A | No queued scheduled commands currently iterating cross-branch without explicit branch context. |

---

### 2.8 Notification Recipient Queries & Broadcast Targeting

| Service / Controller | Method & Query Logic | Classification | Roadmap Scoping |
|---|---|---|---|
| `App\Services\NotificationService` | `notifyConflict`, `notifyBranch`, `getUnreadCount`, `getRecentPreview`. All queries scoped by `branch_id` and optional `user_id`. | **Branch-scoped (unchanged)** | Unchanged. Stays strictly branch-scoped. |
| `App\Services\BroadcastService` | `resolveRecipients($targetType, $targetData)`: `all`, `branch`, `branches`, `role`, `branch_role`. When `all`, selects all users system-wide. | **Platform-only** | Platform broadcasts remain cross-organization. In Phase 6, if Org Admins broadcast, target users are restricted to `users where branch_id in (org branches)`. |
| `App\Http\Controllers\SuperAdmin\BroadcastController` | Manages system-wide broadcasts. | **Platform-only** | Stays Platform Super Admin only. |

---

### 2.9 AI Module Queries & Receipt / IMEI Lookup

| File | Query & Resolution Logic | Classification | Phase 4 Adaptation |
|---|---|---|---|
| `ChatAssistantController@index` | Resolves `$branch`: for non-superadmin, forces `current_branch()` or `$user->branch_id`. For superadmin, allows selecting any active branch or `null` for cross-branch. | **Platform-only** vs **Branch-scoped** | In Phase 4, Organization Admins will be restricted to selecting only branches where `organization_id == $user->organization_id`. |
| `ChatQueryTranslationService@process` | Dynamically parses query. If `$user->is_super_admin`, resolves any branch by name/slug. Otherwise strictly binds to user's branch. | **Platform-only** vs **Branch-scoped** | In Phase 4, Org Admin query resolution will filter `Branch::where('organization_id', $user->organization_id)`. |
| `ChatQueryTranslationService::resolveReceiptLookup` | Matches receipt code (`REC-...`). If `$branch` is null (Super Admin), searches cross-branch. If `$branch` provided, enforces `where('branch_id', $branch->id)`. | **Platform-only** (cross-branch) vs **Branch-scoped** | In Phase 4, Org Admin receipt lookup will use `whereIn('branch_id', $orgBranchIds)` to prevent cross-tenant receipt discovery. |
| `ChatQueryTranslationService` (IMEI Lookup) | Resolves serial/IMEI device unit. | **Branch-scoped (unchanged)** / **Platform-only** | Org Admin scoped to org branches in Phase 4. |

---

### 2.10 Mail, System & AI Provider Settings

| Feature | Location | Classification | Notes |
|---|---|---|---|
| Mail Server Configuration (`settings/mail`) | `SystemSettingController@updateMail` | **Platform-only** | Platform-level SMTP infrastructure. |
| AI Provider Settings (`superadmin/ai/settings`) | `AiSettingsController@index`, `update` | **Platform-only** | API keys (Gemini, OpenAI, Claude, DeepSeek) are platform-managed. |
| System Theme & Company Name | `SystemSettingController@update` | **Platform-only** | Global defaults. |

---

### 2.11 Homepage & Storefront Default Branch Logic

| Location | Logic | Classification | Notes |
|---|---|---|---|
| `routes/web.php` (Line 94: `GET /login`) | `Route::get('/login', fn() => redirect()->route('pos.login', ['branch' => current_branch()?->slug ?? Branch::orderBy('id')->value('slug') ?? 'skynet-digital-enterprise']))` | **Platform-only** / Fallback | Redirects directly to first branch login when root `/login` is requested. Stays untouched. |
| `HandleInertiaRequests` (Line 36) | `$defaultBranch = Branch::orderBy('id')->first()` used if no branch active. | **Branch-scoped / Fallback** | Stays untouched. |

---

## 3. Safety Net & Feature Flag Specification

To guarantee zero behavioral regression while developing subsequent phases:
- A dedicated configuration file `config/organizations.php` is created.
- The environment variable `ORGANIZATIONS_ENABLED` controls whether organization management UI and routing are active.
- Default is `false`.

```php
// config/organizations.php
return [
    'enabled' => env('ORGANIZATIONS_ENABLED', false),
    'reserved_slugs' => [
        'org',
        'organization',
        'organizations',
        'platform',
        'admin',
        'super-admin',
        'superadmin',
        'api',
        'login',
        'logout',
        'register',
        'dashboard',
        'settings',
        'features',
        'industries',
        'pricing',
        'terms',
        'terms-and-conditions',
        'privacy',
        'privacy-policy',
        'refund-policy',
        'security',
        'pos',
        'sw.js',
        'manifest.json',
    ],
];
```

---

## 4. Phase 0 Audit Conclusion & Approval Status

- **Database Verified**: Exactly 2 branches (`felix-enterprise`, `skynet-didtal-hub`) and 1 Super Admin account (`superadmin@skynetpos.com`).
- **Slug Isolation Verified**: No existing slug collides with planned routes.
- **Architectural Tenancy Verified**: `branch_id` remains the sole transactional partition key. The organization layer is cleanly situated strictly **above** branches (`branch -> organization`), preserving all existing POS and offline functionality.
- **Behavior Changes**: **NONE**.
- **Phase 0 Status**: **COMPLETED**. Safe to proceed to **Phase 1 (Additive Schema Only)** upon user confirmation.
