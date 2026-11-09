# POS System — Fix: Ecommerce Integration Screen Missing API Base URL (Agent README)

**Scope of this document:** A narrow, targeted fix on top of the already-executed `AGENT_README_ECOMMERCE_INTEGRATION_COMPLETE.md` (including the Organization Layer / plan-gating corrections already merged into it). Nothing about catalog sync, reservations, pricing, or the existing credential generation/rotation logic changes here — this fixes **one missing display field** on the Branch Ecommerce Integration screen.

---

## 1. Diagnosis

The original document specifies the integration's API routes only as abstract "conceptual endpoints" (e.g., `/api/commerce/v1/catalog`) throughout — it never once specifies that the **actual, full, copyable API base URL** should be shown to Super Admin on screen. It does specify that Client ID and API Secret are things Super Admin can "view, generate, rotate, revoke, or copy" (§36 area), but the base URL was simply never included in that list — an oversight, not a deliberate exclusion.

This matters because the companion Ecommerce Connection Fix README expects whoever sets up the ecommerce installation to be handed **three values**: POS API Base URL, Client ID, and API Secret. With the base URL missing from this screen, there's no legitimate way to complete pairing — which is exactly the symptom you're seeing (ecommerce's Store Config has nowhere to pull that value from).

---

## 2. Required Fix

On the existing **Branch Directory → Open Branch → Ecommerce Integration** screen (Super Admin only, per the existing §36/§37 rules — unchanged), add a **"Connection Details"** panel shown once a branch's ecommerce integration exists, displaying all three values needed for pairing:

| Field | Source | Notes |
|---|---|---|
| **POS API Base URL** | System-level value — see §2.1 | Same for every branch's integration on this installation; not something Super Admin configures per-branch |
| **Client ID** | Already generated per integration (existing logic) | Already correctly shown/copyable per the original document — no change |
| **API Secret** | Already generated per integration (existing logic) | Already correctly shown once at generation time — no change |

### 2.1 Where the Base URL Value Comes From
- This is **not** something Super Admin types in manually per branch — it's a fixed, system-wide value for this entire POS installation (the same API base URL serves every branch's commerce integration; the specific branch is resolved from the credential itself, not from the URL, consistent with the existing rule that branch identity is never trusted from a client-supplied value).
- Derive it from the application's own configured URL (e.g., `config('app.url')` combined with the fixed commerce API path, such as `https://pos.example.com/api/commerce/v1`) rather than hardcoding it or requiring manual entry — it should simply always be correct and automatically current.
- Display it as **read-only, with a copy-to-clipboard button**, exactly like the Client ID.

### 2.2 Presentation
- Show all three values together in one panel so Super Admin can copy each one in sequence while setting up the ecommerce side — reduce back-and-forth between screens.
- The API Secret continues to follow its existing one-time-reveal pattern (shown in full only at generation/rotation time, never retrievable again afterward) — this fix does not change that behavior, only adds the Base URL alongside it.
- Add a short inline note/tooltip near the panel (e.g., "Enter these three values in the ecommerce installation's Store Config screen to complete pairing") so it's clear what this panel is for.

---

## 3. Companion Requirement — Connection Test Endpoint

The Ecommerce Connection Fix README (§5, Open Question 2) flagged that the ecommerce side's "Save & Test Connection" step needs a lightweight endpoint on the POS side to actually verify a connection — distinct from a full catalog-sync call. Confirm/add this now, since it directly supports the pairing flow this fix enables:

- A simple authenticated **ping/identity endpoint** (e.g., `GET /api/commerce/v1/ping` or similar) that, given valid credentials, returns basic confirmation — the branch name and integration status is enough, no real data needed.
- This should already be trivial to build if the broader credential-authentication middleware from the original document is in place — this is likely just one small new route using that same middleware, not new authentication logic.

---

## 4. What This Does NOT Change

- No change to how Client ID and API Secret are generated, rotated, or revealed — that logic is already correct per the original document.
- No change to catalog sync, reservation, or pricing logic.
- No change to who can access this screen (Super Admin only, per §36/§37 — unchanged) or to the Organization-awareness and plan-gating corrections already merged into the main document.

---

## 5. Notes for the Agent

- If the commerce API's base path is already defined somewhere in the existing routing/config (per the original document's "conceptual endpoints"), reuse that exact value for display — don't introduce a second, possibly inconsistent definition of the API's base URL.
- Test explicitly: generate a new branch ecommerce integration, confirm all three values display correctly and are each copyable, then use those exact three values in the ecommerce Store Config screen (per the companion fix) and confirm "Save & Test Connection" succeeds end-to-end.
