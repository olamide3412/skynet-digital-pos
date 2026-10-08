# POS System — Camera Barcode Scanner for the Selling Page (Agent README)

**Note:** This adds a way for a cashier using a phone or tablet to scan item barcodes with the **device camera** on the POS selling page. It is **purely additive**. The camera is just another source of a barcode string. It feeds the **same existing barcode handling** that the search box and hardware scanners already use. Nothing about how items are looked up, added to the cart, priced, discounted, taxed, held, or synced changes.

**Read §2 and §3 first. They define the rule that keeps this safe on a live system.**

---

## 1. Goal

- A cashier can tap a camera button on the POS selling page, point the phone at a product barcode, and have the item added to the cart exactly as if the barcode had been typed or scanned by a hardware scanner.
- Works on phones and tablets (Android Chrome, iOS Safari, installed PWA) and on laptops with a webcam.
- Works **offline** like the rest of the checkout.
- Hardware scanners and typed barcodes continue to work exactly as before.

---

## 2. Core Rule: Camera = Another Input Source, Not a New Checkout Path

- The scanner must only **produce a barcode string** and pass it into the **existing function** the POS page already uses when a barcode is scanned or entered.
- **Do not** write a second add-to-cart path, a second item lookup, or a second barcode-matching routine.
- Everything downstream stays untouched, because it is reached through that one existing function: local item lookup (IndexedDB when offline), unit/variant handling, IMEI selection in the cart, item/group/overall discounts, tax, stock checks, expiry blocking for items using the legacy `expiry_date`, till-session tagging, and offline queueing.

---

## 3. Step 0 — Discovery Before Writing Any Code (No Changes)

Report findings before building anything:
1. Locate the existing function/handler that processes a scanned or entered barcode on the POS selling page (the one the search input and hardware scanners already trigger).
2. Locate any **global key listeners** used to detect hardware scanner input (fast keystrokes), and the **F2 focus shortcut** and focus-management logic around the search input.
3. Note how the cart behaves when the same item is scanned twice (quantity increase) and when an item is not found.
4. Confirm how the POS page currently behaves on a **phone-sized screen**. If it is not usable on a narrow screen, flag it (see Open Questions) and do **not** redesign the layout as part of this task.
5. Confirm whether a `manifest`/service worker precache list exists and how new JS chunks and `.wasm` files get included (see §7.3).

If the existing barcode handling is not a single reusable function, **stop and report** rather than duplicating its logic.

---

## 4. Feature Setting

- Add a branch-level toggle: **"Camera Barcode Scanner"** (On/Off). Branch Admin can manage it; Platform Super Admin can override, per the usual pattern.
- **Recommended default: On.** It is invisible unless a cashier taps the button, so risk is low (see Open Questions).
- The button also appears **only when the device supports it**: a secure context (HTTPS) and `navigator.mediaDevices.getUserMedia` available. Otherwise the button is hidden, not broken.
- **Not plan-gated.** The pricing sheet includes "barcode scanning" in every plan, so this is available on all plans.
- If the project already has a mechanism for branch-level toggles, reuse it. If storage needs a schema change, use a **new migration only**, never edit old ones. Existing branches should not lose or change any behavior.

---

## 5. UI / UX

### 5.1 Entry Point
- A small **camera icon button** next to the existing search/scan input on the POS selling page.
- Minimal change to the page: one button and one mounted component. No re-layout of the existing screen.

### 5.2 Scanner Overlay
Tapping the button opens a full-screen overlay (modal) containing:
- Live camera preview using the **rear camera** by default.
- A rectangular **scan guide frame** in the center.
- A compact **"Last scanned" strip** showing the most recent result (item name and "Added", or "Not found").
- Buttons: **Close/Done**, **Torch** (only if the camera supports it), **Switch camera** (only if more than one camera exists).
- A small **mode toggle**: **Continuous** (default) or **Single scan**.
  - **Continuous:** overlay stays open so the cashier can scan many items in a row.
  - **Single scan:** overlay closes after one successful scan.

### 5.3 Preventing Duplicate Adds (Important)
A camera sees the same barcode across many frames. Without protection, one scan would add the item many times.
- After a successful read, **ignore the same code for a short cooldown (default 1.5 seconds)**. A *different* code is accepted immediately.
- After the cooldown, scanning the same code again adds another unit (intentional rescan), consistent with the existing cart behavior.
- Make the cooldown a single named constant so it is easy to tune.

### 5.4 Feedback
- **Success:** short beep (Web Audio), brief vibration where supported (Android; iOS Safari does not support it, so degrade silently), and a quick visual flash plus the "Last scanned" strip update.
- **Not found:** different, clearly distinguishable feedback (different tone, error styling). **Keep the camera running.** Never crash or close the overlay.
- Sounds and vibration should be allowed to fail silently.

### 5.5 Closing
- On close, **stop the camera immediately**, return focus to the POS search input exactly as it behaved before, and leave the cart as it is.

---

## 6. Camera Permission & Error States

| Situation | Behavior |
|---|---|
| First use | Browser shows its permission prompt after the cashier taps the button (never auto-request on page load). |
| Permission denied | Friendly message explaining how to re-enable camera access for the site. The existing manual/typed barcode input remains fully usable. |
| No camera found | Message and automatically hide/disable the button on that device. |
| Not HTTPS / unsupported browser | Button hidden. No error spam. |
| Camera busy/in use by another app | Clear message with a retry option. |
| Stream drops mid-scan | Message with retry, never a frozen black screen. |

---

## 7. Technical Requirements

### 7.1 Decoding Strategy
- **Use the native `BarcodeDetector` API where available** (Chrome on Android). It is fast and efficient.
- **Fall back to a JavaScript/WASM decoding library** where it is not available (notably **iOS Safari** and some desktop browsers). Choose a maintained library that supports 1D barcodes and runs entirely in the browser (for example a ZXing-based library or a `BarcodeDetector` polyfill). Final choice is the agent's, subject to the requirements below.
- Requirements for whatever library is chosen:
  - Runs **fully on-device** with no network calls.
  - **Self-hosted** with the app bundle. **No CDN dependency at runtime** (the checkout must work offline).
  - Works on **iOS Safari** and **Android Chrome**.

### 7.2 Barcode Formats
Restrict detection to formats the system actually uses. Fewer formats means faster, more accurate scanning:
- **Code 128** (the format this system generates for its own barcodes)
- **EAN-13, EAN-8, UPC-A, UPC-E** (manufacturer retail barcodes)
- **Code 39** (optional, only if already used by any items)

### 7.3 Offline Support (Critical)
- The scanner code (and any `.wasm` decoder files) must be available **offline**. Include them in the service worker precache. **Make sure `.wasm` and any lazy-loaded JS chunks are covered by the precache patterns**, since this is a commonly missed detail that breaks offline scanning.
- **Lazy-load** the scanner code only when the cashier first opens the overlay, so the initial POS page load and splash-screen timing are not slowed. Because it is lazy-loaded, it must still be precached so it works the first time offline.
- Test the exact scenario: load the POS online once, go offline, refresh the page (per the existing offline-refresh fix), open the scanner, scan an item, and confirm it adds from locally cached data.

### 7.4 Camera & Performance
- Request the rear camera (`facingMode: environment`) with a reasonable resolution (about 1280×720 ideal). Higher is slower and rarely helps for retail barcodes.
- Request continuous autofocus where supported.
- Limit decode attempts to a sensible rate to protect battery and keep low-end phones responsive. Do not decode every single frame at full speed if it causes lag.
- Show the torch button only when the camera reports torch support (feature-detect it).
- Optional: use the Screen Wake Lock API while the overlay is open so the screen does not sleep mid-scan.

### 7.5 Lifecycle
- **Stop all camera tracks** when the overlay closes, when the app/tab goes to the background (`visibilitychange`), and when the POS page unmounts. A camera left running drains battery and blocks other apps.
- Remember the last-used camera as a small local preference, and fall back gracefully if that device is no longer available.

---

## 8. Privacy & Security

- **No video frames are recorded, stored, or uploaded.** All decoding happens on the device.
- The camera is requested only from an explicit user tap, never automatically.
- Only the decoded barcode text is passed on (to the existing barcode handler).

---

## 9. Interaction With Existing Features (All Unchanged)

| Existing feature | Expected result |
|---|---|
| Hardware barcode scanners / typed barcodes | Work exactly as before, including F2 focus and any global key listeners. The overlay must not break or permanently capture them. |
| Unit variants (item_units) | Handled by the existing handler as always. |
| IMEI-tracked items | Item is added to the cart as usual, then IMEI selection happens in the cart as already built. Scanning an IMEI itself is **not** part of this task. |
| Discounts, tax, stock checks, legacy expiry blocking | Unchanged. They run after the existing handler adds the item. |
| Held sales, till sessions, offline queue/sync | Unchanged. |
| Item not found | Same existing not-found behavior, plus the scanner's own feedback. |

---

## 10. Code Organization (Keep the Footprint Small)

- Build the scanner as a **self-contained component plus a composable** (for example a `CameraBarcodeScanner` component and a `useCameraScanner` composable), kept separate from the POS page logic.
- The POS selling page gets only: the camera button, mounting the component, and wiring its "barcode detected" event to the existing barcode handler identified in Step 0.
- No changes to cart, pricing, discount, tax, offline sync, or item lookup code.

---

## 11. Testing Checklist

**Device matrix:** Android Chrome, iOS Safari, an installed PWA on each, and a laptop webcam.

**Functional:**
- Scan an item with a Code 128 barcode (system-generated) and one with a manufacturer EAN-13: both add correctly.
- Continuous mode: scan several different items in a row. Each adds once.
- Hold one barcode steadily in view: it adds **once**, not repeatedly (cooldown works). Rescan after the cooldown adds one more unit.
- Not-found barcode: error feedback, camera keeps running.
- IMEI-tracked item: added to cart, IMEI selection works as before.
- Item with a variant/unit setup: behaves exactly as it does when typed.
- Permission denied, no camera, and camera-in-use states show the right messages.
- Torch and switch-camera appear only when supported.
- Close the overlay: camera stops (indicator light off) and focus returns to the search input.
- Switch to another app/tab while scanning: camera stops.

**Offline:** load online once, go offline, refresh, scan an item. Add it to the cart, complete a sale, reconnect, and confirm it syncs normally.

**Regression:** hardware scanner and typed barcode entry still work, F2 shortcut still works, no new console errors, POS page load time is not noticeably slower (lazy-loading confirmed).

**Practical tip for testing:** the camera requires HTTPS. A phone opening your PC at `http://192.168.x.x` will be blocked. Use an HTTPS tunnel or a locally trusted certificate for phone testing during development.

---

## 12. Explicitly Out of Scope

- Redesigning the POS selling page layout for phones.
- Scanning IMEI numbers by camera.
- Scanning QR codes or receipts.
- Image/photo-based product recognition.
- Any change to existing barcode generation or printing.

---

## 13. Open Questions

1. **Phone layout:** Is the POS selling page currently usable on a phone-sized screen? If not, camera scanning is of limited value on phones until the layout is made responsive, which would be a separate piece of work.
2. **Default for existing branches:** Should the new toggle default **On** for all existing branches (recommended, since it is additive and hidden until tapped), or Off until each branch enables it?
3. **Preferred decoder library:** Any preference, or should the agent choose the best maintained option meeting §7.1?
4. **Continuous vs single scan default:** Is Continuous the right default for cashiers (recommended), or should Single scan be the default with Continuous as an option?
