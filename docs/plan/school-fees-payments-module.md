# School Fees & Payment Management — Implementation Plan

Status: **Phase 1 delivered** (§7) · Target repos: `cyfamod-sms-server` (Laravel 12), `cyfamod-sms-web` (Next.js 16), `cyfamod-sms-app` (Expo)

> **Phase 1 notes.** Built as planned, with three deliberate departures:
> `student_bill_items` and `fee_adjustments` carry a denormalised `school_id`
> (immutable, and it saves a join on every report); `finance_audit_logs` also
> stores `actor_name`, so an audit row still reads correctly after a staff
> member is deleted; and tests build their fixtures inline through
> `Tests\Support\FeesFixture` rather than model factories, because this repo
> has factories for only three models and inline creation is its convention.
> The bulk placement lookup was added to the existing
> `StudentSessionPlacementResolver` rather than duplicated.

---

## 1. What already exists (and what it does not do)

A partial "fees" slice was scaffolded earlier and is **still unlinked from the
admin sidebar** — `components/layout/Sidebar.tsx` has no Finance section, so
`/v23/fee-structure` and `/v23/bank-details` are orphaned pages.

| Artefact | State | Verdict |
| --- | --- | --- |
| `fee_items` table + `FeeItem` + `FeeItemController` | Working CRUD, per-school catalog | **Keep as-is** (add permission checks) |
| `fee_structures` table + `FeeStructureController` | Class-scoped only (`class_id`+`session_id`+`term_id`+`fee_item_id`), has `copy`, `getTotal`, `getBySessionTerm` | **Evolve** — needs school/arm/student scopes |
| `bank_details` table + `BankDetailController` | Multi-account, `is_default`, CRUD + `setDefault`/`getDefault` | **Keep**, add a student-facing read endpoint |
| `fee_payments` table + `FeePayment` model | Legacy aggregate (`amount_paid`/`amount_due`/`status`) from the original 2024 schema. **Zero references** outside model relations | **Retire** — replaced by `payments` |
| `finance.*` permission keys | Already in `FrontendPermissionSeeder`, `RbacService`, web `permissionKeys.ts`/`permissionCatalog.ts` — but bank/fee-item/fee-structure only | **Extend** |
| `audit_logs` table | Exists, `user_id`+`action`+`description`, **completely unused**, FK to `users` (cannot record a student actor) | **Do not reuse** — see §2.9 |

Nothing in the repo covers bills, payment submission, evidence, verification,
allocation, receipts, or finance reporting. Those are all net-new.

### Platform facts the plan is built on

- Multi-tenant by `school_id`; **UUID primary keys** everywhere; MariaDB in
  prod/CI (so **no partial unique indexes** — see the `assignment_key` trick in §2.2).
- Two auth guards: `auth:sanctum` (admin/staff `User`) and `auth:student`
  (`Student` model, admission-no login). Parents ride the student guard.
- Class hierarchy: `classes` → `SchoolClass` ("JSS 2"), `class_arms` → `ClassArm`
  ("JSS 2A"), `class_sections`. Students carry `school_class_id`, `class_arm_id`,
  `class_section_id`; `student_enrollments` records the per-session/term placement.
  Note the naming inconsistency: `fee_structures.class_id` should be
  `school_class_id` to match the rest of the codebase.
- Permission enforcement is `Controller::ensurePermission()`, gated behind the
  `features.enforce_endpoint_permissions` config flag, with admin/super_admin as
  a full-access override. The three fee controllers call it **zero** times today.
- Files: `Storage` with a `public` disk that becomes S3 when `S3=on`. Every
  existing upload (`students/photos`, `schools/logos`) goes to the **public**
  disk. Payment evidence must not (§2.6).
- PDFs: `dompdf` (rich, used for result slips) and `App\Support\SimplePdfBuilder`
  (plain text, used for attendance exports).
- Student notifications: `StudentNotification` model + `StudentPushNotificationService`
  + FCM, dispatched from queued jobs (`SendAttendanceNotification` is the pattern).
- Tests: Pest, `tests/Feature/*`, run with `php artisan test`. Lint: `pint`.

---

## 2. Backend data model

Nine new tables, one evolved, one retired. All UUID PK, all `school_id`-scoped,
all money as `decimal(14,2)` — never float.

### 2.1 Money & status rules (decide these once, enforce everywhere)

These are the rules everything else derives from. Put them in one service so the
student view and the admin dashboard can never disagree:

```
bill.total            = Σ bill_items.net_amount
bill.verified_paid    = Σ payments.amount WHERE status = 'verified'
bill.pending          = Σ payments.amount WHERE status = 'pending_verification'
bill.outstanding      = max(bill.total − bill.verified_paid, 0)
bill_item.paid        = Σ payment_allocations.amount (verified payments only)
payment.unallocated   = payment.amount − Σ its allocations   ← a credit, not an error
```

**Balance comes from verified payments; allocations only explain *which fee*
each naira covered.** This matters: if an admin approves ₦50,000 but allocates
only ₦40,000, the student's outstanding still drops by ₦50,000 and ₦10,000 sits
as an unallocated credit. Deriving the balance from allocations instead would
silently under-credit students.

Status (derived, never stored as the source of truth — §19 of the spec):

| Condition | Status |
| --- | --- |
| `verified_paid > total` | `overpaid` |
| `verified_paid >= total` and `total > 0` | `paid` |
| `verified_paid > 0` | `partially_paid` |
| `verified_paid = 0` and `pending > 0` | `pending_verification` |
| otherwise | `unpaid` |

### 2.2 `fee_structures` — evolved into scope-aware assignments

Migration `add_scope_to_fee_structures_table`:

```php
$table->enum('scope', ['school', 'class', 'class_arm', 'student'])->default('class')->after('school_id');
$table->renameColumn('class_id', 'school_class_id');   // match codebase convention
// school_class_id + class_arm_id become nullable
$table->uuid('class_arm_id')->nullable();
$table->string('description')->nullable();
$table->date('due_date')->nullable();
$table->boolean('is_active')->default(true);
$table->uuid('created_by')->nullable();                 // users.id
$table->char('assignment_key', 64)->unique();           // see below
```

The existing `unique_fee_structure` index must be **dropped**: on MariaDB a
UNIQUE index treats every NULL as distinct, so once `school_class_id` is
nullable it stops catching duplicate school-wide fees entirely. Replace it with
a deterministic `assignment_key` column — `sha256(scope|school_class_id|class_arm_id|session_id|term_id|fee_item_id)`
with `''` for nulls — computed in a model `saving` hook and covered by a real
unique index. Portable, and it works for every scope.

Plus a pivot for `scope = 'student'`:

```
fee_structure_students (fee_structure_id, student_id)  — composite PK
```

A backfill step in the same migration stamps `scope='class'` and an
`assignment_key` on every existing row so current data survives untouched.

**Naming:** the spec calls these "fee structures" in the admin nav, so keeping
the table name avoids churning the web client's `API_ROUTES.feeStructures`
contract. The API adds a parallel `/fees/assignments` alias for the new
scope-aware create/update shape; `/fees/structures` keeps working.

### 2.3 `student_bills`

One row per student per session/term. Materialised (not derived) because a bill
must snapshot the class the student was in when it was raised — mid-term
transfers and promotions would otherwise rewrite history.

```
id, school_id, student_id, session_id, term_id,
school_class_id, class_arm_id            -- snapshot at generation time
generated_at, created_at, updated_at
unique (student_id, session_id, term_id)
index (school_id, session_id, term_id)
```

No cached totals. Totals come from aggregate queries over items/payments so they
cannot drift. If the outstanding-students report gets slow at scale, add cached
columns later behind the same service — don't start there.

### 2.4 `student_bill_items`

```
id, student_bill_id, fee_structure_id (nullable, set null on delete),
fee_item_id (nullable), name, description,            -- name snapshotted
source enum('school','class','class_arm','student','manual'),
amount, discount_amount, surcharge_amount, net_amount,  -- net = amount − disc + sur
is_removed boolean, removed_reason,
created_at, updated_at
index (student_bill_id)
```

`fee_structure_id` is nullable + `nullOnDelete` so deleting an assignment never
destroys a bill that has money allocated against it.

### 2.5 `fee_adjustments` — §18 overrides, with the audit trail built in

```
id, school_id, student_bill_item_id,
type enum('discount','surcharge','waiver'),
amount, reason, created_by (users.id), reversed_at, reversed_by,
created_at
```

`student_bill_items.discount_amount` / `surcharge_amount` are **projections** of
this table, recomputed in the same transaction as any write. The adjustment rows
are what §18's "any modification should create an audit record" asks for.

### 2.6 `payments` + `payment_evidence`

One `payments` table with an explicit lifecycle, not two tables. §23's
"a payment submission is not the same thing as a verified payment" is a
*semantic* separation, and a status column enforces it without a dual-write
consistency problem; "Payment Submissions" and "Verified Payments" in the admin
nav are two filters over the same table.

```
payments:
  id, school_id, student_id, session_id, term_id,
  student_bill_id (nullable),
  reference          varchar, unique(school_id, reference)   -- CYF-10483
  receipt_number     varchar nullable, unique(school_id, receipt_number)
  amount             decimal(14,2)
  method             enum('bank_transfer','cash','pos','cheque','online','other')
  paid_at            date
  bank_detail_id     nullable
  note               text nullable
  source             enum('student_submission','admin_manual')
  status             enum('pending_verification','verified','rejected','reversed')
  submitted_by_type  string  -- 'student' | 'user'   (nullable morph)
  submitted_by_id    uuid
  verified_by, verified_at
  rejected_by, rejected_at, rejection_reason
  reversed_by, reversed_at, reversal_reason
  index (school_id, status), index (student_id, status)

payment_evidence:
  id, payment_id, disk, path, original_name, mime_type, size_bytes,
  uploaded_by_type, uploaded_by_id, created_at
```

**Evidence files must go on a private disk, not the `public` one the rest of the
codebase uses.** These are financial documents naming a student and an amount;
on the public disk (and especially on S3 with `visibility: public`) the URL is
world-readable and guessable-adjacent. Serve them through an authenticated
controller route that re-checks school + ownership and streams the file, or a
short-lived `temporaryUrl`. This is a deliberate deviation from the existing
`->store(..., 'public')` habit — flag it in review so it isn't "corrected" back.

Nothing is ever hard-deleted. A mistaken verification is undone with
`status = 'reversed'` + a reason, which reverses its allocations too.

### 2.7 `payment_allocations`

```
id, payment_id, student_bill_item_id, amount, allocated_by, created_at
unique (payment_id, student_bill_item_id)
```

Invariants, enforced in the service inside a transaction with
`lockForUpdate()` on the payment:

- `Σ allocations(payment) ≤ payment.amount`
- `Σ allocations(bill_item) ≤ bill_item.net_amount`
- allocations exist only for `status = 'verified'` payments
- re-allocating replaces the whole set for that payment (delete + insert), so
  partial writes can't leave a torn state

### 2.8 `payment_references` (sequence)

Per-school monotonic counter for `CYF-xxxxx` references and receipt numbers.
A tiny `school_id, kind, next_value` table read with `lockForUpdate()` beats
`max()+1`, which races under concurrent submissions.

### 2.9 `finance_audit_logs`

The existing `audit_logs` table is unusable here: its `user_id` is a hard FK to
`users`, and half the actors in this module are **students**. Rather than
loosening a table nothing writes to yet, add a purpose-built one:

```
id, school_id,
actor_type ('user'|'student'|'system'), actor_id (nullable),
action        -- 'payment.verified', 'payment.rejected', 'fee.adjusted', ...
subject_type, subject_id,
before json, after json,
ip_address, created_at
index (school_id, created_at), index (subject_type, subject_id)
```

Written by a `FinanceAuditLogger` service, called from every state-changing
finance service method. That satisfies §21's who/what/when/before/after.

### 2.10 Retire `fee_payments`

Add a `@deprecated` docblock to the model and drop the relations from
`Student`/`Session`/`Term` in this change; drop the table in a follow-up
migration one release later. Do not build on it.

---

## 3. Backend services

`app/Services/Fees/` — thin controllers, logic here, matching the existing
`app/Services/CBT` and `app/Services/Rbac` layout.

| Service | Responsibility |
| --- | --- |
| `FeeAssignmentService` | Create/update/delete assignments across the 4 scopes; resolve an assignment → the set of students it applies to; `preview()` (student count + total) before committing a bulk assignment |
| `BillGenerationService` | Idempotent sync of a student's bill items for a session/term from all applicable assignments. Adds new items, updates amounts on untouched items, and **never removes an item that has allocations** — marks it `is_removed` instead |
| `BillCalculator` | The single implementation of §2.1. Returns totals + derived status for one bill or, in one aggregate query, for a filtered set of students |
| `PaymentSubmissionService` | Student/parent submission: validate, store evidence privately, mint a reference, create the `pending_verification` payment |
| `PaymentVerificationService` | `approve()` / `reject()` / `reverse()`, transactional, row-locked, idempotent (approving a verified payment → 409, not a double credit). `approve()` mints the receipt number, auto-allocates oldest-unpaid-first, dispatches the notification job, writes the audit row |
| `PaymentAllocationService` | Manual (re)allocation with the §2.7 invariants |
| `FeeAdjustmentService` | Discounts/surcharges/waivers + bill-item projection refresh |
| `FinanceReportService` | Dashboard metrics (§20), outstanding-students report, collections by class/arm/fee item, CSV/PDF export |
| `FinanceAuditLogger` | §2.9 |
| `ReceiptRenderer` | `dompdf` receipt from a verified payment (mirrors the result-slip renderer in `StudentAuthController`) |

**Bill generation triggers:** creating/updating an assignment (sync affected
students, queued for school-wide scope), creating or transferring a student,
and an artisan command `fees:generate-bills {--school=} {--session=} {--term=}`
for backfill and repair. Generation is idempotent, so re-running is always safe.

---

## 4. API surface

### Admin — `auth:sanctum`, under `/api/v1/fees`

```
GET    /fees/overview                         §20 dashboard metrics
GET    /fees/items                            (existing)
GET    /fees/structures | /fees/assignments   list, scope-aware filters
POST   /fees/assignments                      scope + targets + amount
POST   /fees/assignments/preview              affected students + total, no write
PUT    /fees/assignments/{id}
DELETE /fees/assignments/{id}
POST   /fees/assignments/{id}/students        attach/detach for scope=student
POST   /fees/structures/copy                  (existing)

GET    /fees/bills                            filters: session, term, class, arm, status, q
GET    /fees/bills/{bill}
GET    /fees/students/{student}/bill
POST   /fees/bills/generate                   scoped regeneration
POST   /fees/bill-items/{item}/adjustments    discount / surcharge / waiver
DELETE /fees/adjustments/{adjustment}

GET    /fees/payments                         ?status=pending_verification → the queue
GET    /fees/payments/{payment}
POST   /fees/payments                         manual record (admin-entered)
POST   /fees/payments/{payment}/approve
POST   /fees/payments/{payment}/reject        { reason }  (required)
POST   /fees/payments/{payment}/reverse       { reason }  (required)
PUT    /fees/payments/{payment}/allocations   replace the allocation set
GET    /fees/payments/{payment}/evidence/{id} streamed, ownership-checked
GET    /fees/payments/{payment}/receipt.pdf

GET    /fees/reports/outstanding
GET    /fees/reports/collections
GET    /fees/reports/outstanding.csv | .pdf
GET    /fees/audit-logs

GET/PUT /fees/bank-details/...                (existing)
```

### Student / parent — `auth:student`, under `/api/v1/student/fees`

```
GET  /student/fees/bill                   current bill + §3 totals breakdown
GET  /student/fees/bills                  history across sessions/terms
GET  /student/fees/bills/{bill}           expandable item detail
GET  /student/fees/payments               payment history with status
POST /student/fees/payments               multipart submission + evidence
GET  /student/fees/payment-accounts       school bank details (read-only)
GET  /student/fees/payments/{id}/receipt.pdf
```

`POST /student/fees/payments` needs `throttle:10,1`, `mimes:jpg,jpeg,png,pdf`,
`max:5120`, and a server-side MIME re-check — a student-facing multipart upload
is the most abusable endpoint in the module.

### Permissions to add

`finance.dashboard.view`, `finance.assignments.{view,create,update,delete}`,
`finance.bills.{view,generate}`, `finance.bill-items.adjust`,
`finance.payments.{view,record,verify,reject,reverse,allocate}`,
`finance.reports.view`, `finance.audit.view`.

Add to `RbacService::$corePermissions`, `FrontendPermissionSeeder::$permissions`,
web `lib/permissionKeys.ts` and `lib/permissionCatalog.ts` — all four, they are
separate hand-maintained lists. And call `ensurePermission()` in **every** new
controller method, including retro-fitting the three existing fee controllers
that currently check nothing.

### Notifications

New jobs `SendPaymentVerifiedNotification` / `SendPaymentRejectedNotification`,
modelled on `SendAttendanceNotification`, delivering through
`StudentPushNotificationService` with `type: 'fees'`, `route: '/fees'`, and a
dedupe key of `payment:{id}:status:{status}`.

---

## 5. Web admin (Next.js) — `cyfamod-sms-web`

Keep the finance domain under the existing **v23** prefix rather than minting a
new version group, since `/v23/fee-structure` and `/v23/bank-details` already
live there.

```
app/(app)/v23/finance/overview          §20 metric cards + filters
app/(app)/v23/fee-structure             (existing — extend with scope selector)
app/(app)/v23/fee-assignments           §16/§17 bulk + selective allocation
app/(app)/v23/student-bills             per-student bill list + drill-in
app/(app)/v23/payment-submissions       §12 verification queue
app/(app)/v23/verified-payments
app/(app)/v23/outstanding
app/(app)/v23/reports
app/(app)/v23/bank-details              (existing)
```

- **Add the missing Finance section to `components/layout/Sidebar.tsx`.** There
  is none today, which is why the two existing pages are unreachable. Gate each
  link with the matching `requiredPermissions` key, following the existing
  `MenuLink` shape.
- `lib/fees.ts` gains assignment/bill helpers; add `lib/payments.ts` and
  `lib/studentFees.ts`; register every new path in `API_ROUTES` in `lib/config.ts`.
- Evidence viewer: fetch through the authenticated endpoint into a blob URL —
  do not put a raw storage URL in an `<img src>`, there won't be one.
- Approve/reject are irreversible-feeling actions: confirm dialog, reason
  required on reject, and disable the button while the mutation is in flight so
  a double-click can't double-approve (the backend is idempotent, but the UI
  shouldn't rely on that alone).

### Student portal (web)

```
app/(student)/v26/student-dashboard/fees/          Current Bill
app/(student)/v26/student-dashboard/fees/history/  Bill History
app/(student)/v26/student-dashboard/fees/payments/ Payment History + Submit
```

Add a "Fees & Payments" link to the nav array in
`app/(student)/v26/student-dashboard/layout.tsx`. The current-bill page must
show Total / Verified Paid / **Pending Verification** / Outstanding as four
distinct figures — the pending amount is the whole point of the workflow and
must never be folded into "paid".

---

## 6. Mobile (Expo) — `cyfamod-sms-app`

Student/parent side only for v1; staff finance screens are a later phase.

```
src/features/fees/{api.ts,hooks.ts,types.ts,schemas.ts}
src/routes/student/(tabs)/fees.tsx              bill + totals
src/routes/student/(tabs)/fees/history.tsx
src/routes/student/(tabs)/fees/submit.tsx       evidence upload
src/routes/student/(tabs)/fees/payment-info.tsx bank details + copy-to-clipboard
```

Per `AGENTS.md`: `axiosPrivate` in `api.ts`, hooks wrapping it with `QUERY_KEYS`
entries added to `src/lib/utils/query-keys.ts` (never inline arrays), React Hook
Form + Zod in `schemas.ts`, `<Screen>` wrapper, NativeWind + `useThemeColors()`,
`~/*` imports.

Two concrete gaps to handle:

- `expo-image-picker` is already a dependency but **`expo-document-picker` is
  not** — so either add it (needed for PDF bank receipts, which is what most
  Nigerian banks e-mail) or restrict mobile evidence to images in v1 and say so
  in the UI. Recommend adding the dependency; a PDF receipt is the common case.
- Add `/fees` to the push-notification deep-link map so the verified/rejected
  notification opens the right screen.

`AGENTS.md` requires a release record under **both** `docs/releases/staff/` and
`docs/releases/student/` for this work, and the mobile repo forbids AI
attribution on its commits — note the difference from the other two repos.

---

## 7. Phasing

Each phase is shippable and leaves the system consistent.

| Phase | Scope | Why here |
| --- | --- | --- |
| **1. Foundations** | Migrations (§2.2–§2.10), models, factories, `BillCalculator`, `FeeAssignmentService`, `BillGenerationService`, `fees:generate-bills`, permission keys | Nothing user-visible can be right until the money rules and the scope resolution are |
| **2. Admin fee assignment** | Scope-aware assignment API + web `/v23/fee-assignments`, `/v23/student-bills`, **Finance sidebar section** | Makes the orphaned v23 pages reachable and gives admins something to look at |
| **3. Student bill viewing** | Student bill/history endpoints, web `/v26/.../fees`, bank-details read endpoint | Read-only, zero money movement — safe to ship early |
| **4. Payment submission + verification** | Evidence upload (private disk), verification queue, approve/reject/reverse, auto-allocation, notifications, audit logging | The core loop |
| **5. Allocation, adjustments, receipts** | Manual allocation UI, discounts/surcharges, receipt PDF | Refinement on top of a working loop |
| **6. Dashboard & reports** | §20 metrics, outstanding report, CSV/PDF export, audit-log viewer | Needs real data to be worth building |
| **7. Mobile** | `src/features/fees` + student routes | Can run parallel with 5–6 once phase 4's API is frozen |

Online payment gateway integration stays out of scope, exactly as §26 says — the
`payments` table's `method='online'` and `source` columns leave room for it
without a schema change.

---

## 8. Testing

Pest feature tests under `tests/Feature/Fees/`:

- **Scope resolution** — a student in JSS 2A receives school-wide + JSS 2 class +
  JSS 2A arm + individual fees, and exactly those; a JSS 2B student does not get
  the arm fee. (§11's worked example is a ready-made fixture: ₦135,000.)
- **Bill generation idempotency** — running twice produces one set of items;
  running after an amount change updates it; running after an allocation exists
  never deletes the item.
- **Pending ≠ paid** — submitting evidence leaves `outstanding` unchanged and
  surfaces `pending` separately. This is the single most important assertion in
  the module.
- **Approval** — flips status, stamps verifier + timestamp, mints a receipt
  number, allocates, reduces outstanding, writes an audit row, queues the
  notification.
- **Double-approval** — second approve is a 409 and does not double-credit.
- **Rejection** — records the reason, keeps the record, leaves the balance alone.
- **Reversal** — undoes allocations and restores the balance.
- **Allocation invariants** — over-allocating a payment or a bill item is a 422.
- **Overpayment** — `verified_paid > total` yields `overpaid`, not a negative
  outstanding.
- **Tenancy** — school A cannot read, verify, or allocate school B's payments;
  student A cannot read student B's bill or evidence. One test per endpoint.
- **Evidence privacy** — an unauthenticated request for an evidence file is
  rejected, and the stored path is not on a public disk.

Run `vendor/bin/pint` and `php artisan test` before each PR (backend);
`npm run format:check && npm run lint && npm run typecheck && npm run build`
for mobile.

---

## 9. Open questions worth settling before Phase 1

1. **Does a bill span a term or a session?** The plan assumes per-term bills
   keyed `(student, session, term)`, which matches `fee_structures` today and the
   §3 mock-up. Annual-payment schools would need a session-scoped bill type.
2. **Carry-over debt.** When a student owes ₦40,000 in First Term, does that
   appear on the Second Term bill? The spec never says. Recommend: no — keep
   bills per-term and show a separate "previous outstanding" figure derived
   across bills, so history stays immutable.
3. **Partial-term students.** §17 mentions mid-term additions and there is a
   `midterm_student_additions` table already; should a student admitted in week
   8 get the full term's fees, or a prorated bill? Recommend full fee + an
   explicit discount adjustment, so the override is visible and audited.
4. **Who may verify?** `finance.payments.verify` as its own permission, distinct
   from `finance.payments.view`, so a bursar can see the queue without approving.
   Confirm that matches how schools actually want to split the duty.
5. **Currency.** Everything assumes NGN. If multi-currency is ever real, it has
   to be a column now, not a retrofit.
