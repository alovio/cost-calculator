# Alovio Calculator 2.0 — Product-Level Upgrade (Design Spec)

**Date:** 2026-07-05 · **Status:** Approved by user (brainstorming session) · **Target:** one release, free plugin **v2.0.0** + Pro add-on **v1.1.0** (separate repo, same-day)

## 1. Context

Alovio Calculator is live on wp.org at v1.4.1 (builder + 12 field types, decimal-safe formula engine with PHP+JS parity, free conditional logic, quote entries, 11 templates, 6 themes, wizard groundwork, live preview tab). The sibling plugin Alovio Checkout Fields shipped **builder v2** (2026-07-04): Alovio flame design tokens, dark app shell, WYSIWYG live-engine canvas, sentence-token Logic editor, bounded undo, one-click recipes. Both builders share the same skeleton (pure reducer + `@wordpress/data` store + native HTML5 DnD + apiFetch), so the port is low-friction.

**Goal:** take the calculator from MVP to product level in a single 2.0 release, across four tracks: (A) Builder Studio (port + beyond), (B) calculation power (repeater + new field types, all FREE), (C) growth (CCB importer + onboarding), (D) Pro v1.1 expansion.

**Decisions locked with the user:**
- One big v2.0 release (not phased).
- Everything in tracks A–C is FREE, including the repeater. Conditional logic stays free (the wedge). Pro keeps wizard/PDF/webhooks/analytics and gains the track-D features.
- Builder layout = **unified live studio**: no Fields/Settings/Preview tabs; the canvas IS the live preview.

## 2. Track A — Builder Studio v2

### 2.1 Shell

- `App.jsx` keeps its three views (list / builder / entries). The builder view drops `TabPanel` and becomes **`StudioShell`**: coal-dark header + 3-column workspace filling the admin content area.
- Header: flame logo + plugin name · **inline-editable calculator name** · spacer · save-status pill (grey saved / amber dirty / green just-saved / red error) · **Undo + Redo** ghost buttons · **Save** primary button · **Pro** ghost button (hidden when `isPro`).
- Keyboard: ⌘S/Ctrl+S save, ⌘Z undo, ⌘⇧Z redo. `beforeunload` dirty guard stays.
- Design tokens ported from Checkout Fields' `builder.css` into **`src/builder/builder.scss`** (compiled to `build/index.css` by wp-scripts), renamed to a **`--alcb-*`** prefix (builder-scoped; the frontend keeps its `--alc-*` tokens). Flame `#f97316`/`#ea580c`, coal `#1c1917`, ink greys, radii, layered shadow, gradient primary button/logo/empty-state.
- The **entries view gets a light token restyle only** (header, buttons) — no rework this release.

### 2.2 Live canvas (`LiveCanvas`)

Replaces both `Canvas.jsx` and the `Preview.jsx` tab.

- **Rendering:** structural state changes (fields/settings) trigger a 400 ms-debounced **`POST alovio-calc/v1/render`** (new endpoint beside the existing `/preview`; same `FieldSchema::normalize` path, `manage_options`-gated) returning `{ html }` — the exact `CalculatorRenderer::render()` fragment. The fragment is injected **inline** (no iframe), then the frontend bundle initialises it. There is ONE canonical renderer (PHP); no React replica, no drift.
- **Frontend init refactor:** `src/frontend/calculator.js` exports an `init(rootEl)` function (auto-init on DOMContentLoaded preserved for the site). `BuilderAssets.php` enqueues the frontend script + stylesheet on the builder screen so the canvas runs the real engine: typed values compute totals instantly client-side, conditions fire, themes apply, wizard steps navigate (real `wizard.js`).
- **Value persistence:** before each re-render the visitor-value map is read from the DOM and re-applied after injection, so sample values survive structural edits. Toolbar has a "Reset values" action.
- **Canvas toolbar:** Desktop/Tablet/Mobile width toggle (constrains the sheet) · theme quick-switcher (writes `settings.theme.preset`, undoable) · Reset values · "Open full preview" link (existing `/preview` full-page path stays for this).
- **Render failure:** the last good render stays on screen; a non-blocking error strip with Retry appears.

### 2.3 Overlay layer

Positioned over the rendered fragment using the existing `data-alc-*` field wrappers (bounding boxes tracked via ResizeObserver + scroll):

- Selection outline on the selected field; hover toolbar (⠿ drag grip, ⧉ duplicate, ✕ delete).
- **IF pill** per conditioned field — human-readable condition summary from a ported `describe.js` (covers our operators incl. ≥ ≤ empty/not-empty and formula/total controllers).
- **Formula-error badge** on fields whose expression fails validation.
- Click behaviour: clicking anywhere in a field selects it; the actual inputs remain interactive (click = focus + select). Selection changes are NOT recorded in undo history.
- **Drag & drop:** reorder with an insertion-line indicator between fields; **drag from palette to a position** dispatches `INSERT_AT`. Plain palette click inserts **after the selected field** (no longer always at the end). Native HTML5 DnD (no new dependency), with the ↑/↓ buttons kept in the hover toolbar as the accessible fallback.

### 2.4 Right settings panel (contextual)

- **Field selected** → tabs **[General | Logic | Options/Formula]** (third tab appears for choice/formula/repeater types).
  - General: label, help, placeholder/min/max/step/default per type, show-in-summary.
  - **Logic = port of Checkout Fields' sentence-token editor** (colored chips that are disguised native selects, AND/OR toggle, Show/Hide/Require segmented control), adapted to our operator set and controller rules (formula/total allowed, headings not).
  - Options (choice types): **drag-reorder options**, label+price+image — images now supported for select and checkbox_group too (schema already stores them), per-option "selected by default" flag.
  - Formula: existing live validation + insert-field token dropdown, errors mirrored to the canvas badge.
- **Nothing selected, or ⚙ clicked** → calculator-level tabs **[General | Design | Quote form]** (name/currency; theme preset + accent + layout incl. the Pro-gated wizard toggle; quote-form settings incl. the new file-upload block).
- **Pro button** → Pro panel content (replaces `ProTab`; remains the plugin's **single upsell surface**, guideline 11).

### 2.5 Palette

Categorised icon grid — **Inputs** (number, slider, quantity, text, textarea, date, email, phone, url) · **Choices** (select, radio, checkbox group, toggle) · **Content** (heading, HTML, step divider) · **Math** (formula, repeater) — with custom inline SVG icons (no unicode glyphs, no external assets). Below: a **Templates** section; clicking a template inserts its fields into the current calculator via `INSERT_FIELDS` with id remapping (option slugs re-generated server-side on save as today). The New Calculator template modal stays.

### 2.6 Reducer & drafts

- History: `past[]` / `future[]`, bounded **50**, `remember()` on mutating actions only (`ADD_FIELD`, `UPDATE_FIELD`, `REMOVE_FIELD`, `DUPLICATE_FIELD`, `REORDER`, `INSERT_AT`, `INSERT_FIELDS`, `UPDATE_SETTINGS`, name edits). `SELECT`/`HYDRATE` bypass history. Redo cleared on new mutation.
- New actions: `INSERT_AT(type, index)`, `INSERT_FIELDS(fields, index)` (with id/condition-reference remap, ported from Checkout Fields), `UNDO`, `REDO`.
- **Draft recovery (`draft.js`):** on state change (1 s debounce) serialize `{calcId, name, fields, settings, savedAt}` to `localStorage` key `alovio_calc_draft_<id>`; on builder open, if a draft is newer than the server record's modified time show a restore/discard notice bar. Cleared on successful save.

## 3. Track B — Repeater + new field types (all FREE)

### 3.1 Repeater field

**Schema** (`FieldSchema`): `{ id, type:'repeater', label, help, fields:[…children], minRows:1, maxRows (≤50 hard server cap), addLabel, rowLabel ('Room {n}'), rowExpression:'', showInSummary, conditions…}`.

- **Children:** restricted to number, slider, select, radio, checkbox_group, toggle, quantity. **No nesting, no child conditional logic, no child required flags** (v2.0 simplicity). Slug uniqueness is enforced across ALL levels by `FieldSchema`.
- **Row total, two modes:** (1) `rowExpression` empty → the row contributes its children's ordinary price contributions (option prices, toggle price); (2) `rowExpression` set (e.g. `{area} * {rate}`) → evaluated with the **same Lexer/Parser/Evaluator** against a row-local value map — zero new AST node types. Validation: `rowExpression` may reference child ids only (graph check in `FieldSchema`/`FormulaGraph`).
- **Aggregation:** the repeater's sum of row totals is exposed as `{repeater_id}` — referenceable in top-level formulas and usable as a condition controller (like formula fields). `Evaluation::run` computes repeater sums in a pre-pass before the existing fixed-point loop; a repeater hidden by its own conditions contributes 0. Summary shows one line item per row (rowLabel with `{n}` substitution).
- **Frontend:** PHP renders row markup once inside a `<template>`; JS clones/reindexes rows (+ Add row, per-row remove, min/max enforcement). `compute.js` mirrors row evaluation exactly; **parity fixtures extended** with repeater cases in the shared JSON suite.
- **Quote flow:** payload carries repeater values as an array of row objects `{childId: value}`. `QuoteController`'s authoritative server recompute mirrors the same semantics; the 201/400/429 contract is unchanged. Server guards: `maxRows` cap, payload size limit.
- **Builder:** the canvas shows the real rendered repeater. Selecting it opens a "Row fields" mini-list in the Options tab slot (add child from the restricted type list, reorder, click-to-edit a child in the same panel).

### 3.2 New simple field types

`date`, `email`, `phone`, `url`, `textarea` — informational lead-quality inputs (native HTML5 controls; **no datepicker library**). Text-like condition-controller semantics (`is`, `is_not`, `contains`, `is_empty`, `is_not_empty`); NOT referenceable in formulas; values stored in entries and shown in the summary when enabled. `date` is a plain informational field — **no booking/scheduling behavior, ever** (employer-conflict rule). Field count: 12 → **18**.

### 3.3 Quote-form file upload

Ported from Checkout Fields' proven FileUploads approach: async upload to a dedicated endpoint returning a token; the token is submitted with the quote and linked to the entry. Hardening: type allowlist (jpg/png/webp/pdf), size cap (`quoteForm.file.maxMb`, default 5), rate-limited public endpoint + honeypot, random filenames in a non-executable uploads subdir (`.htaccess`), orphan files GC'd by a daily cron after 24 h, files deleted with entry delete and privacy erase. Download via a capability-gated admin endpoint; entry detail and notification email reference the file. Settings: `quoteForm.file{enabled,label,types,maxMb}` (off by default).

### 3.4 Slider polish

Value bubble following the thumb, min/max labels, optional `unit` suffix setting rendered after the value. CSS + small JS; all six themes covered.

## 4. Track C — Growth

### 4.1 CCB importer (`includes/Import/CcbImporter.php`)

Three units: **detector** (is Cost Calculator Builder data present, active or not), **reader** (read-only access to CCB's storage), **mapper** (their field types → ours; their formula syntax → our `{ref}` expressions).

- UI: CalculatorList → "Import" menu → "From JSON" (existing) + "From Cost Calculator Builder": detected calculators listed with checkboxes → import → **mapping report** per calculator (what mapped, what was skipped and why). A formula that cannot be translated imports as an empty expression + warning; an import never hard-fails and only writes after a full successful map (per-calculator try/catch).
- REST: `GET alovio-calc/v1/import/ccb` (list), `POST alovio-calc/v1/import/ccb` (import selected ids); `manage_options`.
- **Open research item (first implementation task):** install CCB free in wp-env, record its storage format(s) as test fixtures, and pin the supported version range. The importer claims only verified formats; unknown versions get a graceful "unsupported" report. Type-mapping table drafted then (expected: Range→slider, Drop Down→select+option prices, Checkbox→checkbox_group, Toggle→toggle, Quantity→quantity, Total→formula, their date→our date; unmappable Pro widgets reported as skipped).

### 4.2 Onboarding (light, guideline-safe)

No activation redirect. (a) One dismissible post-activation notice ("Create your first calculator →"); (b) the CalculatorList **empty state becomes a rich start screen** (template cards + start blank); (c) a 3-step pointer tour on first Studio open (palette → canvas → save), dismissed state in localStorage; (d) a one-time dismissible "What's new in 2.0" notice for updaters.

## 5. Track D — Pro v1.1 (separate repo `~/alovio-calculator-pro`, same-day release)

1. **Conditional email routing** — rule list ("if field X matches Y, also notify Z"); sits on the existing `alovio_calc_quote_email` filter; no free-plugin changes required.
2. **PDF template variants** — 2–3 layout presets + per-calculator footer/terms text, extending the existing Dompdf service.
3. **Funnel analytics** — views → interactions → quote conversions per calculator. **Free-side support (in 2.0):** an anonymous, GDPR-clean beacon `POST alovio-calc/v1/view` (rate-limited, no cookies/PII) incrementing per-calculator postmeta counters, plus an `alovio_calc_view_recorded` action; documented as approximate under page caching. Pro reads the counters and renders the funnel.

Google Sheets OAuth integration is explicitly deferred (webhooks→Zapier already covers it).

## 6. Release & marketing

- Free plugin **2.0.0**; export JSON `schemaVersion` 1→2 (importer accepts v1). Existing saved configs need **no migration** — new keys receive defaults in `FieldSchema::normalize`; existing calculators render unchanged (visual spot-check on demo).
- readme.txt overhaul: 18 field types, "repeater — free" headline, new FAQ "Can I migrate from Cost Calculator Builder? Yes — built-in importer", refreshed feature list and changelog.
- All 6 wp.org screenshots re-shot (hero = the Studio); icon/banner unchanged.
- alovio.org/calculator + store page copy updated; demo.alovio.org/wp synced (new build + a repeater showcase calculator) and re-snapshotted.
- Budgets: frontend bundle **≤ 30 KB gz including repeater**; builder bundle measured at the first Studio chunk, then a budget is set and enforced in the QA checklist.

## 7. Testing

Existing gates stay mandatory: PHPUnit, Jest, PHPCS 0, Plugin Check 0 errors, bundle budgets.

New coverage: repeater shared parity fixtures (PHP+JS from one JSON); `FieldSchema` tests (repeater, 5 new types, file settings, rowExpression graph validation); `QuoteController` recompute tests (repeater rows, file token, caps); reducer history tests (undo/redo, INSERT_AT, INSERT_FIELDS remap — pure, Jest); `describe.js` and `draft.js` unit tests; CCB mapper unit tests against recorded fixtures. Heavy React component tests are skipped in favour of a **wp-env Playwright e2e checklist**: studio flow (create → drag → undo/redo → draft recovery → save), full repeater quote (client total == server recomputed total), file upload round-trip, import against a real CCB install, all 6 themes on canvas, wizard navigation on canvas.

## 8. Error handling (summary)

Canvas render failure → keep last good render + retry strip. Formula errors → canvas badge + panel message; saving is not blocked (frontend already guards via its errors map). Draft conflicts → newest-wins prompt (restore/discard). File upload → clear type/size rejections; orphan GC. Importer → per-calculator isolation, transparent skip report, no partial writes. Repeater → server-side row/payload caps; rate limiting unchanged.

## 9. Out of scope (YAGNI)

Per-row formula language (`sum(rooms, …)`), child-field conditional logic, nested repeaters, Google Sheets OAuth, payments, booking/appointment features (permanent), multi-currency, autosave-to-server (draft recovery is localStorage-only), entries-view rework, React component test suite.

## 10. Success criteria

All gates green; e2e checklist passed; existing calculators visually unchanged; v2.0.0 live on wp.org; Pro v1.1.0 live on Code Heaven; landing + demo updated the same day.
