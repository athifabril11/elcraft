# Checkout & Refund Review Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all Critical/Important findings from the checkout-flow code review: webhook status guards, cart-clearing scope, HTTP-outside-transaction, refund guard edges, refund completion lock, completion email, and captured-funds visibility.

**Architecture:** Each task is an independent TDD red-green cycle on the existing Laravel 13 codebase. Fixes are localized: guards added around existing transitions, HTTP calls moved outside DB transactions (pattern already established by the abandoned-order cancel fix), one new Mailable mirroring the existing shipped-mail, and one admin filter/banner.

**Tech Stack:** Laravel 13, PHP 8.3, Midtrans Snap/Refund API, MySQL (app) / SQLite :memory: (tests), Blade + inline styles for admin views.

**Spec:** Findings from the code review of uncommitted work on HEAD `44b0cce` (review verdict: "With fixes"). Findings are restated verbatim in each task below — the task text is the binding requirement.

**Execution context:** All work stays UNCOMMITTED on `main` (user's established workflow; user explicitly forbids commits unless asked). Review packages are generated as `git diff HEAD -- <task files>` into the SDD workspace — never `BASE..HEAD` commit ranges.

## Global Constraints

- Order statuses (verbatim): `pending`, `paid`, `diproses`, `dikirim`, `selesai`, `dibatalkan`. Return statuses: `diajukan`, `disetujui`, `diproses`, `selesai`, `ditolak`. Return `refund_status`: `api`, `manual`, null. Payment statuses: `pending`, `success`, `failed`, `expired`, `refunded`.
- State-changing routes use `->patch(route(...))`; admin routes live behind the `admin` middleware.
- HTTP calls (Midtrans, RajaOngkir) must NEVER run inside `DB::transaction`. HTTP outcomes use the normalized array contract `['outcome' => string, 'status_code' => ?int, 'message' => string]`. Midtrans service methods never throw; they log and return `error`.
- Emails are synchronous (`Mail::to(...)->send(...)` inside try/catch + `Log::error`, mirroring `Admin/OrderController.php:197-210`). An email failure must never break the user-facing flow.
- TDD is mandatory per task: write the failing test first, run it to confirm RED, implement, run to confirm GREEN, then run the FULL suite `php artisan test` (baseline: **481 passed / 1598 assertions**; must stay green).
- NO git commits. NO `git add`. All changes remain uncommitted.
- Implementers never dispatch subagents (no-subagents contract); review comes from the controller after the report.
- Comment style: match the file (Indonesian comments are the codebase convention on money-critical logic); no gratuitous comments.
- Midtrans refund API-supported payment methods whitelist (exact): `credit_card`, `qris`, `gopay`, `shopeepay`, `dana`, `ovo`, `linkaja`, `kredivo`, `akulaku`.

## Review Focus

Five input classes most likely to bite a person using this software, each pinned to the task that owns it:

1. **Out-of-order webhook redelivery** (a `pending`/`deny` notification delivered *after* settlement was already processed) must never regress a `paid` order or double-deduct stock → Task 1 test.
2. **Customer adds new items to the cart after starting checkout** — those items must survive settlement of the older pending order → Task 2 test.
3. **Midtrans Snap HTTP failure/timeouts during checkout and customer cancel** must not hold DB row locks during the HTTP call, and failure must leave a consistent state (no half-created orders, cart intact) → Task 3 tests.
4. **Non-refundable payment methods (VA/retail) and refunds executed in the Midtrans dashboard** — admin must get an actionable message / auto-sync, never a dead end → Task 4 tests.
5. **Refund-type return advanced to `selesai` without refunding** (money locked forever) and **completion-email failure during customer confirm** → Tasks 5 and 6 tests.

---

### Task 1: Webhook status guards (Critical finding #1)

**Files:**
- Modify: `app/Http/Controllers/MidtransWebhookController.php:338-370` (`markAsPending`), `:372-407+` (`markAsFailed`)
- Test: `tests/Feature/MidtransWebhookTest.php` (add tests) or a new `tests/Feature/WebhookOutOfOrderTest.php` if that file's structure makes additions awkward

**Interfaces:**
- Consumes: existing `handle()` dispatch that routes `pending` → `markAsPending`, `deny|cancel|expire` → `markAsFailed`, `settlement` → `markAsPaid` (with its existing `status === 'pending'` whitelist guard at `:129`).
- Produces: no new public interface — guards only.

**Finding (verbatim):** `markAsPending`/`markAsFailed` have no order-status guard — a late/out-of-order webhook can regress a paid order, enabling double stock deduction or stranding captured money. `markAsPending` sets order+payment to `pending` unconditionally; `markAsFailed` sets `dibatalkan` unconditionally (flipping a `diproses` order to `dibatalkan` WITHOUT restoring stock — silently losing inventory).

**Required behavior:**
- `markAsPending`: apply updates ONLY when the order's current status is `pending`. Otherwise `Log::warning` (context: order_number, current status) and return without changing anything (order, payment, payment_method/response_data/expiry updates all skipped).
- `markAsFailed`: apply updates ONLY when the order's current status is `pending` or `paid`. Keep the existing `paid` → restore-stock → `dibatalkan` branch exactly as-is (merchant-cancel coverage is deliberate). For any other status (`diproses`, `dikirim`, `selesai`, `dibatalkan`): `Log::warning` and skip — order AND payment untouched.

- [ ] **Step 1: Write the failing tests**
  - `test_pending_notification_after_settlement_is_ignored`: settle an order (stock deducted, payment `success`), then POST a `pending` webhook for the same order → order still `paid`, payment still `success`, stock unchanged.
  - `test_cancel_notification_on_processing_order_is_ignored`: order `diproses`, payment `success` → POST `cancel` webhook → order still `diproses`, payment still `success`, stock unchanged.
  - `test_cancel_notification_on_pending_order_still_cancels` (behavior preservation): order `pending` → POST `cancel` → order `dibatalkan`, payment `failed`.
- [ ] **Step 2: Run tests to verify RED** — `php artisan test --filter=<new tests>`; first two FAIL, third PASSES (pre-existing behavior).
- [ ] **Step 3: Implement guards** in `markAsPending` and `markAsFailed` per Required behavior.
- [ ] **Step 4: Run new tests → GREEN, then full suite** — `php artisan test` all green (481+ passing).

---

### Task 2: Cart-clearing scope (Important finding #5)

**Files:**
- Modify: `app/Http/Controllers/MidtransWebhookController.php:138-148` (remove cart-clearing block from `markAsPaid`), `app/Http/Controllers/CheckoutController.php:471-482` (`finish()` — remove cart clearing, keep view render)
- Test: `tests/Feature/WebhookCartClearingTest.php`

**Interfaces:**
- Consumes: cart is already emptied at Snap-token creation (`CheckoutController.php:377-380`, inside the checkout transaction — rollback restores cart on failure). Task 3 will later move that clear to just after the Snap HTTP call; this task does NOT touch it.
- Produces: invariant "cart is cleared exactly once, at successful Snap-token creation" — Task 3 must preserve it.

**Finding (verbatim):** Cart-clearing at webhook/finish is now redundant and can destroy unrelated cart items: items the customer added and selected after starting checkout are silently wiped when an older pending order settles.

**Required behavior:**
- Remove the `markAsPaid` cart-clearing block (`$cart = $order->user?->cart; if ($cart) { $cart->items()->delete(); }` and its comment) — settlement must not touch the cart at all.
- `finish()` must stop deleting cart items; it still renders `checkout.finish` with `order_id`/`status` inputs.
- Token-creation clearing at `:377-380` stays (Task 3 relocates it later; not this task).

- [ ] **Step 1: Write the failing test(s)** in `tests/Feature/WebhookCartClearingTest.php`
  - `test_settlement_preserves_cart_items_added_after_checkout`: user has an order `pending` (cart for it already cleared at token creation — simulate by deleting those rows), customer then adds a NEW item to cart → POST settlement webhook → new cart item STILL EXISTS after webhook.
  - Evolve the existing test at `:143` (codifies delete-all) to assert the new invariant: settlement does not delete cart items. If the existing test's setup creates the order directly without going through checkout, adapt it so its assertions describe: "webhook does not touch cart".
  - If `finish` route has a test asserting cart clearing, evolve it the same way (`finish` renders, cart untouched).
- [ ] **Step 2: RED** — `php artisan test --filter=WebhookCartClearingTest` (new assertions fail).
- [ ] **Step 3: Implement** — delete the two clearing blocks.
- [ ] **Step 4: GREEN + full suite** — `php artisan test` all green.

---

### Task 3: Move Snap HTTP + cancel HTTP outside DB transactions (Important finding #6)

**Files:**
- Modify: `app/Http/Controllers/CheckoutController.php` (`processPayment` — the `createSnapToken` call at `:363`, `Payment::create` at `:367-375`, cart clear at `:377-380`, transaction opened ~`:214`)
- Modify: `app/Http/Controllers/OrderController.php:58-113` (`cancel` — `cancelTransaction` HTTP at `:77` runs inside `lockForUpdate` transaction `:66-98`)
- Test: `tests/Feature/CheckoutTest.php` (snap-token tests), existing customer cancel tests (locate via `--filter=cancel` / grep)

**Interfaces:**
- Consumes: `MidtransService::createSnapToken(array $orderData, array $customer): array{token:string,...}` (HTTP, timeout 30 — never throws? VERIFY: it is wrapped by caller's try/catch today; keep the caller's catch); `MidtransService::cancelTransaction(string $orderNumber): array{outcome,status_code,message}` (never throws).
- Produces: response contract unchanged — `{'snap_token': ..., 'order_id': ...}` JSON on success; `500 {'success':false,...}` on Snap failure. Customer cancel redirect contract unchanged (`success`/`error` flash strings identical).

**Finding (verbatim):** `createSnapToken()` (HTTP, timeout 30) runs INSIDE the checkout DB transaction, holding row locks up to 30s. `OrderController::cancel` runs `cancelTransaction()` (timeout 8) inside a `lockForUpdate` transaction — exactly the deadlock class that the abandoned-order fix (Plan B) eliminated in checkout.

**Required behavior:**

*Checkout (`processPayment`):*
1. **Txn A:** create order + order items only. Commit.
2. **HTTP:** `createSnapToken(...)` outside any transaction.
3. **On Snap success → Txn B (short):** create `Payment` row (same fields as today, with `snap_token`, `expired_at` now+24h), clear cart items (moved from `:377-380` — this is the "cart cleared exactly once" invariant from Task 2), commit → return JSON.
4. **On Snap failure (exception):** outside any transaction, mark the order `dibatalkan` (it exists now — today's rollback deleted it; leaving it `pending` forever would strand it as an abandoned order, which the abandoned-cancel logic would later clean up, but explicit cancel is cleaner and immediate). Cart must remain INTACT (clearing only happens in Txn B). Then rethrow/return the existing 500 JSON error response.
   - Ruling: order is created then locally cancelled on Snap failure — no `dibatalkan` order may keep a `pending` payment: set payment `failed` if the Payment row exists (it won't under this design — Payment is created in Txn B — so just the order update; verify no payment row exists).
   - Keep `midtrans_order_id`/`expired_at` semantics identical to today for the success path.

*Customer cancel (`OrderController::cancel`):*
1. **Txn A:** lock, verify `status === 'pending'` (else return `not_pending` signal), commit — release lock.
2. **HTTP:** `cancelTransaction($order->order_number)` outside any transaction.
3. **Outcome `cancelled|not_found` → Txn B:** lock, RECHECK `status === 'pending'` (if no longer pending → treat as `not_pending`), update order `dibatalkan` + payment `failed`, commit → `cancelled` redirect.
4. **Outcome `cannot_modify|error`:** no local change → existing `blocked` redirect (keep exact flash message).
   - Mirrors the abandoned-order pattern in `CheckoutController.php:192-211`.

- [ ] **Step 1: Write/evolve failing tests**
  - `test_snap_http_failure_cancels_order_and_keeps_cart`: mock `createSnapToken` to throw → POST checkout → 500 JSON, order exists with status `dibatalkan`, NO payment row, cart items still present. (Today: order doesn't exist + cart intact — so this is RED on the order-status assertion.)
  - `test_cancel_http_called_outside_transaction`: behavioral parity test — existing cancel tests must keep passing unchanged (mock `cancelTransaction` outcomes `cancelled`/`cannot_modify`/`error` if not already covered). If the suite already covers outcomes, no new test needed beyond keeping them green — but ADD one regression: cancel where `cancelTransaction` throws (mock `->andThrow(new \RuntimeException)`) → customer gets error flash, order stays `pending` (today: 500/exception bubbles). RED if unhandled.
- [ ] **Step 2: RED** — run the new/changed tests, confirm failures.
- [ ] **Step 3: Implement** both restructures per Required behavior.
- [ ] **Step 4: GREEN + full suite** — `php artisan test` all green. Pay special attention to `CheckoutTest`, webhook tests, and any cancel tests.

---

### Task 4: Refund guard edges — method whitelist + dashboard-refund sync (Important findings #2, #4)

**Files:**
- Modify: `app/Services/RefundService.php` (`guard()` at `:127-151`, `processApiRefund()`, `markManual()`, persistence helper)
- Test: `tests/Feature/Admin/AdminRefundFlowTest.php`, `tests/Feature/Admin/RefundTest.php`

**Interfaces:**
- Consumes: `payments.payment_method` (populated from checkout request and webhook `payment_type`); existing guard contract `processApiRefund(ReturnRequest): array{success:bool,message:string}`, `markManual(ReturnRequest, string $notes): array{success:bool,message:string}`.
- Produces: same return shapes; new sync behavior (below).

**Findings (verbatim):**
- #2: "Supported methods" constraint not enforced — Midtrans Refund API does NOT support VA/retail (bca_va, bni_va, bri_va, mandiri_va, echannel, indomaret, alfamart, etc.); admin gets a generic "Gagal menghubungi Midtrans" instead of "method not refundable via API — use Refund Manual".
- #4: Guard treats an already-refunded payment as an error — if the admin refunds in the Midtrans dashboard while the return is still `diajukan`, then approves it, BOTH "Proses Refund" and "Tandai Refund Manual" hard-fail with "Pembayaran pesanan tidak dalam status berhasil", yet the view still shows the buttons.

**Required behavior:**

*#2 — method whitelist guard (in `guard()`):*
- Whitelist (exact, from Global Constraints): `credit_card, qris, gopay, shopeepay, dana, ovo, linkaja, kredivo, akulaku`.
- If `payment->payment_method` (fall back to `payment_type` if `payment_method` empty) is NOT in the whitelist — including empty/unknown — block with success=false and message: `Metode pembayaran "<method>" tidak didukung Refund API — gunakan tombol Refund Manual.` (use `unknown` when method is empty). No HTTP call for the API path.
- `markManual` is NOT blocked by this guard (manual refund works for any method).

*#4 — sync instead of error:*
- In BOTH `processApiRefund` and `markManual`, BEFORE the status/success checks: if `payment->status === 'refunded'` and `$return->refund_status === null` → do NOT error. Persist a sync (lockForUpdate + recheck, same persistence pattern): `refund_status = 'api'`, `refunded_at = now()`, return `status = 'selesai'` (it was refunded through Midtrans — even for the manual button path, since we cannot know who did it; dashboard refunds arrive via webhook as `api`). Return success with message: `Pembayaran sudah direfund melalui Midtrans — status retur disinkronkan.` No HTTP call.
- If payment is `refunded` and `refund_status` already set → existing double-refund block still applies.

- [ ] **Step 1: Write the failing tests**
  - `test_api_refund_blocked_for_unsupported_payment_method`: approved refund return, payment `success` with `payment_method = 'bca_va'` → POST `admin.returns.refund` → error flash containing `tidak didukung Refund API` + `Refund Manual`, return `refund_status` null, `Http::assertNothingSent()`.
  - `test_api_refund_allowed_for_supported_method_smoke`: payment `payment_method = 'credit_card'` → refund → behaves exactly as the existing success test (adapt an existing fixture to set `payment_method` so the new guard doesn't break it — RED first shows it breaking if fixtures lack the field).
  - `test_dashboard_refund_then_approve_syncs_return`: payment `refunded`, return `disetujui`, `refund_status` null → POST `admin.returns.refund` → success flash `disinkronkan`, return `selesai` + `refund_status=api` + `refunded_at` set, no HTTP.
  - Same for manual button: `test_manual_refund_button_syncs_when_payment_already_refunded` → PATCH `refund-manual` with notes → success, return `selesai` + `refund_status=api`, no HTTP.
  - **Fixtures:** audit existing refund tests for payments without `payment_method` — set `payment_method = 'credit_card'` (or `gopay`) in fixtures; the existing tests define the expected behavior they must keep.
- [ ] **Step 2: RED** — run new tests + existing refund tests; new tests fail, one or more existing may fail on the whitelist (that failure IS the fixture gap — fix fixtures in Step 1).
- [ ] **Step 3: Implement** whitelist guard + sync branch per Required behavior.
- [ ] **Step 4: GREEN + full suite** — `php artisan test` all green.

---

### Task 5: Block refund return advance to `selesai` before refunding (Important finding #3)

**Files:**
- Modify: `app/Http/Controllers/Admin/ReturnController.php:156-185` (`advanceStatus`)
- Test: `tests/Feature/Admin/AdminRefundFlowTest.php`

**Interfaces:**
- Consumes: `returns.refund_status` (migration, exists), guard in `RefundService::guard` requiring status `disetujui|diproses` for refund execution.
- Produces: signal-based rejection pattern already used by `advanceStatus` (`null` → error flash); new rejection message.

**Finding (verbatim):** `advanceStatus` lets `disetujui → diproses → selesai` regardless of `resolution_type`, while refund execution requires status `disetujui|diproses`. An admin who advances a refund return to `selesai` first can never execute OR record the refund — approved customer money with no path to pay it back.

**Required behavior:**
- Inside the locked transaction: if `$locked->resolution_type === 'refund'` AND `$locked->refund_status === null` AND `$next === 'selesai'` → do NOT update; return a distinct rejection signal.
- Redirect with error flash: `Retur ini berjenis refund — proses refund (API atau manual) selebih dulu sebelum menandai selesai.`
- `disetujui → diproses` stays allowed. `tukar_barang` returns unaffected. After refund (`refund_status` set), `diproses → selesai` allowed.

- [ ] **Step 1: Write the failing tests**
  - `test_refund_return_cannot_advance_to_selesai_before_refund`: return `diproses`, `resolution_type=refund`, `refund_status=null` → PATCH `admin.returns.advance` → error flash contains `proses refund`, status still `diproses`.
  - `test_refund_return_can_advance_after_refund`: same but `refund_status='api'` → advance → `selesai`.
  - `test_tukar_barang_return_can_advance_normally`: `tukar_barang`, `diproses` → advance → `selesai` (guard doesn't leak).
- [ ] **Step 2: RED** → **Step 3: Implement** the in-transaction condition + flash → **Step 4: GREEN + full suite.**

---

### Task 6: Order-completed email (Important finding #7)

**Files:**
- Create: `app/Mail/OrderCompletedMail.php` (mirror `app/Mail/orderShippedMail` structure exactly: readonly `Order $order`, `envelope()` subject `"Pesanan #{$this->order->order_number} Selesai - el Craft"`, `content()` view `emails.order-completed`, empty `attachments()`)
- Create: `resources/views/emails/order-completed.blade.php` (mirror `resources/views/emails/order-shipped.blade.php` — same layout/branding; adapt copy: pesanan telah selesai/diterima, terima kasih, invite review)
- Modify: `app/Http/Controllers/OrderController.php:324-352` (`confirmReceived`)
- Modify: `app/Console/Commands/AutoCompleteDeliveredOrders.php` (loop after each successful transaction)
- Test: new `tests/Feature/OrderCompletedEmailTest.php` (or add to existing order tests)

**Interfaces:**
- Consumes: `OrderShippedMail` pattern; email-send pattern `Admin/OrderController.php:197-210` (try/catch + `Log::error` with order context + `$emailNote`-style fallback).
- Produces: `OrderCompletedMail(App\Models\Order $order)` — used by both call sites.

**Finding (verbatim):** No email when an order reaches `selesai` — customer confirm sends no mail; auto-complete sends no mail; there is no completed-mail class. Owner's explicit expectation: "jika admin sudah konfirmasi pesanan, harus ada email klo pesanannya di terima."

**Required behavior:**
- `confirmReceived`: AFTER the DB transaction succeeds (and before/at the success redirect), if `$order->user?->email` → try { `Mail::to(...)->send(new OrderCompletedMail($order))` } catch { `Log::error` with order context }. Email failure never changes the redirect/flash outcome. (Load relations the email view needs — mirror how `advanceStatus` does `$order->load([...])`.)
- `AutoCompleteDeliveredOrders`: after each order's successful transaction, same try/catch + `Log::error`; a failed email must not abort the loop (remaining candidates still processed).

- [ ] **Step 1: Write the failing tests** (`Mail::fake()`)
  - `test_customer_confirm_received_sends_completion_email`: order `dikirim`, `Mail::fake()` → PATCH confirm route → `Mail::assertSent(OrderCompletedMail, fn($m) => $m->hasTo($userEmail) && $m->order->id === $order->id)`, order `selesai`.
  - `test_auto_complete_sends_completion_email`: order `dikirim` with `updated_at` older than threshold → run `orders:auto-complete` → `Mail::assertSent(OrderCompletedMail)`.
  - `test_email_failure_does_not_break_confirm`: `Mail::shouldReceive('to')->andThrow(...)` (or swap a mailer that throws) → confirm still redirects with success flash and order still `selesai`. If mocking the facade pattern in this codebase is awkward, assert try/catch is present by behavior: skip this test and note it — but attempt it first (mirror any existing mail-failure tests, e.g. grep for `OrderShippedMail` failure tests).
- [ ] **Step 2: RED** → **Step 3: Implement** mail class, view, both call sites → **Step 4: GREEN + full suite.**

---

### Task 7: Captured-funds visibility (Important finding #8)

**Files:**
- Modify: `app/Http/Controllers/Admin/OrderController.php` (`index` at `:32-66` — add `captured` filter support)
- Modify: `app/Http/Controllers/Admin/DashboardController.php` (add captured-funds count + flash/banner data)
- Modify: `resources/views/admin/orders/index.blade.php` (filter chip), `resources/views/admin/dashboard.blade.php` (alert banner linking to filtered index)
- Test: `tests/Feature/Admin/` order-index tests + dashboard tests (locate existing: grep for `admin.orders.index` tests)

**Interfaces:**
- Consumes: `orders.status = 'dibatalkan'` + `payments.status = 'success'` = captured-but-unrefunded; existing admin index filter query params (VERIFY how existing filters read query params and mirror exactly).
- Produces: query param `?payment_captured=1` on `admin.orders.index` returning only orders where `status='dibatalkan'` AND payment `status='success'`.

**Finding (verbatim):** After a failed auto-refund, the only trace is `payments.status = success` on a `dibatalkan` order, visible only if an admin opens that specific order's detail modal. No dashboard tile, filter, or report row flags it.

**Required behavior:**
- `admin.orders.index`: when `payment_captured=1`, constrain to `orders.status='dibatalkan'` + `whereHas('payment', status='success')`; the filter chip shows active state.
- Dashboard: compute `capturedStuckCount` (same condition). When > 0, render an alert banner (rose/amber, matching existing dashboard banner styles — inspect first) with count + link `route('admin.orders.index', ['payment_captured' => 1])`, text e.g. `Pesanan batal dengan dana tertangkap menunggu refund`.
- No effect on revenue stats (do NOT count these in `totalRevenue`, which reads `selesai` only — verify untouched).

- [ ] **Step 1: Write the failing tests**
  - `test_orders_index_captured_filter`: create (a) `dibatalkan` order + payment `success`, (b) `dibatalkan` + payment `failed`, (c) `paid` + payment `success` → GET `admin.orders.index?payment_captured=1` as admin → sees (a), does not see (b)/(c).
  - `test_dashboard_shows_captured_funds_banner`: with (a) existing → GET dashboard → assertSee banner text + filtered link; without → assertDontSee.
- [ ] **Step 2: RED** → **Step 3: Implement** filter + banner → **Step 4: GREEN + full suite.**

---

## Post-plan verification

- [ ] Full suite `php artisan test` green (expect 481 + new tests).
- [ ] `php artisan migrate` already applied on dev DB (no new migrations in this plan).
- [ ] Final whole-branch review (dispatch per subagent-driven-development).
