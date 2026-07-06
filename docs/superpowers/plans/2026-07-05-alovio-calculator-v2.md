# Alovio Calculator 2.0 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Take the free plugin from v1.4.1 (MVP+) to v2.0.0 — unified live Builder Studio (Checkout Fields builder-v2 port + beyond), repeater + 5 new field types + quote file upload (all free), CCB importer + light onboarding, and the free-side analytics beacon.

**Architecture:** Studio = flame-token app shell whose canvas is the REAL calculator (server-rendered fragment via a new `POST /render`, injected inline, initialised by the actual frontend bundle) with a builder overlay on top; one canonical renderer, no React replica. Repeater reuses the existing Lexer/Parser/Evaluator with row-local value maps (no new AST nodes) and a pre-pass in `Evaluation::run`. Importer is detector/reader/mapper with a mapping report and no partial writes.

**Tech Stack:** WordPress plugin PHP 7.4+ (namespace `Alovio\Calculator`, WPCS), React via `@wordpress/scripts` + `@wordpress/data` store `alc/builder`, REST ns `alovio-calc/v1`, PHPUnit + Brain Monkey, Jest, shared PHP↔JS parity fixtures, wp-env + Playwright for e2e smoke.

**Spec:** `docs/superpowers/specs/2026-07-05-alovio-calculator-v2-product-design.md` (tracks A–C + §5.3 beacon; Track D is planned separately in the Pro repo).

---

## Conventions (read before any task)

- **Branch:** all work on `feat/v2-studio` off `main` (created in Task 1.1). The branch must stay releasable: every chunk ends with ALL gates green.
- **Gates (run from `/Users/tahir/alovio-calculator`):**
  - `vendor/bin/phpunit` — PHP suite (baseline: 137 tests green)
  - `npm test` — Jest (baseline: 87 tests green)
  - `vendor/bin/phpcs` — 0 errors (WPCS; use `vendor/bin/phpcbf` for auto-fixables)
  - `npm run build` — must compile clean; `build/` is GITIGNORED (never commit it; SVN gets it at release)
- **Commits:** stage EXPLICIT paths only (`git add <files>` — never `-A`/`.`), imperative subject, NO Co-Authored-By lines.
- **Version:** stays `1.4.1` until the release chunk bumps to `2.0.0` (never bump mid-plan).
- **wp-env:** sandbox via `npx @wordpress/env start`. ⚠️ NEVER `wp plugin install <zip> --force` with a zip whose inner folder matches the mapped plugin slug — it deletes the host working tree. Rename the zip's inner folder for sandbox installs.
- **Donor code:** Checkout Fields builder v2 lives at `/Users/tahir/woo-checkout-fields/src/builder/` + `/Users/tahir/woo-checkout-fields/assets/css/builder.css`. Port tasks reference donor files explicitly and list the exact deltas; NEW logic (history, overlay, repeater, importer) is written out in full in this plan.
- **Task numbering:** `Task <chunk>.<n>`. Chunks group as: Studio (1–4) → Calculation power (5–7) → Growth + release (8–9).
- **i18n:** all UI strings through `__( '…', 'alovio-calculator' )` (PHP) / `__` from `@wordpress/i18n` (JS) with LITERAL text-domain strings (make-pot compat).
- **Prefixes:** PHP/options/meta/REST use `alovio_calc_` / `alovio-calc/v1`; builder CSS tokens use `--alcb-*`; frontend keeps `--alc-*` and `.alc-*`.

## File structure (locked decisions)

**Studio (Track A)** — `src/builder/`:
- Create: `builder.scss` (design tokens + all studio styles; compiled into `build/index.css`), `StudioShell.jsx` (header + 3-column workspace), `LiveCanvas.jsx` (render fetch/inject/init + value persistence), `CanvasToolbar.jsx`, `CanvasOverlay.jsx` (selection/hover ops/IF pills/error badges/DnD), `PaletteV2.jsx` (categorised icon grid + templates), `icons.js` (inline SVG set), `describe.js` (human-readable condition summaries), `draft.js` (localStorage draft recovery), `SettingsPanel.jsx` + `panels/{FieldGeneral,LogicTokens,OptionsTab,FormulaTab,CalcGeneral,CalcDesign,CalcQuote,ProPanel}.jsx`.
- Modify: `App.jsx` (builder view → StudioShell), `reducer.js` (history, INSERT_AT, INSERT_FIELDS, UNDO/REDO), `api.js` (+`renderCalculator`, `modified` passthrough), `src/frontend/calculator.js` (export `init`), `includes/Admin/RestController.php` (+`POST /render`, GET gains `modified`), `includes/Admin/BuilderAssets.php` (enqueue frontend bundle+style on builder screen).
- Retire (deleted once replaced): `Canvas.jsx`, `Preview.jsx`, `FieldSettings.jsx`, `ConditionEditor.jsx`, `SettingsTab.jsx`, `ProTab.jsx`, `OptionsEditor.jsx`, `FieldPalette.jsx`.

**Calculation power (Track B):**
- Modify: `includes/Fields/{FieldTypes,FieldSchema}.php`, `includes/Logic/Evaluation.php` (repeater pre-pass), `includes/Frontend/CalculatorRenderer.php` (repeater `<template>`, new types, slider bubble), `src/frontend/{compute.js,calculator.js,quote-form.js}`, `src/shared` parity plumbing, `frontend-style.scss`.
- Create: `src/frontend/repeater.js`, `includes/Entries/FileUploads.php`, `tests/fixtures/repeater-cases.json` (shared PHP↔JS).
- Modify (entries surfaces): `includes/Entries/{QuoteController,CsvExporter,EntryMailer,EntriesRestController,Privacy}.php`, `src/builder/EntriesList.jsx` (detail modal rows + file link).

**Growth (Track C + §5.3):**
- Create: `includes/Import/{CcbDetector,CcbReader,CcbMapper,ImportController}.php`, `tests/fixtures/ccb/` (recorded CCB configs), `includes/Admin/Onboarding.php`, `includes/Analytics/Counter.php` (`POST /track` + per-day postmeta buckets + prune-on-write), `src/builder/tour.js`.
- Modify: `src/builder/CalculatorList.jsx` (import menu + rich empty state), `src/frontend/calculator.js` (beacon), `readme.txt`, `alovio-calculator.php` (2.0.0 at release).

## Chunk overview

| Chunk | Contents | Spec |
|---|---|---|
| 1 | Branch, tokens/`builder.scss`, reducer history (undo/redo/insert), StudioShell header + keyboard + status pill | §2.1, §2.6 |
| 2 | `POST /render` + `modified`, frontend `init()` refactor, LiveCanvas inject + value persistence + sequence token + toolbar | §2.2 |
| 3 | CanvasOverlay (selection/ops/IF pills/error badges), DnD insertion line, palette drag `INSERT_AT`, `describe.js` | §2.3 |
| 4 | PaletteV2 + icons + template insertion, SettingsPanel + all panels (token Logic editor port, options upgrades), draft recovery, Pro panel, retire old components | §2.4–2.6 |
| 5 | Repeater schema + engine (row-local evaluation, pre-pass, graph validation) + shared parity fixtures + compute.js engine mirror | §3.1 |
| 6 | Repeater frontend (template clone, rows UX, quote payload + server recompute) + entries/CSV/email surfaces + builder row-fields UX | §3.1 |
| 7 | New field types (date/email/phone/url/textarea), slider polish, quote-form file upload (+GC cron, entries surfaces) | §3.2–3.4 |
| 8 | CCB format research (fixtures) + detector/reader/mapper + import REST/UI + mapping report | §4.1 |
| 9 | Onboarding (empty state, notices, tour), `/track` beacon, readme/version/screenshots, full QA + release staging | §4.2, §5.3, §6, §7 |

---

# Alovio Calculator 2.0 — Implementation Plan, Group 1 (Chunks 1–4: Builder Studio, Track A)

> Companion to the plan skeleton `docs/superpowers/plans/2026-07-05-alovio-calculator-v2.md` — its **Conventions**, **File structure** and **Chunk overview** sections govern this document. Spec: `docs/superpowers/specs/2026-07-05-alovio-calculator-v2-product-design.md` §2 (+§6 planning note). All paths are relative to `/Users/tahir/alovio-calculator` unless absolute. Donor: `/Users/tahir/woo-checkout-fields`.

**Facts verified against the code (differences from earlier assumptions — trust these):**

1. `src/frontend/calculator.js` exports `initCalculators( doc )` (queries `.alc-calculator` inside `doc`); the per-instance initialiser is a module-private `function initCalculator( root )`. There is **no** `init( rootEl )` export yet — Task 2.3 creates it.
2. There is **no existing PHPUnit test for `RestController`** (no `tests/Unit/Admin/`). New REST tests follow the repo's Brain Monkey patterns from `tests/Unit/Frontend/CalculatorRendererTest.php` (stub set) and `tests/Unit/Entries/QuoteControllerTest.php` (callback-level testing, no `WP_REST_Request`).
3. `FieldRepository::save()` writes post **meta** only — a config-only save does **not** bump `post_modified`. Task 2.2 makes `update_calculator()` call `wp_update_post()` unconditionally so the `modified` timestamp moves on every save (otherwise draft recovery in §2.6 breaks).
4. `window.ALOVIO_CALC_BUILDER.templates` currently carries `key/title/description` only — no `fields`. Task 4.1 adds `fields` in `BuilderAssets.php` so the palette can `INSERT_FIELDS`.
5. The builder store has **no `name`** — the calculator title lives in React state in `App.jsx`'s `Builder`. Spec §2.6 lists "name edits" among remembered actions, so Task 1.3 moves the name into the store (`SET_NAME`, `HYDRATE` gains a `name` arg).
6. `src/index.js` imports `assets/css/builder.css`; that file also styles the **surviving** list/entries/template-modal views (`.alc-topbar`, `.alc-table`, `.alc-template-*`, `.alc-formula*`, …). It stays; `src/builder/builder.scss` is added alongside. Dead blocks are pruned in Task 4.7.
7. `tests/bootstrap.php` stubs `WP_Post` with only `$post_title` — Task 2.1 adds `$ID` and `$post_modified_gmt` (unit-test infra, not shipped code).
8. `@wordpress/i18n` resolves standalone under Jest (verified via `require.resolve`), so pure modules `describe.js`/`draft.js` may use `__()` and stay plain-Jest-testable.

**Interim-UI contract (explicit, per chunk):** rendering the old components as "placeholders" of new ones is not allowed; instead the v1 components stay **mounted and fully functional inside the new shell** until their replacement lands:

| Column | Chunk 1 | Chunk 2 | Chunk 3 | Chunk 4 |
|---|---|---|---|---|
| Left | `FieldPalette` | `FieldPalette` + `Canvas` (structure list moves here) | `FieldPalette` (Canvas unmounted — overlay owns select/reorder) | `PaletteV2` |
| Center | `Canvas` | `LiveCanvas` + toolbar | `LiveCanvas` + `CanvasOverlay` | unchanged |
| Right | `FieldSettings` / `SettingsTab` (none selected) / `ProTab` (Pro open) | same | same | `SettingsPanel` (all panels) |

The old `Preview.jsx` tab becomes unreachable at chunk 1 (the canvas replaces it from chunk 2; "Open full preview" returns in Task 2.4). All retired files are deleted in Task 4.7.

---

## Chunk 1: Branch, design tokens, reducer history, StudioShell

### Task 1.1: Create the working branch

**Files:** none (git only).

- [ ] **Step 1: Branch off main**
  ```bash
  cd /Users/tahir/alovio-calculator && git checkout main && git checkout -b feat/v2-studio
  ```
- [ ] **Step 2: Confirm baseline gates** — `vendor/bin/phpunit` (expect `OK (137 tests`…`)`), `npm test` (expect `Tests: 87 passed`), `vendor/bin/phpcs` (expect no output, exit 0). Do not proceed on any failure — the baseline must be green before the first change.

### Task 1.2: `builder.scss` — flame design tokens + wp-admin integration

**Files:**
- Create: `src/builder/builder.scss`
- Modify: `src/index.js` (add import, line 3 area), `includes/Admin/BuilderAssets.php` (add `admin_body_class` filter)

Donor: `/Users/tahir/woo-checkout-fields/assets/css/builder.css` lines 1–48 (`:root` block + full-bleed + select-neutralisation). Rename every `--clcf-*` token to `--alcb-*`, `clcf-` class prefixes to `alcb-`, and the body class `clcf-builder-page` to `alcb-builder-page`.

**Token mapping table (donor → ours; values unchanged unless noted):**

| Donor (`--clcf-*`) | Ours (`--alcb-*`) | Value |
|---|---|---|
| flame | flame | `#f97316` |
| flame-deep | flame-deep | `#ea580c` |
| flame-soft | flame-soft | `#ffedd5` |
| flame-border | flame-border | `#fed7aa` |
| coal | coal | `#1c1917` |
| coal-2 | coal-2 | `#292524` |
| ink | ink | `#1f2328` |
| ink-2 | ink-2 | `#57606a` |
| ink-3 | ink-3 | `#8b949e` |
| bg | bg | `#f6f5f4` |
| panel | panel | `#ffffff` |
| line | line | `#e7e5e4` |
| line-2 | line-2 | `#d6d3d1` |
| green | green | `#10b981` |
| green-soft | green-soft | `#d1fae5` |
| green-ink | green-ink | `#065f46` |
| — (donor hardcodes `#f59e0b`) | amber | `#f59e0b` (NEW — dirty pill) |
| — (donor hardcodes `#ef4444`) | red | `#ef4444` (NEW — error pill) |
| r | r | `12px` |
| r-sm | r-sm | `8px` |
| shadow | shadow | `0 1px 2px rgba(28,25,23,.06), 0 8px 24px -12px rgba(28,25,23,.14)` |

The file is organised into numbered sections; **each section is appended by the task that ships its component** (no empty sections are ever committed):

| Section | Content | Written in |
|---|---|---|
| 01 | tokens (`:root`) | Task 1.2 |
| 02 | wp-admin integration (full bleed, notice hiding) | Task 1.2 |
| 03 | app frame + header | Task 1.4 |
| 04 | workspace grid + columns | Task 1.4 |
| 05 | canvas column: toolbar, sheet, fragment resets, error strip | Task 2.4 |
| 06 | overlay: outline, ops, IF pill, error badge, insert line | Task 3.2 |
| 07 | palette v2 | Task 4.1 |
| 08 | settings panel + logic tokens + options rows | Tasks 4.3–4.5 |
| 09 | draft-restore bar | Task 4.6 |

- [ ] **Step 1: Create `src/builder/builder.scss` with sections 01–02**
  ```scss
  // Alovio Calculator — Builder Studio v2 (chrome only; the frontend keeps --alc-*).
  // Compiled into build/index.css by wp-scripts (imported from src/index.js).

  // ── 01 · Design tokens (ported from Checkout Fields builder.css, --clcf-* → --alcb-*)
  :root {
  	--alcb-flame: #f97316;
  	--alcb-flame-deep: #ea580c;
  	--alcb-flame-soft: #ffedd5;
  	--alcb-flame-border: #fed7aa;
  	--alcb-coal: #1c1917;
  	--alcb-coal-2: #292524;
  	--alcb-ink: #1f2328;
  	--alcb-ink-2: #57606a;
  	--alcb-ink-3: #8b949e;
  	--alcb-bg: #f6f5f4;
  	--alcb-panel: #ffffff;
  	--alcb-line: #e7e5e4;
  	--alcb-line-2: #d6d3d1;
  	--alcb-green: #10b981;
  	--alcb-green-soft: #d1fae5;
  	--alcb-green-ink: #065f46;
  	--alcb-amber: #f59e0b; // addition vs donor (hardcoded there): save-pill dirty state
  	--alcb-red: #ef4444; // addition vs donor (hardcoded there): save-pill error state
  	--alcb-r: 12px;
  	--alcb-r-sm: 8px;
  	--alcb-shadow: 0 1px 2px rgba(28, 25, 23, 0.06), 0 8px 24px -12px rgba(28, 25, 23, 0.14);
  }

  // ── 02 · wp-admin integration: full-bleed app, no pushed-down layout
  body.alcb-builder-page #wpcontent { padding-left: 0; }
  body.alcb-builder-page #wpbody-content { padding-bottom: 0; }
  body.alcb-builder-page #wpfooter { display: none; }
  // Admin notices would push the full-height app down and break its layout.
  body.alcb-builder-page #wpbody-content > .notice,
  body.alcb-builder-page #wpbody-content > .update-nag,
  body.alcb-builder-page #wpbody-content > .error { display: none; }
  ```
  (Donor's select-neutralisation block is NOT ported here — it targets chip-select components that arrive in chunk 4; Task 4.4 ships it with `.alcb-tok`.)
- [ ] **Step 2: Import it** — in `src/index.js`, after line 3 (`import '../assets/css/builder.css';`) add:
  ```js
  import './builder/builder.scss';
  ```
- [ ] **Step 3: Body class** — in `includes/Admin/BuilderAssets.php`: inside `register()` (after the existing `add_action` on line 18) add:
  ```php
  add_filter( 'admin_body_class', array( $this, 'body_class' ) );
  ```
  and add the method after `enqueue()`:
  ```php
  /**
   * Marks the builder screen so builder.scss can go full-bleed (spec §2.1).
   */
  public function body_class( string $classes ): string {
  	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
  	if ( $screen && 'toplevel_page_' . AdminPage::SLUG === $screen->id ) {
  		$classes .= ' alcb-builder-page';
  	}
  	return $classes;
  }
  ```
- [ ] **Step 4: Verify** — `npm run build` compiles clean; `grep -c 'alcb-flame' build/index.css` ≥ 1. `vendor/bin/phpcs` → 0 errors.
- [ ] **Step 5: Commit**
  ```bash
  git add src/builder/builder.scss src/index.js includes/Admin/BuilderAssets.php
  git commit -m "studio: flame design tokens (--alcb-*) + full-bleed builder body class"
  ```

### Task 1.3: Reducer history — undo/redo, INSERT_AT, INSERT_FIELDS, SET_NAME

**Files:**
- Test: `src/builder/__tests__/reducer.test.js` (new)
- Modify: `src/builder/reducer.js` (history plumbing; `DEFAULTS`, `makeId`, `cloneOptions` and the bodies of existing case blocks are unchanged unless listed)

Donor reference: `/Users/tahir/woo-checkout-fields/src/builder/reducer.js` (bounded `past[]` + `remember()` + `remapTemplate`). **Deliberate divergences from the donor:** limit 50 (not 25); full **redo** via `future[]` (donor has undo only); snapshots cover `{ name, fields, settings }` (donor snapshots `fields` only — our settings and calculator name are undoable per spec §2.6); `remapFields` drops the donor's `@`-context-token guard (our rules only reference sibling field ids) and keeps option `value` slugs untouched (intra-template rules reference `opt_` slugs; `FieldSchema` preserves valid slugs on save).

**Remembered (mutating) actions — `remember()` runs on exactly these, and each clears `future`:** `ADD_FIELD`, `UPDATE_FIELD`, `REMOVE_FIELD`, `DUPLICATE_FIELD`, `REORDER`, `INSERT_AT`, `INSERT_FIELDS`, `UPDATE_SETTINGS`, `SET_NAME`. **Bypass history:** `SELECT` (never recorded), `HYDRATE` (clears both stacks), `UNDO`/`REDO` (move between stacks). Note: `UPDATE_FIELD`/`SET_NAME` record per keystroke — same behaviour as the donor, accepted.

- [ ] **Step 1: Write the failing tests** — create `src/builder/__tests__/reducer.test.js`:
  ```js
  import { reducer, actions, selectors, initialState, HISTORY_LIMIT, remapFields } from '../reducer';

  const run = ( list, start = initialState ) => list.reduce( ( s, a ) => reducer( s, a ), start );

  describe( 'history: undo/redo', () => {
  	it( 'undo restores fields, settings and name; redo re-applies', () => {
  		let s = run( [ actions.addField( 'number' ), actions.updateSettings( { theme: { preset: 'bold' } } ), actions.setName( 'Roof quote' ) ] );
  		expect( s.fields ).toHaveLength( 1 );
  		s = reducer( s, actions.undo() ); // name gone
  		expect( s.name ).toBe( '' );
  		s = reducer( s, actions.undo() ); // settings gone
  		expect( s.settings ).toEqual( {} );
  		s = reducer( s, actions.redo() );
  		expect( s.settings.theme.preset ).toBe( 'bold' );
  		s = reducer( s, actions.redo() );
  		expect( s.name ).toBe( 'Roof quote' );
  		expect( selectors.canRedo( s ) ).toBe( false );
  	} );

  	it( 'redo stack is cleared by a new mutation', () => {
  		let s = run( [ actions.addField( 'number' ), actions.addField( 'slider' ) ] );
  		s = reducer( s, actions.undo() );
  		expect( selectors.canRedo( s ) ).toBe( true );
  		s = reducer( s, actions.addField( 'toggle' ) );
  		expect( selectors.canRedo( s ) ).toBe( false );
  	} );

  	it( 'history is bounded to HISTORY_LIMIT (50)', () => {
  		expect( HISTORY_LIMIT ).toBe( 50 );
  		let s = initialState;
  		for ( let i = 0; i < 60; i++ ) {
  			s = reducer( s, actions.setName( `n${ i }` ) );
  		}
  		expect( s.past ).toHaveLength( 50 );
  	} );

  	it( 'SELECT is never recorded; HYDRATE clears both stacks and sets the name', () => {
  		let s = run( [ actions.addField( 'number' ) ] );
  		const depth = s.past.length;
  		s = reducer( s, actions.selectField( null ) );
  		expect( s.past ).toHaveLength( depth );
  		s = reducer( s, actions.undo() );
  		s = reducer( s, actions.hydrate( [ { id: 'a', type: 'number' } ], { x: 1 }, 'Loaded' ) );
  		expect( s.past ).toHaveLength( 0 );
  		expect( s.future ).toHaveLength( 0 );
  		expect( s.name ).toBe( 'Loaded' );
  	} );

  	it( 'UNDO/REDO on empty stacks are no-ops', () => {
  		expect( reducer( initialState, actions.undo() ) ).toBe( initialState );
  		expect( reducer( initialState, actions.redo() ) ).toBe( initialState );
  	} );

  	it( 'selection is dropped when the selected field vanishes on undo', () => {
  		let s = run( [ actions.addField( 'number' ), actions.addField( 'slider' ) ] );
  		expect( s.selectedId ).toBe( s.fields[ 1 ].id );
  		s = reducer( s, actions.undo() );
  		expect( s.selectedId ).toBeNull();
  	} );
  } );

  describe( 'INSERT_AT', () => {
  	it( 'inserts at the index, selects the new field, records history', () => {
  		let s = run( [ actions.addField( 'number' ), actions.addField( 'slider' ) ] );
  		s = reducer( s, actions.insertAt( 'toggle', 1 ) );
  		expect( s.fields.map( ( f ) => f.type ) ).toEqual( [ 'number', 'toggle', 'slider' ] );
  		expect( s.selectedId ).toBe( s.fields[ 1 ].id );
  		s = reducer( s, actions.undo() );
  		expect( s.fields.map( ( f ) => f.type ) ).toEqual( [ 'number', 'slider' ] );
  	} );

  	it( 'clamps out-of-range indexes', () => {
  		let s = run( [ actions.addField( 'number' ) ] );
  		s = reducer( s, actions.insertAt( 'slider', 99 ) );
  		expect( s.fields[ 1 ].type ).toBe( 'slider' );
  		s = reducer( s, actions.insertAt( 'toggle', -5 ) );
  		expect( s.fields[ 0 ].type ).toBe( 'toggle' );
  	} );
  } );

  describe( 'INSERT_FIELDS + remapFields', () => {
  	const tpl = [
  		{ id: 'area', type: 'slider', label: 'Area', min: 0, max: 100 },
  		{ id: 'svc', type: 'radio', label: 'Service', options: [ { value: 'opt_std', label: 'Std', price: 2 } ] },
  		{
  			id: 'note', type: 'heading', label: 'Note',
  			conditions: [ { field: 'area', operator: 'gt', value: '50' }, { field: 'external', operator: 'is', value: 'x' } ],
  			conditionMatch: 'all', conditionAction: 'show',
  		},
  	];

  	it( 'remaps ids and intra-template condition refs; leaves foreign refs + option slugs alone', () => {
  		const out = remapFields( tpl );
  		expect( out ).toHaveLength( 3 );
  		expect( out.map( ( f ) => f.id ) ).not.toContain( 'area' );
  		expect( new Set( out.map( ( f ) => f.id ) ).size ).toBe( 3 );
  		expect( out[ 2 ].conditions[ 0 ].field ).toBe( out[ 0 ].id ); // remapped
  		expect( out[ 2 ].conditions[ 1 ].field ).toBe( 'external' ); // untouched
  		expect( out[ 1 ].options[ 0 ].value ).toBe( 'opt_std' ); // slug preserved
  		expect( tpl[ 0 ].id ).toBe( 'area' ); // input not mutated
  	} );

  	it( 'inserts the mapped fields at the index and selects the first', () => {
  		let s = run( [ actions.addField( 'number' ) ] );
  		s = reducer( s, actions.insertFields( tpl, 0 ) );
  		expect( s.fields ).toHaveLength( 4 );
  		expect( s.fields[ 3 ].type ).toBe( 'number' );
  		expect( s.selectedId ).toBe( s.fields[ 0 ].id );
  		s = reducer( s, actions.undo() );
  		expect( s.fields ).toHaveLength( 1 );
  	} );

  	it( 'is a state no-op for an empty list', () => {
  		const s = run( [ actions.addField( 'number' ) ] );
  		expect( reducer( s, { type: 'INSERT_FIELDS', fields: [], index: 0 } ) ).toBe( s );
  	} );
  } );
  ```
- [ ] **Step 2: Run and watch them fail** — `npm test -- reducer` → expected: suite fails on import (`HISTORY_LIMIT`/`remapFields` are not exported → `undefined`), plus `actions.undo is not a function`. The 87 existing tests stay green.
- [ ] **Step 3: Implement in `src/builder/reducer.js`** — exact deltas (full code for every new/changed piece):
  1. After `makeId()` add:
     ```js
     export const HISTORY_LIMIT = 50;
     ```
  2. Replace the `initialState` line:
     ```js
     export const initialState = { name: '', fields: [], settings: {}, selectedId: null, past: [], future: [] };
     ```
  3. After `cloneOptions()` add the history helpers and the field factory:
     ```js
     /** What undo/redo restores (spec §2.6): structure, settings and the calculator name. */
     function snapshot( state ) {
     	return { name: state.name, fields: state.fields, settings: state.settings };
     }

     /** Push the current snapshot before a mutating action (bounded). */
     function remember( state ) {
     	const past = [ ...state.past, snapshot( state ) ];
     	if ( past.length > HISTORY_LIMIT ) {
     		past.shift();
     	}
     	return past;
     }

     function makeField( fieldType, id ) {
     	const defaults = DEFAULTS[ fieldType ] || DEFAULTS.text;
     	const field = { id, type: fieldType, ...defaults };
     	if ( defaults.options ) {
     		field.options = cloneOptions( defaults.options );
     	}
     	return field;
     }

     function clampIndex( index, length ) {
     	const i = typeof index === 'number' && ! Number.isNaN( index ) ? index : length;
     	return Math.max( 0, Math.min( i, length ) );
     }

     /** Keep the selection only if the restored snapshot still contains that field. */
     function keepSelection( fields, selectedId ) {
     	return fields.some( ( f ) => f.id === selectedId ) ? selectedId : null;
     }
     ```
  4. Rewrite the `switch` — every existing mutating case gains `past: remember( state ), future: [],` in its returned object (their bodies are otherwise IDENTICAL to today), `ADD_FIELD` now uses `makeField`, and these cases are added:
     ```js
     case 'UNDO': {
     	if ( ! state.past.length ) {
     		return state;
     	}
     	const past = [ ...state.past ];
     	const prev = past.pop();
     	return {
     		...state,
     		...prev,
     		past,
     		future: [ snapshot( state ), ...state.future ],
     		selectedId: keepSelection( prev.fields, state.selectedId ),
     	};
     }
     case 'REDO': {
     	if ( ! state.future.length ) {
     		return state;
     	}
     	const [ next, ...future ] = state.future;
     	return {
     		...state,
     		...next,
     		past: remember( state ),
     		future,
     		selectedId: keepSelection( next.fields, state.selectedId ),
     	};
     }
     case 'INSERT_AT': {
     	const field = makeField( action.fieldType, action.id );
     	const fields = [ ...state.fields ];
     	fields.splice( clampIndex( action.index, fields.length ), 0, field );
     	return { ...state, past: remember( state ), future: [], fields, selectedId: field.id };
     }
     case 'INSERT_FIELDS': {
     	if ( ! Array.isArray( action.fields ) || ! action.fields.length ) {
     		return state;
     	}
     	const fields = [ ...state.fields ];
     	fields.splice( clampIndex( action.index, fields.length ), 0, ...action.fields );
     	return { ...state, past: remember( state ), future: [], fields, selectedId: action.fields[ 0 ].id };
     }
     case 'SET_NAME':
     	return { ...state, past: remember( state ), future: [], name: String( action.name ?? '' ) };
     ```
     and `HYDRATE` becomes:
     ```js
     case 'HYDRATE':
     	return {
     		...state,
     		name: typeof action.name === 'string' ? action.name : '',
     		fields: Array.isArray( action.fields ) ? action.fields : [],
     		settings: action.settings && typeof action.settings === 'object' ? action.settings : {},
     		past: [],
     		future: [],
     	};
     ```
  5. After the reducer add the template remapper (donor `remapTemplate`, adapted — see divergences above):
     ```js
     /**
      * Remap template-local ids to fresh unique ids, rewriting intra-template
      * condition references. Refs to ids outside the template and option `value`
      * slugs are left untouched. Never mutates its input.
      */
     export function remapFields( templateFields ) {
     	const idMap = {};
     	const fields = ( templateFields || [] ).map( ( f ) => {
     		const copy = JSON.parse( JSON.stringify( f ) );
     		idMap[ copy.id ] = makeId();
     		copy.id = idMap[ copy.id ];
     		return copy;
     	} );
     	fields.forEach( ( f ) => {
     		if ( Array.isArray( f.conditions ) ) {
     			f.conditions = f.conditions.map( ( r ) => ( r.field && idMap[ r.field ] ? { ...r, field: idMap[ r.field ] } : r ) );
     		}
     	} );
     	return fields;
     }
     ```
  6. Extend `actions` (existing creators unchanged; `hydrate` gains the third arg):
     ```js
     insertAt: ( fieldType, index ) => ( { type: 'INSERT_AT', fieldType, index, id: makeId() } ),
     insertFields: ( templateFields, index ) => ( { type: 'INSERT_FIELDS', fields: remapFields( templateFields ), index } ),
     undo: () => ( { type: 'UNDO' } ),
     redo: () => ( { type: 'REDO' } ),
     setName: ( name ) => ( { type: 'SET_NAME', name } ),
     hydrate: ( fields, settings, name ) => ( { type: 'HYDRATE', fields, settings, name } ),
     ```
  7. Extend `selectors`:
     ```js
     getName: ( state ) => state.name,
     canUndo: ( state ) => state.past.length > 0,
     canRedo: ( state ) => state.future.length > 0,
     ```
- [ ] **Step 4: Run** — `npm test` → expected: all suites pass (87 baseline + the new reducer suite). `store.js` needs no change (it registers `actions`/`selectors` wholesale).
- [ ] **Step 5: Commit**
  ```bash
  git add src/builder/reducer.js src/builder/__tests__/reducer.test.js
  git commit -m "studio: bounded undo/redo history, INSERT_AT/INSERT_FIELDS with id remap, name in store"
  ```

### Task 1.4: StudioShell — header, keyboard, status pill, 3-column workspace

**Files:**
- Create: `src/builder/StudioShell.jsx`
- Modify: `src/builder/App.jsx` (drop the `Builder` function + `TabPanel`; builder view renders `StudioShell`), `src/builder/builder.scss` (append sections 03–04)

Donor: `/Users/tahir/woo-checkout-fields/src/builder/AppShell.jsx`. Deltas vs donor: our load/save go through `getCalculator`/`saveCalculator` (per-calculator, title included) instead of `clcf/v1/fields`; save-status pill gains the 4th (green "Saved") state donor-style flash; **Redo** button added; keyboard adds ⌘Z/⌘⇧Z with text-input suppression (donor has ⌘S only); inline-editable name input added; "Try Alovio Calculator" cross-promo link dropped; Pro ghost button added (hidden when `isPro`); back button added; the workspace columns host the v1 components (interim contract above).

- [ ] **Step 1: Create `src/builder/StudioShell.jsx`** (full code):
  ```jsx
  import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
  import { useSelect, useDispatch } from '@wordpress/data';
  import { Spinner, Notice } from '@wordpress/components';
  import { __ } from '@wordpress/i18n';
  import { STORE } from './store';
  import { getCalculator, saveCalculator } from './api';
  import FieldPalette from './FieldPalette';
  import Canvas from './Canvas';
  import FieldSettings from './FieldSettings';
  import SettingsTab from './SettingsTab';
  import ProTab from './ProTab';

  /**
   * True when the event target edits text — undo/redo shortcuts must never
   * hijack native text-editing undo (spec §2.1).
   */
  function isTextTarget( t ) {
  	return !! t && ( 'INPUT' === t.tagName || 'TEXTAREA' === t.tagName || 'SELECT' === t.tagName || true === t.isContentEditable );
  }

  export default function StudioShell( { calculatorId, onBack } ) {
  	const { fields, settings, name, selected, canUndo, canRedo } = useSelect(
  		( select ) => ( {
  			fields: select( STORE ).getFields(),
  			settings: select( STORE ).getSettings(),
  			name: select( STORE ).getName(),
  			selected: select( STORE ).getSelected(),
  			canUndo: select( STORE ).canUndo(),
  			canRedo: select( STORE ).canRedo(),
  		} ),
  		[]
  	);
  	const { hydrate, undo, redo, setName } = useDispatch( STORE );
  	const [ loading, setLoading ] = useState( true );
  	const [ loadError, setLoadError ] = useState( false );
  	const [ saving, setSaving ] = useState( false );
  	const [ flash, setFlash ] = useState( null ); // 'saved' | 'error' | null
  	const [ proOpen, setProOpen ] = useState( false );
  	const savedRef = useRef( null );
  	const modifiedRef = useRef( '' ); // server post_modified_gmt (fed from chunk 2; draft.js consumes it in chunk 4)
  	const isPro = !! ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.isPro );

  	const snapshot = ( f, s, n ) => JSON.stringify( { f, s, n } );

  	useEffect( () => {
  		setLoading( true );
  		getCalculator( calculatorId )
  			.then( ( calc ) => {
  				hydrate( calc.config.fields || [], calc.config.settings || {}, calc.title || '' );
  				savedRef.current = snapshot( calc.config.fields || [], calc.config.settings || {}, calc.title || '' );
  				modifiedRef.current = calc.modified || '';
  			} )
  			.catch( () => setLoadError( true ) )
  			.finally( () => setLoading( false ) );
  		// eslint-disable-next-line react-hooks/exhaustive-deps
  	}, [ calculatorId ] );

  	const dirty = savedRef.current !== null && snapshot( fields, settings, name ) !== savedRef.current;

  	useEffect( () => {
  		const handler = ( e ) => {
  			if ( dirty ) {
  				e.preventDefault();
  				e.returnValue = '';
  			}
  		};
  		window.addEventListener( 'beforeunload', handler );
  		return () => window.removeEventListener( 'beforeunload', handler );
  	}, [ dirty ] );

  	const save = useCallback( async () => {
  		setSaving( true );
  		try {
  			const saved = await saveCalculator( calculatorId, {
  				title: name,
  				config: { schemaVersion: 1, fields, settings },
  			} );
  			// Re-hydrate from the normalized response — the server may rewrite
  			// option slugs. HYDRATE clears undo history ON PURPOSE: stale
  			// snapshots could resurrect pre-slug options the server renamed.
  			hydrate( saved.config.fields || [], saved.config.settings || {}, saved.title || '' );
  			savedRef.current = snapshot( saved.config.fields || [], saved.config.settings || {}, saved.title || '' );
  			modifiedRef.current = saved.modified || modifiedRef.current;
  			setFlash( 'saved' );
  			window.setTimeout( () => setFlash( null ), 2500 );
  		} catch ( e ) {
  			setFlash( 'error' );
  		}
  		setSaving( false );
  	}, [ calculatorId, name, fields, settings, hydrate ] );

  	useEffect( () => {
  		const onKey = ( e ) => {
  			if ( ! ( e.metaKey || e.ctrlKey ) ) {
  				return;
  			}
  			const k = e.key.toLowerCase();
  			if ( 's' === k ) {
  				e.preventDefault();
  				save();
  				return;
  			}
  			if ( 'z' !== k || isTextTarget( e.target ) ) {
  				return;
  			}
  			e.preventDefault();
  			if ( e.shiftKey ) {
  				redo();
  			} else {
  				undo();
  			}
  		};
  		window.addEventListener( 'keydown', onKey );
  		return () => window.removeEventListener( 'keydown', onKey );
  	}, [ save, undo, redo ] );

  	const back = () => {
  		// eslint-disable-next-line no-alert
  		if ( ! dirty || window.confirm( __( 'You have unsaved changes. Leave anyway?', 'alovio-calculator' ) ) ) {
  			onBack();
  		}
  	};

  	if ( loading ) {
  		return <div className="alcb-app alcb-app--center"><Spinner /></div>;
  	}
  	if ( loadError ) {
  		return (
  			<div className="alcb-app alcb-app--center">
  				<Notice status="error" isDismissible={ false }>{ __( 'Could not load this calculator.', 'alovio-calculator' ) }</Notice>
  			</div>
  		);
  	}

  	let statusCls = 'alcb-status';
  	let statusTxt = __( 'All changes saved', 'alovio-calculator' );
  	if ( 'error' === flash ) {
  		statusCls += ' is-error';
  		statusTxt = __( 'Save failed — try again', 'alovio-calculator' );
  	} else if ( dirty ) {
  		statusCls += ' is-dirty';
  		statusTxt = __( 'Unsaved changes', 'alovio-calculator' );
  	} else if ( 'saved' === flash ) {
  		statusCls += ' is-saved';
  		statusTxt = __( 'Saved', 'alovio-calculator' );
  	}

  	return (
  		<div className="alcb-app">
  			<div className="alcb-hdr">
  				<button className="alcb-back" onClick={ back } aria-label={ __( 'All calculators', 'alovio-calculator' ) }>←</button>
  				<div className="alcb-logo">
  					<span className="alcb-mark">▲</span>
  					Alovio <span className="alcb-sub">{ __( 'Calculator', 'alovio-calculator' ) }</span>
  				</div>
  				<input
  					className="alcb-name"
  					value={ name }
  					placeholder={ __( 'Calculator name', 'alovio-calculator' ) }
  					aria-label={ __( 'Calculator name', 'alovio-calculator' ) }
  					onChange={ ( e ) => setName( e.target.value ) }
  				/>
  				<div className="alcb-grow"></div>
  				<span className={ statusCls }><span className="alcb-dot"></span>{ statusTxt }</span>
  				<button className="alcb-btn-ghost" disabled={ ! canUndo } onClick={ undo }>⟲ { __( 'Undo', 'alovio-calculator' ) }</button>
  				<button className="alcb-btn-ghost" disabled={ ! canRedo } onClick={ redo }>⟳ { __( 'Redo', 'alovio-calculator' ) }</button>
  				{ ! isPro && (
  					<button className={ 'alcb-btn-ghost alcb-btn-pro' + ( proOpen ? ' is-on' : '' ) } aria-pressed={ proOpen } onClick={ () => setProOpen( ! proOpen ) }>
  						{ __( 'Pro', 'alovio-calculator' ) }
  					</button>
  				) }
  				<button className="alcb-btn-primary" disabled={ saving } onClick={ save }>
  					{ saving ? __( 'Saving…', 'alovio-calculator' ) : __( 'Save', 'alovio-calculator' ) }
  				</button>
  			</div>
  			<div className="alcb-work">
  				{ /* INTERIM (see plan header table): v1 components run inside the new
  				     shell until chunks 2–4 replace each column. Their alc-* styles
  				     still ship from assets/css/builder.css. */ }
  				<div className="alcb-col alcb-col--left"><FieldPalette /></div>
  				<div className="alcb-col alcb-col--center"><Canvas /></div>
  				<div className="alcb-col alcb-col--right">
  					{ proOpen ? <ProTab /> : ( selected ? <FieldSettings /> : <SettingsTab /> ) }
  				</div>
  			</div>
  		</div>
  	);
  }
  ```
- [ ] **Step 2: Rewrite `src/builder/App.jsx`** — the whole file becomes (the `Builder` function and its imports move out; `CalculatorList`/`EntriesList` untouched):
  ```jsx
  import { useState } from '@wordpress/element';
  import StudioShell from './StudioShell';
  import CalculatorList from './CalculatorList';
  import EntriesList from './EntriesList';

  export default function App() {
  	const [ view, setView ] = useState( 'list' );
  	const [ calculatorId, setCalculatorId ] = useState( null );

  	if ( view === 'builder' && calculatorId ) {
  		return <StudioShell calculatorId={ calculatorId } onBack={ () => setView( 'list' ) } />;
  	}
  	if ( view === 'entries' ) {
  		return <EntriesList onBack={ () => setView( 'list' ) } />;
  	}
  	return (
  		<CalculatorList
  			onEdit={ ( id ) => {
  				setCalculatorId( id );
  				setView( 'builder' );
  			} }
  			onEntries={ () => setView( 'entries' ) }
  		/>
  	);
  }
  ```
- [ ] **Step 3: Append builder.scss sections 03–04** (port of donor lines 50–115 with renames; additions: `.alcb-back`, `.alcb-name`, Redo shares `.alcb-btn-ghost`, `.is-saved` green pill text, `.alcb-app--center`, column scroll wrappers):
  ```scss
  // ── 03 · App frame + header
  .alcb-app {
  	font-size: 13px;
  	color: var(--alcb-ink);
  	background: var(--alcb-bg);
  	display: flex;
  	flex-direction: column;
  	height: calc(100vh - 32px); // minus admin bar
  	overflow: hidden;
  }
  .alcb-app *, .alcb-app *::before, .alcb-app *::after { box-sizing: border-box; }
  .alcb-app--center { align-items: center; justify-content: center; }

  .alcb-hdr {
  	height: 58px; flex: none; background: var(--alcb-coal); color: #fff;
  	display: flex; align-items: center; gap: 12px; padding: 0 16px;
  }
  .alcb-back {
  	width: 34px; height: 34px; border-radius: var(--alcb-r-sm); flex: none;
  	border: 1px solid #44403c; background: transparent; color: #d6d3d1; font-size: 15px; cursor: pointer;
  }
  .alcb-back:hover { border-color: #78716c; color: #fff; }
  .alcb-logo { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 15px; white-space: nowrap; }
  .alcb-logo .alcb-mark {
  	width: 28px; height: 28px; border-radius: 8px;
  	background: linear-gradient(135deg, var(--alcb-flame), var(--alcb-flame-deep));
  	display: flex; align-items: center; justify-content: center; font-size: 14px;
  }
  .alcb-logo .alcb-sub { color: #a8a29e; font-weight: 600; font-size: 13px; margin-left: 2px; }
  .alcb-name {
  	height: 34px; min-width: 180px; max-width: 340px; flex: 1;
  	border: 1px solid transparent; border-radius: var(--alcb-r-sm);
  	background: transparent; color: #fff; font-size: 14px; font-weight: 700; padding: 0 10px;
  }
  .alcb-name:hover { border-color: #44403c; }
  .alcb-name:focus { border-color: var(--alcb-flame); background: var(--alcb-coal-2); outline: none; box-shadow: none; }
  .alcb-name::placeholder { color: #78716c; }
  .alcb-hdr .alcb-grow { flex: 1; }
  .alcb-status { display: flex; align-items: center; gap: 7px; color: #a8a29e; font-size: 12.5px; font-weight: 600; white-space: nowrap; }
  .alcb-status .alcb-dot { width: 7px; height: 7px; border-radius: 50%; background: #57534e; }
  .alcb-status.is-dirty .alcb-dot { background: var(--alcb-amber); }
  .alcb-status.is-saved { color: var(--alcb-green); }
  .alcb-status.is-saved .alcb-dot { background: var(--alcb-green); }
  .alcb-status.is-error { color: #fca5a5; }
  .alcb-status.is-error .alcb-dot { background: var(--alcb-red); }

  .alcb-btn-ghost {
  	height: 34px; padding: 0 14px; border-radius: var(--alcb-r-sm);
  	border: 1px solid #44403c; background: transparent; color: #d6d3d1;
  	font-size: 13px; font-weight: 700; cursor: pointer;
  	display: inline-flex; align-items: center; gap: 7px;
  }
  .alcb-btn-ghost:hover:not(:disabled) { border-color: #78716c; color: #fff; }
  .alcb-btn-ghost:disabled { opacity: 0.4; cursor: default; }
  .alcb-btn-pro { color: #fdba74; border-color: rgba(249, 115, 22, 0.45); }
  .alcb-btn-pro.is-on { background: rgba(249, 115, 22, 0.18); }
  .alcb-btn-primary {
  	height: 34px; padding: 0 18px; border-radius: var(--alcb-r-sm); border: none;
  	background: linear-gradient(135deg, var(--alcb-flame), var(--alcb-flame-deep));
  	color: #fff; font-size: 13px; font-weight: 800; cursor: pointer;
  	box-shadow: 0 4px 14px -4px rgba(234, 88, 12, 0.6);
  	display: inline-flex; align-items: center; gap: 7px;
  }
  .alcb-btn-primary:hover { filter: brightness(1.06); }
  .alcb-btn-primary:disabled { opacity: 0.6; cursor: default; }

  // ── 04 · Workspace grid
  .alcb-work {
  	flex: 1;
  	display: grid;
  	grid-template-columns: 252px 1fr 340px;
  	min-height: 0;
  }
  @media (max-width: 1100px) { .alcb-work { grid-template-columns: 220px 1fr 300px; } }
  .alcb-col { min-height: 0; overflow-y: auto; }
  .alcb-col--left { background: var(--alcb-panel); border-right: 1px solid var(--alcb-line); padding: 14px; }
  .alcb-col--center { display: flex; flex-direction: column; padding: 16px 22px; }
  .alcb-col--right { background: var(--alcb-panel); border-left: 1px solid var(--alcb-line); padding: 14px; }
  .alcb-sec-label {
  	font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
  	color: var(--alcb-ink-3); margin: 4px 2px 10px; display: block;
  }
  ```
- [ ] **Step 4: Verify** — `npm run build` clean; `npm test` green (no component tests — reducer/pure suites unaffected).
- [ ] **Step 5: wp-env spot-check** — `npx @wordpress/env start`, open Calculator → edit one: coal header with name input, pill goes amber on edit, ⌘Z/⌘⇧Z work on canvas ops but NOT while typing in a text field, ⌘S saves (pill flashes green), Redo disabled after a fresh mutation, `beforeunload` prompt when dirty, old palette/canvas/settings fully functional in the three columns, Pro button toggles the ProTab in the right column (hidden if `isPro`).
- [ ] **Step 6: Commit**
  ```bash
  git add src/builder/StudioShell.jsx src/builder/App.jsx src/builder/builder.scss
  git commit -m "studio: StudioShell header + keyboard + status pill + 3-column workspace (v1 panels interim)"
  ```

### Task 1.5: Chunk 1 gates

**Files:** none.

- [ ] **Step 1: Run all gates** — `vendor/bin/phpunit` (137 green), `npm test` (baseline + reducer suite green), `vendor/bin/phpcs` (0), `npm run build` (clean). Fix regressions before starting chunk 2 — the branch stays releasable at every chunk boundary.

## Chunk 2: POST /render, `modified`, frontend `init()`, LiveCanvas + toolbar

### Task 2.1: `POST alovio-calc/v1/render` (PHP, tests first)

**Files:**
- Test: `tests/Unit/Admin/RestControllerTest.php` (new file, new dir), `tests/bootstrap.php` (WP_Post stub gains props)
- Modify: `includes/Admin/RestController.php` (route + callback + `use` line)

No RestController test exists today — this suite follows `CalculatorRendererTest` (Brain Monkey stub set) and `QuoteControllerTest` (callback-level testing). Route callbacks take an untyped `$request`, so a duck-typed request object suffices; `get_calculator()` also uses `$request['id']`, hence `ArrayAccess`.

- [ ] **Step 1: Extend the `WP_Post` stub** — in `tests/bootstrap.php`, the stub class body becomes:
  ```php
  class WP_Post {
  	public $ID                = 0;
  	public $post_title        = '';
  	public $post_modified_gmt = '';
  }
  ```
- [ ] **Step 2: Write the failing tests** — create `tests/Unit/Admin/RestControllerTest.php`:
  ```php
  <?php
  namespace Alovio\Calculator\Tests\Unit\Admin;

  use Alovio\Calculator\Admin\RestController;
  use Alovio\Calculator\Fields\FieldRepository;
  use Alovio\Calculator\Tests\TestCase;
  use Brain\Monkey\Functions;

  /** Duck-typed WP_REST_Request stand-in (route callbacks type-hint nothing). */
  class FakeRequest implements \ArrayAccess {
  	private $params;
  	public function __construct( array $params ) {
  		$this->params = $params;
  	}
  	public function get_param( $key ) {
  		return $this->params[ $key ] ?? null;
  	}
  	#[\ReturnTypeWillChange]
  	public function offsetExists( $key ): bool {
  		return isset( $this->params[ $key ] );
  	}
  	#[\ReturnTypeWillChange]
  	public function offsetGet( $key ) {
  		return $this->params[ $key ] ?? null;
  	}
  	#[\ReturnTypeWillChange]
  	public function offsetSet( $key, $value ): void {
  		$this->params[ $key ] = $value;
  	}
  	#[\ReturnTypeWillChange]
  	public function offsetUnset( $key ): void {
  		unset( $this->params[ $key ] );
  	}
  }

  class RestControllerTest extends TestCase {

  	protected function setUp(): void {
  		parent::setUp();
  		// Renderer + schema stub set — mirrors CalculatorRendererTest.
  		Functions\when( 'apply_filters' )->returnArg( 2 );
  		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
  		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( (string) $s ) );
  		Functions\when( 'sanitize_hex_color' )->returnArg();
  		Functions\when( 'sanitize_email' )->returnArg();
  		Functions\when( 'wp_kses_post' )->returnArg();
  		Functions\when( 'esc_attr' )->alias( static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ) );
  		Functions\when( 'esc_html' )->alias( static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ) );
  		Functions\when( 'esc_url' )->returnArg();
  		Functions\when( 'rest_url' )->alias( static fn( $path = '' ) => 'https://example.test/wp-json/' . $path );
  		Functions\when( 'wp_json_encode' )->alias( static fn( $data, $flags = 0 ) => json_encode( $data, $flags ) );
  		Functions\when( '__' )->returnArg();
  		Functions\when( 'esc_html__' )->alias( static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ) );
  		Functions\when( 'wp_get_attachment_image' )->justReturn( '<img src="thumb.jpg" alt="" />' );
  		Functions\when( 'rest_ensure_response' )->returnArg();
  		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
  	}

  	public function test_render_fragment_returns_canonical_renderer_html(): void {
  		$res = ( new RestController() )->render_fragment(
  			new FakeRequest(
  				array(
  					'calculatorId' => 7,
  					'fields'       => array(
  						array( 'id' => 'area', 'type' => 'number', 'label' => 'Area' ),
  						array( 'id' => 'evil', 'type' => 'nope', 'label' => 'Dropped' ),
  					),
  					'settings'     => array(),
  				)
  			)
  		);
  		$this->assertArrayHasKey( 'html', $res );
  		$this->assertStringContainsString( 'class="alc-calculator', $res['html'] );
  		$this->assertStringContainsString( 'data-alc-id="7"', $res['html'] );
  		$this->assertStringContainsString( 'data-alc-field="area"', $res['html'] );
  		$this->assertStringNotContainsString( 'evil', $res['html'] ); // unknown type dropped by FieldSchema::normalize
  		$this->assertStringContainsString( 'class="alc-config"', $res['html'] ); // embedded payload — init() parses it
  	}

  	public function test_render_fragment_survives_garbage_body(): void {
  		$res = ( new RestController() )->render_fragment( new FakeRequest( array( 'fields' => 'not-an-array' ) ) );
  		$this->assertStringContainsString( 'class="alc-calculator', $res['html'] ); // empty-but-valid fragment
  	}

  	public function test_can_manage_gates_on_manage_options(): void {
  		Functions\when( 'current_user_can' )->justReturn( false );
  		$this->assertFalse( ( new RestController() )->can_manage() );
  	}
  }
  ```
- [ ] **Step 3: Run and watch it fail** — `vendor/bin/phpunit --filter RestControllerTest` → expected: `Error: Call to undefined method ...RestController::render_fragment()` (3 failures/errors).
- [ ] **Step 4: Implement** — in `includes/Admin/RestController.php`:
  1. Add the import after line 8 (`use Alovio\Calculator\Templates\Presets;`):
     ```php
     use Alovio\Calculator\Frontend\CalculatorRenderer;
     ```
  2. In `register_routes()`, after the `/settings` registration (line 89–107 block), add:
     ```php
     register_rest_route(
     	'alovio-calc/v1',
     	'/render',
     	array(
     		'methods'             => 'POST',
     		'callback'            => array( $this, 'render_fragment' ),
     		'permission_callback' => array( $this, 'can_manage' ),
     	)
     );
     ```
  3. Add the callback after `update_settings()`:
     ```php
     /**
      * Studio live canvas (spec §2.2): renders the UNSAVED config through the one
      * canonical renderer. Same FieldSchema::normalize path as /preview; the
      * response html is injected inline by the builder and re-initialised by the
      * real frontend bundle. manage_options-gated like every builder route.
      *
      * @param \WP_REST_Request $request Request.
      * @return \WP_REST_Response|array
      */
     public function render_fragment( $request ) {
     	$config = FieldSchema::normalize(
     		array(
     			'fields'   => (array) $request->get_param( 'fields' ),
     			'settings' => (array) $request->get_param( 'settings' ),
     		)
     	);

     	return rest_ensure_response(
     		array(
     			'html' => CalculatorRenderer::render( absint( $request->get_param( 'calculatorId' ) ), $config ),
     		)
     	);
     }
     ```
- [ ] **Step 5: Run** — `vendor/bin/phpunit` → expected: all green (137 + 3 new). `vendor/bin/phpcs` → 0.
- [ ] **Step 6: Commit**
  ```bash
  git add includes/Admin/RestController.php tests/Unit/Admin/RestControllerTest.php tests/bootstrap.php
  git commit -m "studio: POST /render endpoint — canonical fragment for the live canvas"
  ```

### Task 2.2: Calculator GET/PUT gain `modified` (PUT always bumps it)

**Files:**
- Test: `tests/Unit/Admin/RestControllerTest.php` (extend)
- Modify: `includes/Admin/RestController.php` (`get_calculator()` lines 176–188, `update_calculator()` lines 191–217)

Code-over-spec note: `FieldRepository::save()` only writes post meta, which does NOT touch `post_modified` — so `update_calculator()` must call `wp_update_post()` on every save (not only when a title arrives) or draft recovery (spec §2.6) would compare against a stale server timestamp.

- [ ] **Step 1: Add failing tests** to `RestControllerTest`:
  ```php
  public function test_get_calculator_includes_modified(): void {
  	$post                    = new \WP_Post();
  	$post->ID                = 12;
  	$post->post_title        = 'Roof quote';
  	$post->post_modified_gmt = '2026-07-05 09:30:00';
  	Functions\when( 'get_post' )->justReturn( $post );
  	Functions\when( 'get_post_type' )->justReturn( FieldRepository::POST_TYPE );
  	Functions\when( 'get_post_meta' )->justReturn( '' );

  	$res = ( new RestController() )->get_calculator( new FakeRequest( array( 'id' => 12 ) ) );
  	$this->assertSame( '2026-07-05 09:30:00', $res['modified'] );
  	$this->assertSame( 'Roof quote', $res['title'] );
  }

  public function test_update_calculator_always_bumps_and_returns_modified(): void {
  	$post             = new \WP_Post();
  	$post->ID         = 12;
  	$post->post_title = 'Roof quote';
  	Functions\when( 'get_post' )->justReturn( $post );
  	Functions\when( 'get_post_type' )->justReturn( FieldRepository::POST_TYPE );
  	Functions\when( 'get_post_meta' )->justReturn( '' );
  	Functions\when( 'update_post_meta' )->justReturn( true );
  	Functions\when( 'wp_slash' )->returnArg();
  	Functions\when( 'get_post_field' )->justReturn( '2026-07-05 10:00:00' );
  	// The essential contract: post_modified moves even on a config-only save.
  	Functions\expect( 'wp_update_post' )->once()->with( array( 'ID' => 12 ) );

  	$res = ( new RestController() )->update_calculator(
  		new FakeRequest( array( 'id' => 12, 'config' => array( 'fields' => array(), 'settings' => array() ) ) )
  	);
  	$this->assertSame( '2026-07-05 10:00:00', $res['modified'] );
  }
  ```
- [ ] **Step 2: Run** — `vendor/bin/phpunit --filter RestControllerTest` → expected: 2 failures (`modified` key missing; `wp_update_post` never called — Mockery expectation error).
- [ ] **Step 3: Implement** —
  1. `get_calculator()`: add to the response array after `'title'`:
     ```php
     'modified' => (string) $post->post_modified_gmt,
     ```
  2. `update_calculator()`: replace the title-update block (lines 197–205) with:
     ```php
     $title  = $request->get_param( 'title' );
     $update = array( 'ID' => $post->ID );
     if ( is_string( $title ) && '' !== $title ) {
     	$update['post_title'] = $title;
     }
     // Always runs — even config-only saves must bump post_modified, or the
     // studio's draft-recovery comparison (spec §2.6) sees a stale timestamp
     // (FieldRepository::save touches meta only).
     wp_update_post( $update );
     ```
     and add to its response array after `'config'`:
     ```php
     'modified' => (string) get_post_field( 'post_modified_gmt', $post->ID ),
     ```
- [ ] **Step 4: Run** — `vendor/bin/phpunit` all green; `vendor/bin/phpcs` 0.
- [ ] **Step 5: Commit**
  ```bash
  git add includes/Admin/RestController.php tests/Unit/Admin/RestControllerTest.php
  git commit -m "rest: calculator responses carry modified; PUT bumps post_modified on every save"
  ```

### Task 2.3: Frontend `init( rootEl )` export + studio global + builder-screen enqueue

**Files:**
- Modify: `src/frontend/calculator.js` (rename internal init, line 91 + line 173–175), `src/frontend.js` (full rewrite, 12 lines), `includes/Admin/BuilderAssets.php` (enqueue frontend handles)

The builder bundle cannot import the frontend bundle's module instances (separate webpack entries), so the contract is a window global set by the frontend entry. `FrontendAssets::register_assets()` registers handles directly (verified — plain `wp_register_*`, no hook dependency), so calling it from admin context is safe; `Preview.php` line 85 already uses exactly this pattern.

- [ ] **Step 1: `src/frontend/calculator.js`** — two exact edits:
  1. Line 91 `function initCalculator( root ) {` becomes:
     ```js
     /** Initialise ONE rendered calculator root (`.alc-calculator` element). Idempotent per fresh fragment; the studio canvas calls this after each inject (spec §2.2). */
     export function init( root ) {
     ```
  2. Lines 173–175 become:
     ```js
     export function initCalculators( doc ) {
     	doc.querySelectorAll( '.alc-calculator' ).forEach( init );
     }
     ```
- [ ] **Step 2: `src/frontend.js`** — full new content (auto-init preserved):
  ```js
  import './frontend/frontend-style.scss';
  import { init, initCalculators } from './frontend/calculator';

  // Studio contract: the builder re-initialises injected canvas fragments through
  // this global — the two bundles cannot share module instances (spec §2.2).
  window.AlovioCalc = Object.assign( window.AlovioCalc || {}, { init, initAll: initCalculators } );

  if ( document.readyState === 'loading' ) {
  	document.addEventListener( 'DOMContentLoaded', () => initCalculators( document ) );
  } else {
  	initCalculators( document );
  }
  ```
- [ ] **Step 3: `includes/Admin/BuilderAssets.php`** — add the import after line 7 (`use Alovio\Calculator\Templates\Presets;`):
  ```php
  use Alovio\Calculator\Frontend\FrontendAssets;
  ```
  and inside `enqueue()`, right after the builder style block (line 36–39), add:
  ```php
  // The studio canvas runs the REAL frontend engine against server-rendered
  // fragments (spec §2.2) — same handles the Preview page uses.
  ( new FrontendAssets() )->register_assets();
  wp_enqueue_script( 'alovio-calc-frontend' );
  wp_enqueue_style( 'alovio-calc-frontend' );
  ```
- [ ] **Step 4: Verify** — `npm run build` clean; `npm test` green (compute/wizard suites don't import `calculator.js`); `vendor/bin/phpcs` 0. In wp-env, load a front-end page with a shortcode calculator: still computes (auto-init path), and `window.AlovioCalc.init` is a function in the console.
- [ ] **Step 5: Commit**
  ```bash
  git add src/frontend/calculator.js src/frontend.js includes/Admin/BuilderAssets.php
  git commit -m "frontend: export init(rootEl) + AlovioCalc global; enqueue engine on builder screen"
  ```

### Task 2.4: LiveCanvas + CanvasToolbar

**Files:**
- Create: `src/builder/LiveCanvas.jsx`, `src/builder/CanvasToolbar.jsx`
- Modify: `src/builder/api.js` (+1 line), `src/builder/StudioShell.jsx` (center column; move interim `Canvas` to left column), `src/builder/builder.scss` (append section 05)

All new logic — full code below. Sequence-token rule (spec §2.2): each request carries a monotonically increasing `seq`; a response is applied only if `seq > appliedSeq`, so an out-of-order success can never clobber a newer render. On failure the last good fragment stays; the error strip offers Retry.

- [ ] **Step 1: api.js** — append:
  ```js
  export const renderCalculator = ( body ) => apiFetch( { path: 'alovio-calc/v1/render', method: 'POST', data: body } );
  ```
- [ ] **Step 2: Create `src/builder/CanvasToolbar.jsx`** (full code):
  ```jsx
  import { useState } from '@wordpress/element';
  import { useSelect, useDispatch } from '@wordpress/data';
  import { Spinner } from '@wordpress/components';
  import { __ } from '@wordpress/i18n';
  import { STORE } from './store';
  import { previewCalculator } from './api';

  export const DEVICES = [
  	{ id: 'desktop', label: __( 'Desktop', 'alovio-calculator' ), width: '100%' },
  	{ id: 'tablet', label: __( 'Tablet', 'alovio-calculator' ), width: '820px' },
  	{ id: 'mobile', label: __( 'Mobile', 'alovio-calculator' ), width: '390px' },
  ];

  // Single source for the preset list in studio chrome (CalcDesign reuses it in chunk 4).
  export const THEME_PRESETS = [
  	{ value: 'classic', label: __( 'Classic', 'alovio-calculator' ) },
  	{ value: 'minimal', label: __( 'Minimal', 'alovio-calculator' ) },
  	{ value: 'midnight', label: __( 'Midnight', 'alovio-calculator' ) },
  	{ value: 'soft', label: __( 'Soft', 'alovio-calculator' ) },
  	{ value: 'bold', label: __( 'Bold', 'alovio-calculator' ) },
  	{ value: 'slate', label: __( 'Slate', 'alovio-calculator' ) },
  ];

  export default function CanvasToolbar( { device, onDevice, onResetValues, busy } ) {
  	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
  	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
  	const { updateSettings } = useDispatch( STORE );
  	const [ opening, setOpening ] = useState( false );
  	const theme = settings.theme || {};

  	const openPreview = async () => {
  		setOpening( true );
  		try {
  			const res = await previewCalculator( { fields, settings } );
  			window.open( res.url, '_blank', 'noopener' );
  		} catch ( e ) {
  			// Non-fatal — the canvas itself is the preview; the error strip covers render issues.
  		}
  		setOpening( false );
  	};

  	return (
  		<div className="alcb-toolbar">
  			<div className="alcb-devices" role="group" aria-label={ __( 'Canvas width', 'alovio-calculator' ) }>
  				{ DEVICES.map( ( d ) => (
  					<button key={ d.id } className={ device === d.id ? 'is-on' : '' } aria-pressed={ device === d.id } onClick={ () => onDevice( d.id ) }>
  						{ d.label }
  					</button>
  				) ) }
  			</div>
  			<label className="alcb-theme-pick">
  				{ __( 'Theme', 'alovio-calculator' ) }
  				{ /* Writes settings.theme.preset through the store — UPDATE_SETTINGS is remembered, so it is undoable. */ }
  				<select value={ theme.preset || 'classic' } onChange={ ( e ) => updateSettings( { theme: { ...theme, preset: e.target.value } } ) }>
  					{ THEME_PRESETS.map( ( t ) => (
  						<option key={ t.value } value={ t.value }>{ t.label }</option>
  					) ) }
  				</select>
  			</label>
  			<div className="alcb-grow"></div>
  			{ busy && <Spinner /> }
  			<button className="alcb-tool-btn" onClick={ onResetValues }>{ __( 'Reset values', 'alovio-calculator' ) }</button>
  			<button className="alcb-tool-btn" disabled={ opening } onClick={ openPreview }>{ __( 'Open full preview', 'alovio-calculator' ) } ↗</button>
  		</div>
  	);
  }
  ```
- [ ] **Step 3: Create `src/builder/LiveCanvas.jsx`** (full code):
  ```jsx
  import { useState, useEffect, useRef } from '@wordpress/element';
  import { useSelect } from '@wordpress/data';
  import { __ } from '@wordpress/i18n';
  import { STORE } from './store';
  import { renderCalculator } from './api';
  import CanvasToolbar, { DEVICES } from './CanvasToolbar';

  /**
   * Read a { fieldId: {kind, value} } snapshot off the rendered fragment so
   * typed sample values survive structural re-renders (spec §2.2). Type-agnostic
   * on purpose: it keys off the DOM, not the config, so it also covers the
   * chunk-5+ field types without changes.
   */
  export function snapshotValues( root ) {
  	const values = {};
  	root.querySelectorAll( '[data-alc-field]' ).forEach( ( wrap ) => {
  		const id = wrap.getAttribute( 'data-alc-field' );
  		if ( wrap.querySelector( 'input[type="radio"]' ) ) {
  			const checked = wrap.querySelector( 'input[type="radio"]:checked' );
  			values[ id ] = { kind: 'radio', value: checked ? checked.value : '' };
  			return;
  		}
  		const boxes = wrap.querySelectorAll( 'input[type="checkbox"]' );
  		if ( boxes.length ) {
  			values[ id ] = { kind: 'checks', value: Array.from( boxes ).filter( ( b ) => b.checked ).map( ( b ) => b.value ) };
  			return;
  		}
  		const input = wrap.querySelector( 'input, select, textarea' );
  		if ( input ) {
  			values[ id ] = { kind: 'input', value: input.value };
  		}
  	} );
  	return values;
  }

  /** Re-apply a snapshot to a fresh fragment; each touched control dispatches input+change so the engine recomputes. */
  export function restoreValues( root, values ) {
  	Object.keys( values ).forEach( ( id ) => {
  		const snap = values[ id ];
  		const wrap = root.querySelector( `[data-alc-field="${ id }"]` );
  		if ( ! wrap ) {
  			return; // field removed by the edit — nothing to restore
  		}
  		let touched = null;
  		if ( 'radio' === snap.kind ) {
  			wrap.querySelectorAll( 'input[type="radio"]' ).forEach( ( r ) => {
  				const on = r.value === snap.value;
  				if ( r.checked !== on ) {
  					r.checked = on;
  					touched = on ? r : touched || r;
  				}
  			} );
  		} else if ( 'checks' === snap.kind ) {
  			wrap.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( b ) => {
  				const on = snap.value.indexOf( b.value ) !== -1;
  				if ( b.checked !== on ) {
  					b.checked = on;
  					touched = b;
  				}
  			} );
  		} else {
  			const input = wrap.querySelector( 'input, select, textarea' );
  			if ( input && input.value !== snap.value ) {
  				input.value = snap.value;
  				touched = input;
  			}
  		}
  		if ( touched ) {
  			touched.dispatchEvent( new Event( 'input', { bubbles: true } ) );
  			touched.dispatchEvent( new Event( 'change', { bubbles: true } ) );
  		}
  	} );
  }

  export default function LiveCanvas( { calculatorId } ) {
  	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
  	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
  	const [ device, setDevice ] = useState( 'desktop' );
  	const [ busy, setBusy ] = useState( false );
  	const [ failed, setFailed ] = useState( false );
  	const [ tick, setTick ] = useState( 0 ); // manual retry / reset trigger
  	const hostRef = useRef( null );
  	const scrollRef = useRef( null );
  	const seqRef = useRef( 0 ); // last issued sequence token
  	const appliedSeqRef = useRef( 0 ); // last APPLIED sequence token
  	const skipRestoreRef = useRef( false ); // set by "Reset values"

  	useEffect( () => {
  		const seq = ++seqRef.current;
  		const delay = 0 === appliedSeqRef.current ? 0 : 400; // first paint immediate, edits debounced (spec §2.2)
  		const timer = window.setTimeout( () => {
  			setBusy( true );
  			renderCalculator( { calculatorId, fields, settings } )
  				.then( ( res ) => {
  					if ( seq <= appliedSeqRef.current ) {
  						return; // stale success — a newer render already applied; discard
  					}
  					appliedSeqRef.current = seq;
  					const host = hostRef.current;
  					if ( ! host ) {
  						return;
  					}
  					const keep = skipRestoreRef.current ? {} : snapshotValues( host );
  					skipRestoreRef.current = false;
  					host.innerHTML = res.html; // trusted: manage_options-gated endpoint, canonical renderer output
  					const rootEl = host.querySelector( '.alc-calculator' );
  					if ( rootEl && window.AlovioCalc && window.AlovioCalc.init ) {
  						window.AlovioCalc.init( rootEl );
  					}
  					restoreValues( host, keep );
  					// Studio guard: the fragment's quote form must not create real entries.
  					host.querySelectorAll( '.alc-quote__submit' ).forEach( ( b ) => {
  						b.disabled = true;
  						b.title = __( 'Disabled in the studio — use "Open full preview" to test quotes.', 'alovio-calculator' );
  					} );
  					setFailed( false );
  				} )
  				.catch( () => {
  					if ( seq === seqRef.current ) {
  						setFailed( true ); // latest request failed; last good render stays on screen
  					}
  				} )
  				.finally( () => setBusy( false ) );
  		}, delay );
  		return () => window.clearTimeout( timer );
  		// eslint-disable-next-line react-hooks/exhaustive-deps
  	}, [ fields, settings, tick ] );

  	const resetValues = () => {
  		skipRestoreRef.current = true;
  		setTick( ( t ) => t + 1 ); // force a fresh render without value restore
  	};

  	const width = ( DEVICES.find( ( d ) => d.id === device ) || DEVICES[ 0 ] ).width;

  	return (
  		<div className="alcb-canvas-col">
  			<CanvasToolbar device={ device } onDevice={ setDevice } onResetValues={ resetValues } busy={ busy } />
  			{ failed && (
  				<div className="alcb-render-error" role="alert">
  					<span>{ __( 'Live render failed — showing the last good state.', 'alovio-calculator' ) }</span>
  					<button onClick={ () => setTick( ( t ) => t + 1 ) }>{ __( 'Retry', 'alovio-calculator' ) }</button>
  				</div>
  			) }
  			{ ! fields.length && (
  				<p className="alcb-canvas-hint">{ __( 'Add a field from the left to get started.', 'alovio-calculator' ) }</p>
  			) }
  			<div className="alcb-canvas" ref={ scrollRef }>
  				<div className="alcb-sheet" style={ { maxWidth: width } }>
  					<div ref={ hostRef } className="alcb-fragment"></div>
  				</div>
  			</div>
  		</div>
  	);
  }
  ```
- [ ] **Step 4: StudioShell wiring** — in `src/builder/StudioShell.jsx`: add `import LiveCanvas from './LiveCanvas';`; the workspace becomes (interim `Canvas` moves under the palette as the structure/selection list until the chunk-3 overlay):
  ```jsx
  <div className="alcb-col alcb-col--left">
  	<FieldPalette />
  	{ /* INTERIM until chunk 3: selection/reorder still lives here. */ }
  	<span className="alcb-sec-label">{ __( 'Structure', 'alovio-calculator' ) }</span>
  	<Canvas />
  </div>
  <div className="alcb-col alcb-col--center alcb-col--canvas"><LiveCanvas calculatorId={ calculatorId } /></div>
  ```
  (right column unchanged).
- [ ] **Step 5: Append builder.scss section 05**:
  ```scss
  // ── 05 · Canvas column: toolbar, sheet, fragment resets, error strip
  .alcb-col--canvas { padding: 0; overflow: hidden; }
  .alcb-canvas-col { display: flex; flex-direction: column; min-height: 0; height: 100%; }
  .alcb-toolbar {
  	margin: 16px 22px 0; padding: 8px 12px; flex: none;
  	background: var(--alcb-coal-2); border-radius: var(--alcb-r);
  	display: flex; align-items: center; gap: 12px; color: #d6d3d1; flex-wrap: wrap;
  }
  .alcb-devices { display: inline-flex; border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 999px; overflow: hidden; }
  .alcb-devices button {
  	padding: 5px 13px; font-size: 12px; font-weight: 700; color: #a8a29e;
  	background: transparent; border: none; cursor: pointer;
  }
  .alcb-devices button.is-on { background: rgba(249, 115, 22, 0.18); color: #fdba74; }
  .alcb-theme-pick { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 700; color: #a8a29e; }
  .alcb-theme-pick select {
  	background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 999px;
  	color: #e7e5e4; font-size: 12px; font-weight: 700; min-height: 0; height: 28px; padding: 0 24px 0 10px; margin: 0; max-width: none;
  }
  .alcb-toolbar .alcb-grow { flex: 1; }
  .alcb-tool-btn {
  	height: 28px; padding: 0 12px; border-radius: 999px; cursor: pointer;
  	background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.14);
  	color: #e7e5e4; font-size: 12px; font-weight: 700;
  }
  .alcb-tool-btn:hover:not(:disabled) { border-color: rgba(249, 115, 22, 0.55); color: #fdba74; }
  .alcb-render-error {
  	margin: 10px 22px 0; padding: 8px 14px; border-radius: var(--alcb-r-sm);
  	background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-weight: 600;
  	display: flex; align-items: center; justify-content: space-between; gap: 10px; flex: none;
  }
  .alcb-render-error button { border: none; background: none; color: var(--alcb-flame-deep); font-weight: 800; cursor: pointer; }
  .alcb-canvas-hint { margin: 10px 22px 0; color: var(--alcb-ink-3); flex: none; }
  .alcb-canvas { flex: 1; overflow-y: auto; padding: 16px 22px 22px; }
  .alcb-sheet {
  	position: relative; // overlay (chunk 3) anchors to this
  	margin: 0 auto; background: var(--alcb-panel);
  	border: 1px solid var(--alcb-line); border-radius: 16px; box-shadow: var(--alcb-shadow);
  	padding: 26px 30px; transition: max-width 0.2s ease;
  }
  // Neutralise wp-admin form chrome inside the real fragment so it matches the site.
  .alcb-fragment select { max-width: none; min-height: 0; }
  .alcb-fragment input[type="checkbox"], .alcb-fragment input[type="radio"] { margin: 0; }
  ```
- [ ] **Step 6: Verify** — `npm run build` clean; `npm test` green; wp-env: open the studio → the REAL calculator renders in the sheet (theme applied); typing a number updates the total instantly (client-side engine); structural edits (add field via palette, edit label in right panel) re-render after ~400 ms and typed values survive; theme quick-switch re-renders and is undoable (⌘Z); Desktop/Tablet/Mobile constrain the sheet; Reset values returns defaults; "Open full preview" opens the /preview page; stop `wp-env` (or block the request in devtools) → error strip + Retry, last render still visible; quote submit button disabled inside the canvas.
- [ ] **Step 7: Commit**
  ```bash
  git add src/builder/LiveCanvas.jsx src/builder/CanvasToolbar.jsx src/builder/api.js src/builder/StudioShell.jsx src/builder/builder.scss
  git commit -m "studio: LiveCanvas — debounced /render inject + real-engine init, value persistence, seq token, toolbar"
  ```

### Task 2.5: Chunk 2 gates

**Files:** none.

- [ ] **Step 1: Run all gates** — `vendor/bin/phpunit`, `npm test`, `vendor/bin/phpcs`, `npm run build` — all green/clean before chunk 3.

## Chunk 3: CanvasOverlay — selection, ops, IF pills, error badges, DnD, `describe.js`

### Task 3.1: `describe.js` — human-readable condition summaries (tests first)

**Files:**
- Test: `src/builder/__tests__/describe.test.js` (new)
- Create: `src/builder/describe.js`

Donor: `/Users/tahir/woo-checkout-fields/src/builder/describe.js`. Deltas vs donor: the `sources` parameter and all `@…` context-token branches are REMOVED (our controllers are sibling fields only, incl. `formula` — the running total); `valueLabel` resolves toggle → On/Off and choice `opt_` slugs → option labels; the operator map gains `gte ≥`, `lte ≤`, `is_empty`, `is_not_empty` (no value rendered for the empty ops); strings go through `__()` with the literal `'alovio-calculator'` domain (`@wordpress/i18n` resolves standalone under Jest — verified); `describeRule` is exported separately for reuse.

- [ ] **Step 1: Write the failing tests** — create `src/builder/__tests__/describe.test.js`:
  ```js
  import { describeCondition, describeRule, conditionAction } from '../describe';

  const fields = [
  	{ id: 'area', type: 'slider', label: 'Area (m²)' },
  	{
  		id: 'service', type: 'radio', label: 'Service',
  		options: [ { value: 'opt_std', label: 'Standard' }, { value: 'opt_deep', label: 'Deep clean' } ],
  	},
  	{ id: 'express', type: 'toggle', label: 'Express' },
  	{ id: 'total', type: 'formula', label: 'Estimated price' },
  ];
  const f = ( conditions, extra = {} ) => ( { id: 'x', type: 'heading', conditions, ...extra } );

  describe( 'describeRule', () => {
  	it( 'renders numeric operators as symbols', () => {
  		expect( describeRule( { field: 'area', operator: 'gte', value: '100' }, fields ) ).toBe( 'Area (m²) ≥ 100' );
  		expect( describeRule( { field: 'area', operator: 'lt', value: '5' }, fields ) ).toBe( 'Area (m²) < 5' );
  	} );
  	it( 'resolves option slugs to labels', () => {
  		expect( describeRule( { field: 'service', operator: 'is', value: 'opt_deep' }, fields ) ).toBe( 'Service is Deep clean' );
  	} );
  	it( 'renders toggle values as On/Off', () => {
  		expect( describeRule( { field: 'express', operator: 'is', value: '1' }, fields ) ).toBe( 'Express is On' );
  		expect( describeRule( { field: 'express', operator: 'is', value: '' }, fields ) ).toBe( 'Express is Off' );
  	} );
  	it( 'omits the value for presence operators', () => {
  		expect( describeRule( { field: 'area', operator: 'is_empty', value: '' }, fields ) ).toBe( 'Area (m²) is empty' );
  	} );
  	it( 'supports formula (total) controllers and falls back to the raw id when unknown', () => {
  		expect( describeRule( { field: 'total', operator: 'gt', value: '500' }, fields ) ).toBe( 'Estimated price > 500' );
  		expect( describeRule( { field: 'ghost', operator: 'is', value: '3' }, fields ) ).toBe( 'ghost is 3' );
  	} );
  } );

  describe( 'describeCondition', () => {
  	it( 'is empty without rules', () => {
  		expect( describeCondition( f( [] ), fields ) ).toBe( '' );
  		expect( describeCondition( { id: 'x', type: 'heading' }, fields ) ).toBe( '' );
  	} );
  	it( 'shows the first rule plus a connector count', () => {
  		const field = f(
  			[ { field: 'area', operator: 'gt', value: '100' }, { field: 'express', operator: 'is', value: '1' } ],
  			{ conditionMatch: 'any' }
  		);
  		expect( describeCondition( field, fields ) ).toBe( 'Area (m²) > 100 OR +1' );
  		field.conditionMatch = 'all';
  		expect( describeCondition( field, fields ) ).toBe( 'Area (m²) > 100 AND +1' );
  	} );
  } );

  describe( 'conditionAction', () => {
  	it( 'maps the action to its chip word (SHOW default)', () => {
  		expect( conditionAction( {} ) ).toBe( 'SHOW' );
  		expect( conditionAction( { conditionAction: 'hide' } ) ).toBe( 'HIDE' );
  		expect( conditionAction( { conditionAction: 'require' } ) ).toBe( 'REQUIRE' );
  	} );
  } );
  ```
- [ ] **Step 2: Run and watch it fail** — `npm test -- describe` → expected: `Cannot find module '../describe'`.
- [ ] **Step 3: Create `src/builder/describe.js`** (full code):
  ```js
  /**
   * Human summaries of a field's conditional rules for the canvas IF pills
   * (spec §2.3). Pure module — Jest-tested. Ported from Checkout Fields'
   * describe.js, adapted to our operator set and sibling-only controllers
   * (incl. formula/total; no @context tokens).
   */
  import { __ } from '@wordpress/i18n';

  const NO_VALUE_OPS = [ 'is_empty', 'is_not_empty' ];

  function ops() {
  	return {
  		is: __( 'is', 'alovio-calculator' ),
  		is_not: __( 'is not', 'alovio-calculator' ),
  		contains: __( 'contains', 'alovio-calculator' ),
  		gt: '>',
  		gte: '≥',
  		lt: '<',
  		lte: '≤',
  		is_empty: __( 'is empty', 'alovio-calculator' ),
  		is_not_empty: __( 'is not empty', 'alovio-calculator' ),
  	};
  }

  function controllerFor( rule, fields ) {
  	return ( fields || [] ).find( ( x ) => x.id === rule.field ) || null;
  }

  function sourceLabel( rule, fields ) {
  	const c = controllerFor( rule, fields );
  	return c ? c.label || c.type : rule.field || '';
  }

  function valueLabel( rule, fields ) {
  	if ( NO_VALUE_OPS.indexOf( rule.operator ) !== -1 ) {
  		return '';
  	}
  	const c = controllerFor( rule, fields );
  	if ( c && 'toggle' === c.type ) {
  		return '1' === rule.value ? __( 'On', 'alovio-calculator' ) : __( 'Off', 'alovio-calculator' );
  	}
  	if ( c && Array.isArray( c.options ) ) {
  		const opt = c.options.find( ( o ) => o.value === rule.value );
  		if ( opt ) {
  			return opt.label || opt.value;
  		}
  	}
  	return String( rule.value ?? '' );
  }

  /** One rule as a sentence fragment, e.g. "Area (m²) ≥ 100". */
  export function describeRule( rule, fields ) {
  	const op = ops()[ rule.operator ] || rule.operator;
  	return `${ sourceLabel( rule, fields ) } ${ op } ${ valueLabel( rule, fields ) }`.trim();
  }

  /** One-line summary: first rule + "AND/OR +n". Empty string when unconditioned. */
  export function describeCondition( field, fields ) {
  	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
  	if ( ! rules.length ) {
  		return '';
  	}
  	let txt = describeRule( rules[ 0 ], fields );
  	if ( rules.length > 1 ) {
  		const joiner = 'any' === field.conditionMatch ? __( 'OR', 'alovio-calculator' ) : __( 'AND', 'alovio-calculator' );
  		txt += ` ${ joiner } +${ rules.length - 1 }`;
  	}
  	return txt;
  }

  /** Action word for the pill: SHOW | HIDE | REQUIRE. */
  export function conditionAction( field ) {
  	const a = field.conditionAction || 'show';
  	if ( 'hide' === a ) {
  		return __( 'HIDE', 'alovio-calculator' );
  	}
  	if ( 'require' === a ) {
  		return __( 'REQUIRE', 'alovio-calculator' );
  	}
  	return __( 'SHOW', 'alovio-calculator' );
  }
  ```
- [ ] **Step 4: Run** — `npm test` → all green.
- [ ] **Step 5: Commit**
  ```bash
  git add src/builder/describe.js src/builder/__tests__/describe.test.js
  git commit -m "studio: describe.js — condition summaries for IF pills (our operators, option labels)"
  ```

### Task 3.2: CanvasOverlay — rect tracking, selection, hover ops, pills, badges

**Files:**
- Create: `src/builder/CanvasOverlay.jsx`
- Modify: `src/builder/LiveCanvas.jsx` (applied-tick state + overlay mount), `src/builder/StudioShell.jsx` (unmount interim `Canvas`), `src/builder/builder.scss` (append section 06)

Architecture (all new logic — full code): the overlay is an absolutely-positioned, `pointer-events: none` layer inside `.alcb-sheet` (the fragment's positioned ancestor). Field boxes come from `getBoundingClientRect()` relative to the fragment host, re-measured on: applied render, field-list change, `ResizeObserver` on the host, **scroll on the canvas scroll container**, window resize, and `input`/`change` inside the fragment (typing can flip conditional visibility outside React). Click/hover listeners attach to `.alcb-sheet` (the host's parent), so moving the pointer onto the ops toolbar (which lives in the overlay, not the fragment) does not drop the hover. Inputs stay fully interactive: selection uses plain bubbling clicks with no `preventDefault`.

- [ ] **Step 1: Create `src/builder/CanvasOverlay.jsx`** (full code):
  ```jsx
  import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
  import { useSelect, useDispatch } from '@wordpress/data';
  import { __ } from '@wordpress/i18n';
  import { STORE } from './store';
  import { describeCondition, conditionAction } from './describe';
  import { validateExpression } from './formula-validation';

  // Drag payload MIME keys. PaletteV2 (chunk 4) sets TYPE_MIME on its items;
  // the drop side below already handles both.
  export const REORDER_MIME = 'alovio-calc/reorder';
  export const TYPE_MIME = 'alovio-calc/field-type';

  const box = ( r ) => ( { top: r.top, left: r.left, width: r.width, height: r.height } );

  export default function CanvasOverlay( { hostRef, scrollRef, renderTick } ) {
  	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
  	const selectedId = useSelect( ( select ) => select( STORE ).getSelectedId(), [] );
  	const { selectField, removeField, duplicateField, reorder, insertAt } = useDispatch( STORE );
  	const [ rects, setRects ] = useState( {} );
  	const [ hoverId, setHoverId ] = useState( null );
  	const [ dragging, setDragging ] = useState( false );
  	const [ insertLine, setInsertLine ] = useState( null ); // { index, y }

  	const measure = useCallback( () => {
  		const host = hostRef.current;
  		if ( ! host ) {
  			return;
  		}
  		const base = host.getBoundingClientRect();
  		const next = {};
  		host.querySelectorAll( '[data-alc-field]' ).forEach( ( el ) => {
  			if ( el.hidden ) {
  				return; // hidden by conditions — no box, no ops (spec §2.3)
  			}
  			const r = el.getBoundingClientRect();
  			next[ el.getAttribute( 'data-alc-field' ) ] = {
  				top: r.top - base.top,
  				left: r.left - base.left,
  				width: r.width,
  				height: r.height,
  			};
  		} );
  		setRects( next );
  	}, [ hostRef ] );

  	useEffect( () => {
  		measure();
  	}, [ renderTick, fields, measure ] );

  	useEffect( () => {
  		const host = hostRef.current;
  		const scroller = scrollRef.current;
  		if ( ! host ) {
  			return undefined;
  		}
  		const schedule = () => window.requestAnimationFrame( measure );
  		const ro = new window.ResizeObserver( schedule );
  		ro.observe( host );
  		host.addEventListener( 'input', schedule ); // engine-driven visibility flips
  		host.addEventListener( 'change', schedule );
  		if ( scroller ) {
  			scroller.addEventListener( 'scroll', schedule, { passive: true } );
  		}
  		window.addEventListener( 'resize', schedule );
  		return () => {
  			ro.disconnect();
  			host.removeEventListener( 'input', schedule );
  			host.removeEventListener( 'change', schedule );
  			if ( scroller ) {
  				scroller.removeEventListener( 'scroll', schedule );
  			}
  			window.removeEventListener( 'resize', schedule );
  		};
  	}, [ hostRef, scrollRef, measure ] );

  	// Click-to-select + hover tracking on the sheet (host parent), so the
  	// overlay toolbar keeps hover. Inputs stay interactive: no preventDefault.
  	useEffect( () => {
  		const host = hostRef.current;
  		const sheet = host && host.parentElement;
  		if ( ! sheet ) {
  			return undefined;
  		}
  		const onClick = ( e ) => {
  			if ( e.target.closest( '.alcb-ops' ) ) {
  				return; // toolbar buttons handle themselves
  			}
  			const wrap = e.target.closest( '[data-alc-field]' );
  			if ( wrap ) {
  				selectField( wrap.getAttribute( 'data-alc-field' ) );
  			}
  		};
  		const onOver = ( e ) => {
  			if ( e.target.closest( '.alcb-ops' ) ) {
  				return; // keep the current hover while on the toolbar
  			}
  			const wrap = e.target.closest( '[data-alc-field]' );
  			setHoverId( wrap ? wrap.getAttribute( 'data-alc-field' ) : null );
  		};
  		const onLeave = () => setHoverId( null );
  		sheet.addEventListener( 'click', onClick );
  		sheet.addEventListener( 'mouseover', onOver );
  		sheet.addEventListener( 'mouseleave', onLeave );
  		return () => {
  			sheet.removeEventListener( 'click', onClick );
  			sheet.removeEventListener( 'mouseover', onOver );
  			sheet.removeEventListener( 'mouseleave', onLeave );
  		};
  	}, [ hostRef, selectField ] );

  	/** Map a pointer Y to an insertion index over the VISIBLE fields (midpoint rule). */
  	const insertionFromY = useCallback( ( clientY ) => {
  		const host = hostRef.current;
  		if ( ! host ) {
  			return { index: fields.length, y: 0 };
  		}
  		const y = clientY - host.getBoundingClientRect().top;
  		const visible = fields.filter( ( f ) => rects[ f.id ] );
  		for ( let i = 0; i < visible.length; i++ ) {
  			const r = rects[ visible[ i ].id ];
  			if ( y < r.top + r.height / 2 ) {
  				return { index: fields.indexOf( visible[ i ] ), y: r.top };
  			}
  		}
  		const last = visible[ visible.length - 1 ];
  		return { index: fields.length, y: last ? rects[ last.id ].top + rects[ last.id ].height : 0 };
  	}, [ hostRef, fields, rects ] );

  	// DnD drop side: field reorder (grip) AND palette insertion (INSERT_AT).
  	useEffect( () => {
  		const host = hostRef.current;
  		const sheet = host && host.parentElement;
  		if ( ! sheet ) {
  			return undefined;
  		}
  		const accepts = ( e ) => {
  			const types = e.dataTransfer ? Array.from( e.dataTransfer.types ) : [];
  			return types.indexOf( REORDER_MIME ) !== -1 || types.indexOf( TYPE_MIME ) !== -1;
  		};
  		const onDragOver = ( e ) => {
  			if ( ! accepts( e ) ) {
  				return;
  			}
  			e.preventDefault(); // required to allow dropping
  			setInsertLine( insertionFromY( e.clientY ) );
  		};
  		const onDrop = ( e ) => {
  			if ( ! accepts( e ) ) {
  				return;
  			}
  			e.preventDefault();
  			const target = insertionFromY( e.clientY );
  			const type = e.dataTransfer.getData( TYPE_MIME );
  			const from = e.dataTransfer.getData( REORDER_MIME );
  			if ( type ) {
  				insertAt( type, target.index ); // palette drag → INSERT_AT (drag source: chunk 4)
  			} else if ( '' !== from ) {
  				const f = Number( from );
  				const to = target.index > f ? target.index - 1 : target.index;
  				if ( to !== f ) {
  					reorder( f, to );
  				}
  			}
  			setInsertLine( null );
  			setDragging( false );
  		};
  		const onDragLeave = ( e ) => {
  			if ( ! sheet.contains( e.relatedTarget ) ) {
  				setInsertLine( null );
  			}
  		};
  		sheet.addEventListener( 'dragover', onDragOver );
  		sheet.addEventListener( 'drop', onDrop );
  		sheet.addEventListener( 'dragleave', onDragLeave );
  		return () => {
  			sheet.removeEventListener( 'dragover', onDragOver );
  			sheet.removeEventListener( 'drop', onDrop );
  			sheet.removeEventListener( 'dragleave', onDragLeave );
  		};
  	}, [ hostRef, insertionFromY, insertAt, reorder ] );

  	// Formula-error badges reuse the existing live validator (spec §2.3).
  	const formulaErrors = useMemo( () => {
  		const map = {};
  		fields
  			.filter( ( f ) => 'formula' === f.type && '' !== ( f.expression || '' ).trim() )
  			.forEach( ( f ) => {
  				const r = validateExpression( f.expression, f.id, fields );
  				if ( ! r.ok ) {
  					map[ f.id ] = r.error.message;
  				}
  			} );
  		return map;
  	}, [ fields ] );

  	const opsId = hoverId || selectedId; // hover wins; selection is the keyboard fallback
  	const opsIndex = opsId ? fields.findIndex( ( f ) => f.id === opsId ) : -1;
  	const opsRect = opsId ? rects[ opsId ] : null;

  	return (
  		<div className="alcb-overlay">
  			{ selectedId && rects[ selectedId ] && <div className="alcb-outline" style={ box( rects[ selectedId ] ) }></div> }

  			{ fields.map( ( f ) => {
  				const r = rects[ f.id ];
  				if ( ! r ) {
  					return null;
  				}
  				const summary = describeCondition( f, fields );
  				return (
  					<div key={ f.id }>
  						{ '' !== summary && (
  							<span
  								className="alcb-if-pill"
  								style={ { top: r.top + r.height - 11, left: r.left + 10 } }
  								title={ `${ summary } → ${ conditionAction( f ) }` }
  							>
  								{ __( 'IF', 'alovio-calculator' ) } · { summary } → { conditionAction( f ) }
  							</span>
  						) }
  						{ formulaErrors[ f.id ] && (
  							<span
  								className="alcb-err-badge"
  								style={ { top: r.top - 9, left: r.left + r.width - 26 } }
  								title={ formulaErrors[ f.id ] }
  								role="img"
  								aria-label={ __( 'Formula error', 'alovio-calculator' ) }
  							>
  								!
  							</span>
  						) }
  					</div>
  				);
  			} ) }

  			{ opsRect && -1 !== opsIndex && ! dragging && (
  				<div className="alcb-ops" style={ { top: opsRect.top - 14, left: opsRect.left + opsRect.width - 160 } }>
  					<span
  						className="alcb-op alcb-op--grip"
  						draggable
  						title={ __( 'Drag to reorder', 'alovio-calculator' ) }
  						onDragStart={ ( e ) => {
  							e.dataTransfer.setData( REORDER_MIME, String( opsIndex ) );
  							e.dataTransfer.effectAllowed = 'move';
  							selectField( opsId );
  							setDragging( true );
  						} }
  						onDragEnd={ () => {
  							setDragging( false );
  							setInsertLine( null );
  						} }
  					>
  						⠿
  					</span>
  					<button className="alcb-op" disabled={ 0 === opsIndex } aria-label={ __( 'Move up', 'alovio-calculator' ) } onClick={ () => reorder( opsIndex, opsIndex - 1 ) }>↑</button>
  					<button className="alcb-op" disabled={ opsIndex === fields.length - 1 } aria-label={ __( 'Move down', 'alovio-calculator' ) } onClick={ () => reorder( opsIndex, opsIndex + 1 ) }>↓</button>
  					<button className="alcb-op" aria-label={ __( 'Duplicate', 'alovio-calculator' ) } onClick={ () => duplicateField( opsId ) }>⧉</button>
  					<button className="alcb-op alcb-op--danger" aria-label={ __( 'Delete', 'alovio-calculator' ) } onClick={ () => removeField( opsId ) }>✕</button>
  				</div>
  			) }

  			{ insertLine && <div className="alcb-insert-line" style={ { top: insertLine.y - 2 } }></div> }
  		</div>
  	);
  }
  ```
- [ ] **Step 2: Mount it from `LiveCanvas.jsx`** — three exact edits:
  1. Add imports: `import CanvasOverlay from './CanvasOverlay';`
  2. Add applied-tick state under the existing `useState` lines: `const [ appliedTick, setAppliedTick ] = useState( 0 );` and, in the render success handler, after the quote-guard `forEach` block add: `setAppliedTick( ( t ) => t + 1 );`
  3. The sheet block becomes:
     ```jsx
     <div className="alcb-sheet" style={ { maxWidth: width } }>
     	<div ref={ hostRef } className="alcb-fragment"></div>
     	<CanvasOverlay hostRef={ hostRef } scrollRef={ scrollRef } renderTick={ appliedTick } />
     </div>
     ```
- [ ] **Step 3: Unmount the interim structure list** — in `StudioShell.jsx` remove the `<span className="alcb-sec-label">…Structure…</span>` + `<Canvas />` lines and the `import Canvas from './Canvas';` line (file itself is deleted in Task 4.7). The left column is `<FieldPalette />` only again.
- [ ] **Step 4: Append builder.scss section 06**:
  ```scss
  // ── 06 · Overlay: outline, ops toolbar, IF pill, error badge, insert line
  .alcb-overlay { position: absolute; inset: 26px 30px; pointer-events: none; } // matches .alcb-sheet padding
  .alcb-outline {
  	position: absolute; border: 2px solid var(--alcb-flame); border-radius: 10px;
  	box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.12); margin: -4px; padding: 2px;
  }
  .alcb-ops { position: absolute; display: flex; gap: 4px; pointer-events: auto; z-index: 3; }
  .alcb-op {
  	width: 26px; height: 26px; border-radius: 7px; background: var(--alcb-coal); color: #fff;
  	border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;
  	font-size: 12px; box-shadow: 0 4px 10px -2px rgba(28, 25, 23, 0.4);
  }
  .alcb-op:hover:not(:disabled) { background: var(--alcb-flame-deep); }
  .alcb-op:disabled { opacity: 0.35; cursor: default; }
  .alcb-op--grip { cursor: grab; }
  .alcb-op--danger:hover { background: var(--alcb-red); }
  .alcb-if-pill {
  	position: absolute; z-index: 2; pointer-events: auto;
  	display: inline-flex; align-items: center; gap: 6px;
  	font-size: 10.5px; font-weight: 800; letter-spacing: 0.03em;
  	background: var(--alcb-flame-soft); color: var(--alcb-flame-deep);
  	border: 1px solid var(--alcb-flame-border); border-radius: 999px; padding: 3px 9px;
  	white-space: nowrap; max-width: 85%; overflow: hidden; text-overflow: ellipsis;
  }
  .alcb-err-badge {
  	position: absolute; z-index: 3; pointer-events: auto;
  	width: 18px; height: 18px; border-radius: 50%;
  	background: var(--alcb-red); color: #fff; font-weight: 800; font-size: 12px; line-height: 18px; text-align: center;
  	box-shadow: 0 2px 6px rgba(28, 25, 23, 0.35);
  }
  .alcb-insert-line {
  	position: absolute; left: -10px; right: -10px; height: 3px; border-radius: 2px;
  	background: var(--alcb-flame); box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.25);
  }
  ```
  Note the overlay `inset` mirrors `.alcb-sheet`'s `26px 30px` padding so overlay coordinates (measured relative to `.alcb-fragment`) line up — if the sheet padding ever changes, change both.
- [ ] **Step 5: Verify** — `npm run build` clean; `npm test` green; wp-env manual pass: click a field in the canvas → flame outline + right panel shows it while the input still receives focus/typing; hover shows ⠿/↑/↓/⧉/✕ (also visible on the selected field for keyboard users, buttons tabbable); ⧉ duplicates, ✕ deletes, ↑↓ reorder — all undoable; grip-drag shows the insertion line between fields and drops reorder correctly (including drag-below-last); a conditioned field shows the `IF · … → SHOW` pill matching its rules; typing an unknown `{ref}` into a formula shows the red `!` badge at the field's top-right (tooltip = validator message); resize the window / switch device widths → overlays track their fields.
- [ ] **Step 6: Commit**
  ```bash
  git add src/builder/CanvasOverlay.jsx src/builder/LiveCanvas.jsx src/builder/StudioShell.jsx src/builder/builder.scss
  git commit -m "studio: canvas overlay — selection, hover ops, IF pills, formula badges, DnD reorder + palette drop"
  ```

### Task 3.3: Chunk 3 gates

**Files:** none.

- [ ] **Step 1: Run all gates** — `vendor/bin/phpunit`, `npm test`, `vendor/bin/phpcs`, `npm run build` — all green/clean before chunk 4.

## Chunk 4: PaletteV2 + icons, SettingsPanel + panels, draft recovery, retire v1 components

Task order matters: schema (4.2) lands before the Options UI that edits it; all `panels/` files land before `SettingsPanel` imports them (4.5). New files are unreferenced until wired, so every commit still builds green.

### Task 4.1: `icons.js` + `PaletteV2` + template fields in the builder global

**Files:**
- Create: `src/builder/icons.js`, `src/builder/PaletteV2.jsx`
- Modify: `includes/Admin/BuilderAssets.php` (templates gain `fields`), `src/builder/StudioShell.jsx` (left column swap), `src/builder/builder.scss` (append section 07)

- [ ] **Step 1: Create `src/builder/icons.js`** — 18 inline SVGs (spec §2.5: no unicode glyphs, no external assets; each ≤ ~180 bytes). Types for chunks 5–7 (`textarea/date/email/phone/url/repeater`) ship now; the palette filters by `ALOVIO_CALC_BUILDER.fieldTypes`, so they stay invisible until their chunks register them. Full code:
  ```jsx
  /** Inline SVG type icons for the studio palette + settings panel (spec §2.5). */
  const P = {
  	viewBox: '0 0 24 24',
  	width: 16,
  	height: 16,
  	fill: 'none',
  	stroke: 'currentColor',
  	strokeWidth: 1.8,
  	strokeLinecap: 'round',
  	strokeLinejoin: 'round',
  	'aria-hidden': true,
  };

  export const ICONS = {
  	number: <svg { ...P }><path d="M4 9h16M4 15h16M10 4 8 20M16 4l-2 16" /></svg>,
  	slider: <svg { ...P }><path d="M3 12h18" /><circle cx="14" cy="12" r="3.2" /></svg>,
  	quantity: <svg { ...P }><rect x="3" y="8" width="18" height="8" rx="2" /><path d="M6.5 12h3M15 10.5v3M13.5 12h3" /></svg>,
  	text: <svg { ...P }><path d="M4 6h16M4 12h16M4 18h9" /></svg>,
  	textarea: <svg { ...P }><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M7 10h10M7 14h6" /></svg>,
  	date: <svg { ...P }><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>,
  	email: <svg { ...P }><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>,
  	phone: <svg { ...P }><path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 4 5a2 2 0 0 1 2-2Z" /></svg>,
  	url: <svg { ...P }><path d="M10 14a4 4 0 0 1 0-6l2-2a4 4 0 0 1 6 6l-1 1M14 10a4 4 0 0 1 0 6l-2 2a4 4 0 0 1-6-6l1-1" /></svg>,
  	select: <svg { ...P }><rect x="3" y="6" width="18" height="12" rx="2" /><path d="m13.5 11 2.5 2.5L18.5 11" /></svg>,
  	radio: <svg { ...P }><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="3" fill="currentColor" /></svg>,
  	checkbox_group: <svg { ...P }><rect x="3" y="3" width="8" height="8" rx="2" /><path d="m5 7 1.5 1.5L9.5 5" /><rect x="13" y="13" width="8" height="8" rx="2" /></svg>,
  	toggle: <svg { ...P }><rect x="3" y="8" width="18" height="8" rx="4" /><circle cx="15.5" cy="12" r="2.4" fill="currentColor" /></svg>,
  	heading: <svg { ...P }><path d="M6 4v16M18 4v16M6 12h12" /></svg>,
  	html: <svg { ...P }><path d="m9 8-4 4 4 4M15 8l4 4-4 4" /></svg>,
  	step: <svg { ...P }><path d="M6 21V4h10l-2 3.5 2 3.5H6" /></svg>,
  	formula: <svg { ...P }><path d="M18 5H8l5 7-5 7h10" /></svg>,
  	repeater: <svg { ...P }><rect x="3" y="4" width="18" height="6" rx="2" /><rect x="3" y="14" width="12" height="6" rx="2" /><path d="M19 15v4M17 17h4" /></svg>,
  };
  ```
- [ ] **Step 2: Create `src/builder/PaletteV2.jsx`** (full code; drag sets the MIME the chunk-3 overlay already accepts; plain click inserts AFTER the selected field, spec §2.3):
  ```jsx
  import { useDispatch, useSelect } from '@wordpress/data';
  import { __ } from '@wordpress/i18n';
  import { STORE } from './store';
  import { ICONS } from './icons';
  import { TYPE_MIME } from './CanvasOverlay';

  const LABELS = () => ( {
  	number: __( 'Number', 'alovio-calculator' ),
  	slider: __( 'Slider', 'alovio-calculator' ),
  	quantity: __( 'Quantity', 'alovio-calculator' ),
  	text: __( 'Text', 'alovio-calculator' ),
  	textarea: __( 'Text area', 'alovio-calculator' ),
  	date: __( 'Date', 'alovio-calculator' ),
  	email: __( 'Email', 'alovio-calculator' ),
  	phone: __( 'Phone', 'alovio-calculator' ),
  	url: __( 'Website', 'alovio-calculator' ),
  	select: __( 'Dropdown', 'alovio-calculator' ),
  	radio: __( 'Multiple choice', 'alovio-calculator' ),
  	checkbox_group: __( 'Checkboxes', 'alovio-calculator' ),
  	toggle: __( 'Toggle', 'alovio-calculator' ),
  	heading: __( 'Heading', 'alovio-calculator' ),
  	html: __( 'HTML content', 'alovio-calculator' ),
  	step: __( 'Step / Section', 'alovio-calculator' ),
  	formula: __( 'Formula', 'alovio-calculator' ),
  	repeater: __( 'Repeater', 'alovio-calculator' ),
  } );

  const CATEGORIES = () => [
  	{ key: 'inputs', label: __( 'Inputs', 'alovio-calculator' ), types: [ 'number', 'slider', 'quantity', 'text', 'textarea', 'date', 'email', 'phone', 'url' ] },
  	{ key: 'choices', label: __( 'Choices', 'alovio-calculator' ), types: [ 'select', 'radio', 'checkbox_group', 'toggle' ] },
  	{ key: 'content', label: __( 'Content', 'alovio-calculator' ), types: [ 'heading', 'html', 'step' ] },
  	{ key: 'math', label: __( 'Math', 'alovio-calculator' ), types: [ 'formula', 'repeater' ] },
  ];

  export default function PaletteV2() {
  	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
  	const selectedId = useSelect( ( select ) => select( STORE ).getSelectedId(), [] );
  	const { insertAt, insertFields } = useDispatch( STORE );
  	const available = ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.fieldTypes ) || [];
  	const templates = ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.templates ) || [];
  	const labels = LABELS();

  	/** Plain click inserts AFTER the selected field (end when none) — spec §2.3. */
  	const insertIndex = () => {
  		const i = fields.findIndex( ( f ) => f.id === selectedId );
  		return -1 === i ? fields.length : i + 1;
  	};

  	return (
  		<div className="alcb-palette" aria-label={ __( 'Field types', 'alovio-calculator' ) }>
  			{ CATEGORIES().map( ( cat ) => {
  				const types = cat.types.filter( ( t ) => available.indexOf( t ) !== -1 );
  				if ( ! types.length ) {
  					return null; // Track-B types appear here automatically in chunks 5–7
  				}
  				return (
  					<div key={ cat.key }>
  						<span className="alcb-sec-label">{ cat.label }</span>
  						<div className="alcb-ptypes">
  							{ types.map( ( type ) => (
  								<button
  									key={ type }
  									className="alcb-ptype"
  									draggable
  									onDragStart={ ( e ) => {
  										e.dataTransfer.setData( TYPE_MIME, type );
  										e.dataTransfer.effectAllowed = 'copy';
  									} }
  									onClick={ () => insertAt( type, insertIndex() ) }
  								>
  									<span className="alcb-ic">{ ICONS[ type ] || null }</span>
  									{ labels[ type ] || type }
  								</button>
  							) ) }
  						</div>
  					</div>
  				);
  			} ) }

  			{ templates.length > 0 && (
  				<div>
  					<span className="alcb-sec-label">{ __( 'Templates', 'alovio-calculator' ) }</span>
  					<div className="alcb-tpl">
  						<p>{ __( 'Insert a pre-built field set into this calculator.', 'alovio-calculator' ) }</p>
  						<div className="alcb-chips">
  							{ templates.map( ( tpl ) => (
  								<button key={ tpl.key } className="alcb-chip" title={ tpl.description } onClick={ () => insertFields( tpl.fields || [], insertIndex() ) }>
  									{ tpl.title }
  								</button>
  							) ) }
  						</div>
  					</div>
  				</div>
  			) }
  		</div>
  	);
  }
  ```
- [ ] **Step 3: Ship template fields** — in `includes/Admin/BuilderAssets.php`, the `$templates[] = array( … )` block gains one entry after `'description'`:
  ```php
  'fields'      => $preset['config']['fields'],
  ```
  (Ids/option slugs are remapped client-side by `remapFields`; the server re-normalizes on save. `TemplatePicker` ignores the extra key — New Calculator modal unchanged.)
- [ ] **Step 4: Swap the left column** — in `StudioShell.jsx`: replace `import FieldPalette from './FieldPalette';` with `import PaletteV2 from './PaletteV2';` and the left column with `<div className="alcb-col alcb-col--left"><PaletteV2 /></div>`.
- [ ] **Step 5: Append builder.scss section 07** (donor palette block, lines 122–148, renamed; grid stays 2-up):
  ```scss
  // ── 07 · Palette v2
  .alcb-ptypes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 18px; }
  .alcb-ptype {
  	min-height: 64px; border: 1px solid var(--alcb-line); border-radius: var(--alcb-r);
  	display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
  	background: #fff; font-size: 11.5px; font-weight: 700; color: var(--alcb-ink-2); cursor: pointer;
  }
  .alcb-ptype:hover { border-color: var(--alcb-flame-border); box-shadow: var(--alcb-shadow); }
  .alcb-ic {
  	width: 26px; height: 26px; border-radius: 7px; background: var(--alcb-flame-soft);
  	display: inline-flex; align-items: center; justify-content: center; color: var(--alcb-flame-deep); flex: none;
  }
  .alcb-tpl { border: 1px dashed var(--alcb-line-2); border-radius: var(--alcb-r); padding: 12px; }
  .alcb-tpl p { font-size: 11.5px; color: var(--alcb-ink-3); line-height: 1.45; margin: 0 0 9px; }
  .alcb-chips { display: flex; flex-wrap: wrap; gap: 6px; }
  .alcb-chip {
  	font-size: 11px; font-weight: 700; color: var(--alcb-flame-deep);
  	background: var(--alcb-flame-soft); border: 1px solid var(--alcb-flame-border);
  	padding: 4px 9px; border-radius: 999px; cursor: pointer;
  }
  .alcb-chip:hover { background: #fee7cd; }
  ```
- [ ] **Step 6: Verify** — `npm run build` clean, `vendor/bin/phpcs` 0; wp-env: categorised icon grid renders; click inserts after the selected field; dragging a type onto the canvas shows the insertion line and drops via `INSERT_AT`; a template chip inserts its fields with working intra-template conditions (fresh ids), undo removes the whole insertion in one step.
- [ ] **Step 7: Commit**
  ```bash
  git add src/builder/icons.js src/builder/PaletteV2.jsx src/builder/StudioShell.jsx src/builder/builder.scss includes/Admin/BuilderAssets.php
  git commit -m "studio: categorised palette with SVG icons, click-after-selected + drag insert, template insertion"
  ```

### Task 4.2: Per-option `default` flag — schema + renderer (tests first)

**Files:**
- Test: `tests/Unit/Fields/FieldSchemaTest.php` (extend), `tests/Unit/Frontend/CalculatorRendererTest.php` (extend)
- Modify: `includes/Fields/FieldSchema.php` (`normalize_options()` lines 129–149 + call site line 105), `includes/Frontend/CalculatorRenderer.php` (`render_input()` select/radio/checkbox branches)

NEW schema surface (spec §2.4) and it must land BEFORE the Options UI (Task 4.3). Semantics: `default` is stored per option; select/radio keep at most ONE default (first wins); checkbox_group allows many. The renderer emits `selected`/`checked` — initial server totals intentionally ignore option defaults (`Evaluation::run` gets empty values) because the frontend `recompute()` on init reads the checked DOM state and syncs instantly — the exact pattern toggle defaults already use. Quote-side server recompute is unaffected (it uses submitted values). Native `<select>` cannot render images; select images are stored/editable but only radio/checkbox_group render them (existing behaviour, unchanged).

- [ ] **Step 1: Failing schema tests** — append to the existing `FieldSchemaTest` class (its setUp already stubs the sanitizers its `normalize` tests need; mirror `CalculatorRendererTest::setUp()` for any missing stub):
  ```php
  public function test_option_default_flag_is_stored(): void {
  	$out = FieldSchema::normalize( [ 'fields' => [ [ 'id' => 'g', 'type' => 'checkbox_group', 'label' => 'G', 'options' => [
  		[ 'value' => 'opt_a', 'label' => 'A', 'default' => true ],
  		[ 'value' => 'opt_b', 'label' => 'B', 'default' => true ],
  		[ 'value' => 'opt_c', 'label' => 'C' ],
  	] ] ] ] );
  	$this->assertSame( [ true, true, false ], array_column( $out['fields'][0]['options'], 'default' ) );
  }

  public function test_single_choice_types_keep_at_most_one_default(): void {
  	foreach ( [ 'select', 'radio' ] as $type ) {
  		$out = FieldSchema::normalize( [ 'fields' => [ [ 'id' => 'f', 'type' => $type, 'label' => 'F', 'options' => [
  			[ 'value' => 'opt_a', 'label' => 'A', 'default' => true ],
  			[ 'value' => 'opt_b', 'label' => 'B', 'default' => true ],
  		] ] ] ] );
  		$this->assertSame( [ true, false ], array_column( $out['fields'][0]['options'], 'default' ), $type );
  	}
  }
  ```
- [ ] **Step 2: Failing renderer tests** — append to `CalculatorRendererTest`:
  ```php
  public function test_option_defaults_render_selected_and_checked(): void {
  	$config = FieldSchema::normalize( [ 'fields' => [
  		[ 'id' => 'size', 'type' => 'select', 'label' => 'Size', 'options' => [
  			[ 'value' => 'opt_s', 'label' => 'S' ],
  			[ 'value' => 'opt_m', 'label' => 'M', 'default' => true ],
  		] ],
  		[ 'id' => 'extras', 'type' => 'checkbox_group', 'label' => 'Extras', 'options' => [
  			[ 'value' => 'opt_x', 'label' => 'X', 'default' => true ],
  		] ],
  	], 'settings' => [] ] );
  	$html = CalculatorRenderer::render( 7, $config );
  	$this->assertMatchesRegularExpression( '/<option value="opt_m"[^>]*selected>/', $html );
  	$this->assertMatchesRegularExpression( '/<option value="opt_s">/', $html );
  	$this->assertMatchesRegularExpression( '/<input type="checkbox"[^>]*value="opt_x"[^>]*checked>/', $html );
  }
  ```
- [ ] **Step 3: Run** — `vendor/bin/phpunit --filter 'FieldSchemaTest|CalculatorRendererTest'` → expected: 3 new failures (`default` key absent; no `selected`/`checked` attrs).
- [ ] **Step 4: Implement `FieldSchema`** — change the call site (line 105) to `self::normalize_options( (array) ( $raw['options'] ?? [] ), $type );` and `normalize_options` to:
  ```php
  private static function normalize_options( array $rawOptions, string $type ): array {
  	$options = [];
  	$used    = [];
  	foreach ( $rawOptions as $opt ) {
  		if ( ! is_array( $opt ) ) {
  			continue;
  		}
  		$value = sanitize_key( (string) ( $opt['value'] ?? '' ) );
  		if ( '' === $value || 0 !== strpos( $value, 'opt_' ) || isset( $used[ $value ] ) ) {
  			$value = self::generate_slug( $used );
  		}
  		$used[ $value ] = true;
  		$options[]      = [
  			'value'   => $value,
  			'label'   => sanitize_text_field( (string) ( $opt['label'] ?? '' ) ),
  			'price'   => isset( $opt['price'] ) && is_numeric( $opt['price'] ) ? (float) $opt['price'] : 0.0,
  			'image'   => isset( $opt['image'] ) ? max( 0, (int) $opt['image'] ) : 0,
  			'default' => ! empty( $opt['default'] ),
  		];
  	}
  	// Single-choice fields keep at most ONE default (first wins) — spec §2.4.
  	if ( in_array( $type, [ 'select', 'radio' ], true ) ) {
  		$found = false;
  		foreach ( $options as &$o ) {
  			if ( $o['default'] && $found ) {
  				$o['default'] = false;
  			}
  			$found = $found || $o['default'];
  		}
  		unset( $o );
  	}
  	return $options;
  }
  ```
- [ ] **Step 5: Implement the renderer** — in `render_input()`: the select loop's option `sprintf` gains a 3rd placeholder:
  ```php
  $options .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $opt['value'] ), ! empty( $opt['default'] ) ? ' selected' : '', esc_html( $opt['label'] ) );
  ```
  and the radio/checkbox item `sprintf` (line 133–141) inserts `%6$s` after `value="%3$s"`:
  ```php
  $items .= sprintf(
  	'<label class="alc-choice"><input type="%1$s" name="alc_%2$s" value="%3$s"%6$s>%4$s<span class="alc-choice__label">%5$s</span></label>',
  	$type,
  	$id,
  	esc_attr( $opt['value'] ),
  	$image,
  	esc_html( $opt['label'] ),
  	! empty( $opt['default'] ) ? ' checked' : ''
  );
  ```
- [ ] **Step 6: Run** — `vendor/bin/phpunit` all green (existing option tests keep passing — `default` defaults to `false`); `vendor/bin/phpcs` 0.
- [ ] **Step 7: Commit**
  ```bash
  git add includes/Fields/FieldSchema.php includes/Frontend/CalculatorRenderer.php tests/Unit/Fields/FieldSchemaTest.php tests/Unit/Frontend/CalculatorRendererTest.php
  git commit -m "schema: per-option default flag (single for select/radio) + renderer selected/checked"
  ```

### Task 4.3: Panels part 1 — FieldGeneral, OptionsTab, FormulaTab

**Files:**
- Create: `src/builder/panels/FieldGeneral.jsx`, `src/builder/panels/OptionsTab.jsx`, `src/builder/panels/FormulaTab.jsx`
- Modify: `src/builder/builder.scss` (append section 08, first part)

`FieldGeneral` ports the per-type branches of `src/builder/FieldSettings.jsx` (this repo) MINUS everything that moved to other tabs: `ConditionEditor` (→ LogicTokens), `OptionsEditor` (→ OptionsTab), `FormulaPanel` (→ FormulaTab). `OptionsTab` extends the old `OptionsEditor.jsx` with drag-reorder, images for ALL choice types (old: radio only), and the new per-option default flag. `FormulaTab` wraps the surviving `FormulaPanel.jsx` (NOT retired).

- [ ] **Step 1: Create `src/builder/panels/FieldGeneral.jsx`** (full code):
  ```jsx
  import { TextControl, ToggleControl, TextareaControl } from '@wordpress/components';
  import { __ } from '@wordpress/i18n';

  const HAS_RANGE = [ 'number', 'slider', 'quantity' ];

  function num( v ) {
  	return '' === v || null === v || undefined === v ? null : v;
  }

  export default function FieldGeneral( { field, set } ) {
  	const summaryControl = (
  		<ToggleControl
  			label={ __( 'Show in summary', 'alovio-calculator' ) }
  			help={ __( 'List this field as a line item in the quote summary.', 'alovio-calculator' ) }
  			checked={ !! field.showInSummary }
  			onChange={ ( showInSummary ) => set( { showInSummary } ) }
  		/>
  	);

  	if ( 'heading' === field.type ) {
  		return <TextControl label={ __( 'Heading text', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />;
  	}
  	if ( 'html' === field.type ) {
  		return (
  			<>
  				<TextControl label={ __( 'Label (admin only)', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
  				<TextareaControl label={ __( 'Content (HTML allowed)', 'alovio-calculator' ) } value={ field.content || '' } onChange={ ( content ) => set( { content } ) } rows={ 5 } />
  			</>
  		);
  	}
  	if ( 'step' === field.type ) {
  		return (
  			<>
  				<TextControl label={ __( 'Step title', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
  				<TextareaControl label={ __( 'Description (optional)', 'alovio-calculator' ) } value={ field.description || '' } onChange={ ( description ) => set( { description } ) } rows={ 3 } />
  				<p className="alcb-hint">{ __( 'Splits the form into a section. With the Wizard layout (Pro), each section becomes a step.', 'alovio-calculator' ) }</p>
  			</>
  		);
  	}
  	if ( 'formula' === field.type ) {
  		return (
  			<>
  				<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
  				{ summaryControl }
  				<p className="alcb-hint">{ __( 'Edit the expression in the Formula tab. The LAST formula in the field list is shown as the grand total.', 'alovio-calculator' ) }</p>
  			</>
  		);
  	}

  	return (
  		<>
  			<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
  			<TextControl
  				label={ __( 'Help text', 'alovio-calculator' ) }
  				help={ __( 'Optional hint shown under the field.', 'alovio-calculator' ) }
  				value={ field.help || '' }
  				onChange={ ( help ) => set( { help } ) }
  			/>
  			{ 'text' === field.type && (
  				<TextControl label={ __( 'Placeholder', 'alovio-calculator' ) } value={ field.placeholder || '' } onChange={ ( placeholder ) => set( { placeholder } ) } />
  			) }
  			{ HAS_RANGE.indexOf( field.type ) !== -1 && (
  				<div className="alcb-row4">
  					<TextControl type="number" label={ __( 'Min', 'alovio-calculator' ) } value={ field.min ?? '' } onChange={ ( v ) => set( { min: num( v ) } ) } />
  					<TextControl type="number" label={ __( 'Max', 'alovio-calculator' ) } value={ field.max ?? '' } onChange={ ( v ) => set( { max: num( v ) } ) } />
  					<TextControl type="number" label={ __( 'Step', 'alovio-calculator' ) } value={ field.step ?? '' } onChange={ ( v ) => set( { step: num( v ) } ) } />
  					<TextControl type="number" label={ __( 'Default', 'alovio-calculator' ) } value={ field.default ?? '' } onChange={ ( v ) => set( { default: num( v ) } ) } />
  				</div>
  			) }
  			{ 'toggle' === field.type && (
  				<>
  					<TextControl
  						type="number"
  						step="0.01"
  						label={ __( 'Price when on', 'alovio-calculator' ) }
  						value={ 0 === field.price || field.price ? String( field.price ) : '' }
  						onChange={ ( price ) => set( { price } ) }
  					/>
  					<ToggleControl label={ __( 'On by default', 'alovio-calculator' ) } checked={ !! field.default } onChange={ ( on ) => set( { default: on } ) } />
  				</>
  			) }
  			{ summaryControl }
  		</>
  	);
  }
  ```
- [ ] **Step 2: Create `src/builder/panels/OptionsTab.jsx`** (full code):
  ```jsx
  import { useState } from '@wordpress/element';
  import { Button, TextControl, Notice } from '@wordpress/components';
  import { __ } from '@wordpress/i18n';

  /**
   * Options editor v2 (spec §2.4): drag-reorder, label+price+image for ALL
   * choice types, per-option default. New options ship WITHOUT `value` — the
   * server assigns stable opt_ slugs on save (conditions reference them).
   */
  export default function OptionsTab( { field, set } ) {
  	const options = field.options || [];
  	const single = 'select' === field.type || 'radio' === field.type;
  	const [ drag, setDrag ] = useState( null );

  	const update = ( i, patch ) => set( { options: options.map( ( o, idx ) => ( idx === i ? { ...o, ...patch } : o ) ) } );
  	const add = () => set( { options: [ ...options, { label: '', price: 0 } ] } );
  	const remove = ( i ) => set( { options: options.filter( ( _, idx ) => idx !== i ) } );
  	const move = ( from, to ) => {
  		if ( from === to || to < 0 || to >= options.length ) {
  			return;
  		}
  		const next = [ ...options ];
  		const [ m ] = next.splice( from, 1 );
  		next.splice( to, 0, m );
  		set( { options: next } );
  	};
  	const setDefault = ( i, on ) =>
  		set( { options: options.map( ( o, idx ) => ( { ...o, default: idx === i ? on : single ? false : !! o.default } ) ) } );

  	const pickImage = ( i ) => {
  		if ( ! window.wp || ! window.wp.media ) {
  			return;
  		}
  		const frame = window.wp.media( { title: __( 'Choose option image', 'alovio-calculator' ), library: { type: 'image' }, multiple: false } );
  		frame.on( 'select', () => {
  			const a = frame.state().get( 'selection' ).first().toJSON();
  			update( i, { image: a.id, imageUrl: a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url } );
  		} );
  		frame.open();
  	};

  	return (
  		<div className="alcb-options">
  			{ options.map( ( o, i ) => (
  				<div
  					key={ o.value || `new-${ i }` }
  					className={ 'alcb-opt-row' + ( drag === i ? ' is-dragging' : '' ) }
  					onDragOver={ ( e ) => e.preventDefault() }
  					onDrop={ () => {
  						if ( null !== drag ) {
  							move( drag, i );
  						}
  						setDrag( null );
  					} }
  				>
  					<span className="alcb-opt-grip" draggable onDragStart={ () => setDrag( i ) } onDragEnd={ () => setDrag( null ) } title={ __( 'Drag to reorder', 'alovio-calculator' ) }>⠿</span>
  					<TextControl label={ __( 'Label', 'alovio-calculator' ) } hideLabelFromVision placeholder={ __( 'Label', 'alovio-calculator' ) } value={ o.label || '' } onChange={ ( label ) => update( i, { label } ) } />
  					<TextControl label={ __( 'Price', 'alovio-calculator' ) } hideLabelFromVision placeholder="0" type="number" step="0.01" value={ 0 === o.price || o.price ? String( o.price ) : '' } onChange={ ( price ) => update( i, { price } ) } />
  					<span className="alcb-opt-img">
  						{ o.image > 0 && o.imageUrl && <img src={ o.imageUrl } alt="" width="28" height="28" /> }
  						<Button size="small" onClick={ () => pickImage( i ) }>{ o.image > 0 ? __( 'Change', 'alovio-calculator' ) : __( 'Image', 'alovio-calculator' ) }</Button>
  						{ o.image > 0 && <Button size="small" isDestructive onClick={ () => update( i, { image: 0, imageUrl: '' } ) }>✕</Button> }
  					</span>
  					<label className="alcb-opt-default" title={ single ? __( 'Selected by default', 'alovio-calculator' ) : __( 'Checked by default', 'alovio-calculator' ) }>
  						<input type={ single ? 'radio' : 'checkbox' } name={ `alcb-def-${ field.id }` } checked={ !! o.default } onChange={ ( e ) => setDefault( i, e.target.checked ) } />
  						{ __( 'Default', 'alovio-calculator' ) }
  					</label>
  					<Button size="small" isDestructive disabled={ options.length < 2 } onClick={ () => remove( i ) } aria-label={ __( 'Remove option', 'alovio-calculator' ) }>✕</Button>
  				</div>
  			) ) }
  			{ ! options.length && <Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', 'alovio-calculator' ) }</Notice> }
  			<div className="alcb-opt-foot">
  				<Button variant="secondary" size="small" onClick={ add }>{ __( '+ Add option', 'alovio-calculator' ) }</Button>
  				{ single && options.some( ( o ) => o.default ) && (
  					<Button variant="link" onClick={ () => set( { options: options.map( ( o ) => ( { ...o, default: false } ) ) } ) }>
  						{ __( 'Clear default', 'alovio-calculator' ) }
  					</Button>
  				) }
  			</div>
  			{ 'select' === field.type && (
  				<p className="alcb-hint">{ __( 'Images are stored for dropdown options but only shown for Multiple choice and Checkboxes (a native dropdown cannot render images).', 'alovio-calculator' ) }</p>
  			) }
  		</div>
  	);
  }
  ```
- [ ] **Step 3: Create `src/builder/panels/FormulaTab.jsx`** (thin wrapper; `FormulaPanel.jsx` survives as-is):
  ```jsx
  import FormulaPanel from '../FormulaPanel';

  /** Formula tab = existing live-validated editor; errors also surface as canvas badges (same validator). */
  export default function FormulaTab( { field, fields, set } ) {
  	return <FormulaPanel field={ field } fields={ fields } set={ set } />;
  }
  ```
- [ ] **Step 4: Append builder.scss section 08 (part 1 — shared panel bits + options rows)**:
  ```scss
  // ── 08 · Settings panel, options rows, logic tokens
  .alcb-hint { color: var(--alcb-ink-3); font-size: 12px; margin: 6px 0 0; }
  .alcb-row4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
  .alcb-opt-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
  .alcb-opt-row.is-dragging { opacity: 0.45; }
  .alcb-opt-row > .components-base-control { margin-bottom: 0; }
  .alcb-opt-row > .components-base-control:first-of-type { flex: 2; }
  .alcb-opt-grip { cursor: grab; color: var(--alcb-ink-3); flex: none; }
  .alcb-opt-img { display: flex; align-items: center; gap: 4px; flex: none; }
  .alcb-opt-img img { border-radius: 4px; object-fit: cover; }
  .alcb-opt-default { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; color: var(--alcb-ink-2); flex: none; }
  .alcb-opt-foot { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
  ```
- [ ] **Step 5: Verify + commit** — `npm run build` clean (files are not imported yet — compile check comes free via the entry once SettingsPanel lands; here `npm run build` plus `npx wp-scripts lint-js src/builder/panels` if configured — otherwise the build in Task 4.5 covers them). `npm test` green.
  ```bash
  git add src/builder/panels/FieldGeneral.jsx src/builder/panels/OptionsTab.jsx src/builder/panels/FormulaTab.jsx src/builder/builder.scss
  git commit -m "studio: FieldGeneral/OptionsTab/FormulaTab panels (options: reorder, images everywhere, default flag)"
  ```

### Task 4.4: LogicTokens — sentence-token condition editor

**Files:**
- Create: `src/builder/panels/LogicTokens.jsx`
- Modify: `src/builder/builder.scss` (append section 08 part 2)

Donor: `/Users/tahir/woo-checkout-fields/src/builder/panels/Logic.jsx`. Deltas vs donor (everything else — chip markup, AND/OR toggle, Show/Hide/Require segmented control, rule card layout — is a straight port with `clcf-`→`alcb-` and our text domain): the `@…` context-source system (`SOURCES`, `FIELD_SOURCE`, `baseToken`, `suffixId`, `defFor`, `kindOf`) is REMOVED — controllers are sibling fields only, including `formula` (running total), never heading/html/step (mirrors `FieldTypes::is_condition_controller`); the operator set comes from the old `ConditionEditor.jsx` per-controller mapping incl. `gte/lte/is_empty/is_not_empty`; the value token is HIDDEN for `is_empty`/`is_not_empty` (value forced `''`); choice values are stable `opt_` slugs shown by label (unsaved options can't be referenced — same rule as before); toggle values are On/Off; `setRules` does NOT write the donor's legacy `condition: null` key (our schema never had it).

- [ ] **Step 1: Create `src/builder/panels/LogicTokens.jsx`** (full code):
  ```jsx
  import { useDispatch, useSelect } from '@wordpress/data';
  import { ToggleControl } from '@wordpress/components';
  import { __ } from '@wordpress/i18n';
  import { STORE } from '../store';

  const CONTROLLER_TYPES = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'formula' ];
  const NUMERIC = [ 'number', 'slider', 'quantity', 'formula' ];
  const CHOICE = [ 'select', 'radio', 'checkbox_group' ];
  const NO_VALUE_OPS = [ 'is_empty', 'is_not_empty' ];

  const OP_LABELS = () => ( {
  	is: __( 'is', 'alovio-calculator' ),
  	is_not: __( 'is not', 'alovio-calculator' ),
  	contains: __( 'contains', 'alovio-calculator' ),
  	gt: __( 'greater than', 'alovio-calculator' ),
  	gte: __( 'at least', 'alovio-calculator' ),
  	lt: __( 'less than', 'alovio-calculator' ),
  	lte: __( 'at most', 'alovio-calculator' ),
  	is_empty: __( 'is empty', 'alovio-calculator' ),
  	is_not_empty: __( 'is not empty', 'alovio-calculator' ),
  } );

  function opsFor( type ) {
  	if ( NUMERIC.indexOf( type ) !== -1 ) {
  		return [ 'is', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' ];
  	}
  	if ( 'select' === type || 'radio' === type ) {
  		return [ 'is', 'is_not', 'is_empty', 'is_not_empty' ];
  	}
  	if ( 'checkbox_group' === type ) {
  		return [ 'contains', 'is', 'is_not', 'is_empty', 'is_not_empty' ];
  	}
  	if ( 'toggle' === type ) {
  		return [ 'is' ];
  	}
  	return [ 'is', 'is_not', 'contains', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' ]; // text
  }

  function defaultValueFor( controller ) {
  	if ( ! controller ) {
  		return '';
  	}
  	if ( 'toggle' === controller.type ) {
  		return '1';
  	}
  	if ( CHOICE.indexOf( controller.type ) !== -1 ) {
  		const first = ( controller.options || [] ).find( ( o ) => o.value );
  		return first ? first.value : '';
  	}
  	return '';
  }

  function makeRule( controller ) {
  	return { field: controller ? controller.id : '', operator: opsFor( controller ? controller.type : 'text' )[ 0 ], value: defaultValueFor( controller ) };
  }

  /** A select disguised as a token chip (donor pattern). */
  function TokSelect( { kind, value, valueLabel, options, onChange } ) {
  	return (
  		<span className={ `alcb-tok${ kind ? ` alcb-tok--${ kind }` : '' }` }>
  			{ valueLabel } <span className="alcb-car"></span>
  			<select value={ value } onChange={ ( e ) => onChange( e.target.value ) }>
  				{ options.map( ( o ) => (
  					<option key={ o.value } value={ o.value }>{ o.label }</option>
  				) ) }
  			</select>
  		</span>
  	);
  }

  function ValueToken( { controller, rule, onChange } ) {
  	if ( controller && 'toggle' === controller.type ) {
  		const opts = [ { label: __( 'On', 'alovio-calculator' ), value: '1' }, { label: __( 'Off', 'alovio-calculator' ), value: '' } ];
  		return <TokSelect kind="val" value={ rule.value } valueLabel={ '1' === rule.value ? __( 'On', 'alovio-calculator' ) : __( 'Off', 'alovio-calculator' ) } options={ opts } onChange={ ( value ) => onChange( { ...rule, value } ) } />;
  	}
  	if ( controller && CHOICE.indexOf( controller.type ) !== -1 ) {
  		const opts = ( controller.options || [] ).filter( ( o ) => o.value ).map( ( o ) => ( { label: o.label || o.value, value: o.value } ) );
  		const current = opts.find( ( o ) => o.value === rule.value );
  		return (
  			<TokSelect
  				kind="val"
  				value={ rule.value }
  				valueLabel={ current ? current.label : '—' }
  				options={ opts.length ? opts : [ { label: __( '— save first to reference new options —', 'alovio-calculator' ), value: '' } ] }
  				onChange={ ( value ) => onChange( { ...rule, value } ) }
  			/>
  		);
  	}
  	const numeric = controller && NUMERIC.indexOf( controller.type ) !== -1;
  	return (
  		<span className="alcb-tok alcb-tok--val">
  			<input type={ numeric ? 'number' : 'text' } value={ rule.value } placeholder={ __( 'value…', 'alovio-calculator' ) } onChange={ ( e ) => onChange( { ...rule, value: e.target.value } ) } />
  		</span>
  	);
  }

  function RuleRow( { rule, controllers, onChange, onRemove, canRemove } ) {
  	const controller = controllers.find( ( f ) => f.id === rule.field ) || null;
  	const ops = opsFor( controller ? controller.type : 'text' );
  	const operator = ops.indexOf( rule.operator ) !== -1 ? rule.operator : ops[ 0 ];
  	const labels = OP_LABELS();

  	return (
  		<div className="alcb-sentence">
  			<TokSelect
  				kind="src"
  				value={ rule.field }
  				valueLabel={ controller ? controller.label || controller.type : '—' }
  				options={ controllers.map( ( f ) => ( { label: f.label || f.type, value: f.id } ) ) }
  				onChange={ ( v ) => onChange( makeRule( controllers.find( ( f ) => f.id === v ) || null ) ) }
  			/>
  			<TokSelect
  				value={ operator }
  				valueLabel={ labels[ operator ] || operator }
  				options={ ops.map( ( o ) => ( { label: labels[ o ] || o, value: o } ) ) }
  				onChange={ ( v ) => onChange( NO_VALUE_OPS.indexOf( v ) !== -1 ? { ...rule, operator: v, value: '' } : { ...rule, operator: v } ) }
  			/>
  			{ NO_VALUE_OPS.indexOf( operator ) === -1 && <ValueToken controller={ controller } rule={ rule } onChange={ onChange } /> }
  			{ canRemove && <button className="alcb-rule-x" aria-label={ __( 'Remove rule', 'alovio-calculator' ) } onClick={ onRemove }>✕</button> }
  		</div>
  	);
  }

  export default function LogicTokens( { field } ) {
  	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
  	const { updateField } = useDispatch( STORE );
  	const controllers = fields.filter( ( f ) => f.id !== field.id && CONTROLLER_TYPES.indexOf( f.type ) !== -1 );

  	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
  	const enabled = rules.length > 0;

  	const setRules = ( newRules ) =>
  		updateField( field.id, {
  			conditions: newRules,
  			conditionMatch: field.conditionMatch || 'all',
  			conditionAction: field.conditionAction || 'show',
  		} );

  	if ( ! controllers.length ) {
  		return <p className="alcb-hint">{ __( 'Add another input field first — conditions react to other fields (or a formula total).', 'alovio-calculator' ) }</p>;
  	}

  	const toggle = ( on ) => ( on ? setRules( [ makeRule( controllers[ 0 ] ) ] ) : updateField( field.id, { conditions: [] } ) );

  	return (
  		<>
  			<ToggleControl
  				label={ __( 'Conditional logic', 'alovio-calculator' ) }
  				help={ __( 'Show, hide, or require this field based on another field or the running total.', 'alovio-calculator' ) }
  				checked={ enabled }
  				onChange={ toggle }
  			/>
  			{ enabled && (
  				<>
  					<div className="alcb-rule-card">
  						<div className="alcb-rule-when">{ __( 'When', 'alovio-calculator' ) }</div>
  						{ rules.map( ( r, i ) => (
  							<div key={ i }>
  								{ i > 0 && (
  									<div className="alcb-andor">
  										<button className={ 'all' === ( field.conditionMatch || 'all' ) ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionMatch: 'all' } ) }>{ __( 'AND', 'alovio-calculator' ) }</button>
  										<button className={ 'any' === field.conditionMatch ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionMatch: 'any' } ) }>{ __( 'OR', 'alovio-calculator' ) }</button>
  									</div>
  								) }
  								<RuleRow
  									rule={ r }
  									controllers={ controllers }
  									onChange={ ( nr ) => setRules( rules.map( ( x, idx ) => ( idx === i ? nr : x ) ) ) }
  									onRemove={ () => setRules( rules.filter( ( _, idx ) => idx !== i ) ) }
  									canRemove={ rules.length > 1 }
  								/>
  							</div>
  						) ) }
  						<button className="alcb-addrule" onClick={ () => setRules( [ ...rules, makeRule( controllers[ 0 ] ) ] ) }>＋ { __( 'Add condition', 'alovio-calculator' ) }</button>
  					</div>
  					<div className="alcb-then">
  						<div className="alcb-then-lbl">{ __( 'Then', 'alovio-calculator' ) }</div>
  						<div className="alcb-seg">
  							{ [ [ 'show', __( 'Show', 'alovio-calculator' ) ], [ 'hide', __( 'Hide', 'alovio-calculator' ) ], [ 'require', __( 'Require', 'alovio-calculator' ) ] ].map( ( [ v, l ] ) => (
  								<button key={ v } className={ ( field.conditionAction || 'show' ) === v ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionAction: v } ) }>{ l }</button>
  							) ) }
  						</div>
  						{ 'require' === ( field.conditionAction || 'show' ) && (
  							<p className="alcb-hint">{ __( 'The field stays visible and must be filled in before a quote can be requested.', 'alovio-calculator' ) }</p>
  						) }
  					</div>
  				</>
  			) }
  		</>
  	);
  }
  ```
- [ ] **Step 2: Append builder.scss section 08 (part 2 — token chips; donor lines 316–344 renamed, plus the donor's select-neutralisation rule scoped to our chips)**:
  ```scss
  .alcb-rule-card { border: 1px solid var(--alcb-line); border-radius: var(--alcb-r); padding: 13px 14px; background: #fafaf9; }
  .alcb-rule-when, .alcb-then-lbl { font-size: 11px; font-weight: 800; letter-spacing: 0.07em; color: var(--alcb-ink-3); text-transform: uppercase; margin-bottom: 9px; }
  .alcb-sentence { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; font-size: 12.5px; color: var(--alcb-ink-2); }
  .alcb-sentence + .alcb-sentence { margin-top: 8px; }
  .alcb-tok {
  	position: relative; display: inline-flex; align-items: center; gap: 6px;
  	border: 1px solid var(--alcb-line-2); background: #fff; border-radius: 8px;
  	padding: 5px 10px; font-weight: 700; color: var(--alcb-ink); font-size: 12px; cursor: pointer;
  }
  .alcb-tok select { position: absolute; inset: 0; opacity: 0; width: 100%; cursor: pointer; min-height: 0; height: 100%; max-width: none; margin: 0; padding: 0; border: 0; }
  .alcb-tok input { border: none; outline: none; width: 72px; font: inherit; color: inherit; background: transparent; padding: 0; min-height: 0; }
  .alcb-tok--src { border-color: var(--alcb-flame-border); background: var(--alcb-flame-soft); color: var(--alcb-flame-deep); }
  .alcb-tok--val { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
  .alcb-car { width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 4px solid currentColor; opacity: 0.55; }
  .alcb-rule-x { border: none; background: none; color: var(--alcb-ink-3); cursor: pointer; font-size: 13px; padding: 2px 4px; }
  .alcb-rule-x:hover { color: var(--alcb-red); }
  .alcb-andor { display: inline-flex; border: 1px solid var(--alcb-line-2); border-radius: 8px; overflow: hidden; margin: 10px 0; }
  .alcb-andor button { padding: 5px 13px; font-size: 11.5px; font-weight: 800; color: var(--alcb-ink-3); background: #fff; border: none; cursor: pointer; }
  .alcb-andor button.is-on { background: var(--alcb-coal); color: #fff; }
  .alcb-addrule { margin-top: 11px; display: inline-flex; align-items: center; gap: 7px; cursor: pointer; font-size: 12.5px; font-weight: 800; color: var(--alcb-flame-deep); border: none; background: none; padding: 0; }
  .alcb-then { margin-top: 16px; }
  .alcb-seg { display: flex; border: 1px solid var(--alcb-line-2); border-radius: 9px; overflow: hidden; }
  .alcb-seg button { flex: 1; text-align: center; padding: 8px 0; font-size: 12.5px; font-weight: 800; color: var(--alcb-ink-3); background: #fff; border: none; cursor: pointer; }
  .alcb-seg button.is-on { background: linear-gradient(135deg, var(--alcb-flame), var(--alcb-flame-deep)); color: #fff; }
  ```
- [ ] **Step 3: Commit**
  ```bash
  git add src/builder/panels/LogicTokens.jsx src/builder/builder.scss
  git commit -m "studio: sentence-token Logic editor (our operators, opt_ slug values, empty-ops hide value)"
  ```

### Task 4.5: Calculator panels + ProPanel + SettingsPanel + right-column swap

**Files:**
- Create: `src/builder/panels/CalcGeneral.jsx`, `src/builder/panels/CalcDesign.jsx`, `src/builder/panels/CalcQuote.jsx`, `src/builder/panels/ProPanel.jsx`, `src/builder/SettingsPanel.jsx`
- Modify: `src/builder/StudioShell.jsx` (right column), `src/builder/builder.scss` (append section 08 part 3)

The three calc panels split `src/builder/SettingsTab.jsx` (this repo) 1:1: **CalcGeneral** = Currency section (SettingsTab lines 27–49 + `setCurrency` helper, line 16); **CalcDesign** = Appearance (51–73) + Layout incl. Pro-gated wizard toggle exactly as today (75–93), with the preset list imported from `CanvasToolbar`'s `THEME_PRESETS` instead of the inline array; **CalcQuote** = Quote requests (95–124 + `setQuote`/`toggleQuoteField` helpers, lines 17–23). Each panel is a standalone component reading the store itself (copy SettingsTab lines 1–14 imports/state prelude, trimmed to what the panel uses; drop the `<section>`/`<h3>` wrappers — the tab header replaces them). **ProPanel** ports `ProTab.jsx` content verbatim into studio chrome.

- [ ] **Step 1: Create the three calc panels** — full code for CalcGeneral (the other two follow the identical pattern with their stated line ranges):
  ```jsx
  import { useDispatch, useSelect } from '@wordpress/data';
  import { TextControl, SelectControl } from '@wordpress/components';
  import { __ } from '@wordpress/i18n';
  import { STORE } from '../store';

  export default function CalcGeneral() {
  	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
  	const { updateSettings } = useDispatch( STORE );
  	const currency = settings.currency || {};
  	const setCurrency = ( patch ) => updateSettings( { currency: { ...currency, ...patch } } );

  	return (
  		<>
  			<span className="alcb-sec-label">{ __( 'Currency', 'alovio-calculator' ) }</span>
  			{ /* Body = SettingsTab.jsx lines 29–48 verbatim (alc-row4 → alcb-row4, alc-narrow → alcb-narrow). */ }
  		</>
  	);
  }
  ```
  CalcDesign: same prelude + `import { THEME_PRESETS } from '../CanvasToolbar';`, body = SettingsTab lines 53–92 verbatim except the `options={ [ …inline preset array… ] }` becomes `options={ THEME_PRESETS }` and the two section labels become `alcb-sec-label` spans (`Appearance`, `Layout`). The `isPro` gate line (SettingsTab line 14) moves in unchanged. CalcQuote: prelude + `quoteFields`/`toggleQuoteField` helpers (lines 19–23), body = lines 97–123 verbatim. The calculator NAME is edited in the studio header (chunk 1) — deliberately not duplicated in CalcGeneral.
- [ ] **Step 2: Create `src/builder/panels/ProPanel.jsx`** (full code — still the plugin's single upsell surface, guideline 11):
  ```jsx
  import { ExternalLink } from '@wordpress/components';
  import { __ } from '@wordpress/i18n';

  export default function ProPanel() {
  	const features = [
  		__( 'Multi-step wizard layout', 'alovio-calculator' ),
  		__( 'Branded PDF quotes (download & email, logo, tax/VAT)', 'alovio-calculator' ),
  		__( 'Webhooks & Zapier', 'alovio-calculator' ),
  		__( 'Quote analytics dashboard', 'alovio-calculator' ),
  	];
  	return (
  		<div className="alcb-pro">
  			<h3>{ __( 'Alovio Calculator Pro', 'alovio-calculator' ) }</h3>
  			<p>{ __( 'Everything you use today stays free — including conditional logic. Pro adds:', 'alovio-calculator' ) }</p>
  			<ul>{ features.map( ( f ) => <li key={ f }>{ f }</li> ) }</ul>
  			<ExternalLink href="https://alovio.org/store/calculator-pro">{ __( 'Get Alovio Calculator Pro', 'alovio-calculator' ) }</ExternalLink>
  		</div>
  	);
  }
  ```
- [ ] **Step 3: Create `src/builder/SettingsPanel.jsx`** (full code; contextual per spec §2.4 — field tabs / calc tabs / Pro):
  ```jsx
  import { useState, useEffect } from '@wordpress/element';
  import { useSelect, useDispatch } from '@wordpress/data';
  import { __ } from '@wordpress/i18n';
  import { STORE } from './store';
  import { ICONS } from './icons';
  import FieldGeneral from './panels/FieldGeneral';
  import LogicTokens from './panels/LogicTokens';
  import OptionsTab from './panels/OptionsTab';
  import FormulaTab from './panels/FormulaTab';
  import CalcGeneral from './panels/CalcGeneral';
  import CalcDesign from './panels/CalcDesign';
  import CalcQuote from './panels/CalcQuote';
  import ProPanel from './panels/ProPanel';

  const CHOICE = [ 'select', 'radio', 'checkbox_group' ];

  function Tabs( { tabs, current, onChange } ) {
  	return (
  		<div className="alcb-tabs">
  			{ tabs.map( ( [ key, label ] ) => (
  				<button key={ key } className={ `alcb-tab${ current === key ? ' is-on' : '' }` } onClick={ () => onChange( key ) }>{ label }</button>
  			) ) }
  		</div>
  	);
  }

  export default function SettingsPanel( { proOpen } ) {
  	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
  	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
  	const { selectField, updateField } = useDispatch( STORE );
  	const [ tab, setTab ] = useState( 'general' );

  	useEffect( () => {
  		setTab( 'general' ); // reset when the context changes
  	}, [ field && field.id, proOpen ] ); // eslint-disable-line react-hooks/exhaustive-deps

  	if ( proOpen ) {
  		return <div className="alcb-settings"><div className="alcb-sp-body"><ProPanel /></div></div>;
  	}

  	if ( ! field ) {
  		return (
  			<div className="alcb-settings">
  				<div className="alcb-sp-head">
  					<div className="alcb-sp-title"><h3>{ __( 'Calculator settings', 'alovio-calculator' ) }</h3></div>
  					<Tabs
  						tabs={ [ [ 'general', __( 'General', 'alovio-calculator' ) ], [ 'design', __( 'Design', 'alovio-calculator' ) ], [ 'quote', __( 'Quote form', 'alovio-calculator' ) ] ] }
  						current={ tab }
  						onChange={ setTab }
  					/>
  				</div>
  				<div className="alcb-sp-body">
  					{ 'design' === tab ? <CalcDesign /> : 'quote' === tab ? <CalcQuote /> : <CalcGeneral /> }
  				</div>
  			</div>
  		);
  	}

  	const third = CHOICE.indexOf( field.type ) !== -1 ? 'options' : 'formula' === field.type ? 'formula' : null;
  	const set = ( patch ) => updateField( field.id, patch );
  	const tabs = [
  		[ 'general', __( 'General', 'alovio-calculator' ) ],
  		[ 'logic', __( 'Logic', 'alovio-calculator' ) ],
  		...( 'options' === third ? [ [ 'options', __( 'Options', 'alovio-calculator' ) ] ] : [] ),
  		...( 'formula' === third ? [ [ 'formula', __( 'Formula', 'alovio-calculator' ) ] ] : [] ),
  	];

  	return (
  		<div className="alcb-settings">
  			<div className="alcb-sp-head">
  				<div className="alcb-sp-title">
  					<span className="alcb-ic">{ ICONS[ field.type ] || null }</span>
  					<div>
  						<h3>{ field.label || field.type }</h3>
  						<small>{ field.type }</small>
  					</div>
  					<button className="alcb-gear" title={ __( 'Calculator settings', 'alovio-calculator' ) } aria-label={ __( 'Calculator settings', 'alovio-calculator' ) } onClick={ () => selectField( null ) }>⚙</button>
  				</div>
  				<Tabs tabs={ tabs } current={ tab } onChange={ setTab } />
  			</div>
  			<div className="alcb-sp-body">
  				{ 'general' === tab && <FieldGeneral field={ field } set={ set } /> }
  				{ 'logic' === tab && <LogicTokens field={ field } /> }
  				{ 'options' === tab && 'options' === third && <OptionsTab field={ field } set={ set } /> }
  				{ 'formula' === tab && 'formula' === third && <FormulaTab field={ field } fields={ fields } set={ set } /> }
  			</div>
  			<div className="alcb-sp-foot">
  				{ __( 'Changes render live', 'alovio-calculator' ) } · <span className="alcb-kbd">⌘S</span> { __( 'saves', 'alovio-calculator' ) }
  			</div>
  		</div>
  	);
  }
  ```
- [ ] **Step 4: Swap the right column** — in `StudioShell.jsx`: remove the `FieldSettings`, `SettingsTab`, `ProTab` imports and the `selected` entry from the `useSelect` mapping; add `import SettingsPanel from './SettingsPanel';`; the right column becomes `<div className="alcb-col alcb-col--right alcb-col--panel"><SettingsPanel proOpen={ proOpen } /></div>`.
- [ ] **Step 5: Append builder.scss section 08 (part 3 — panel chrome; donor lines 272–314 renamed)**:
  ```scss
  .alcb-col--panel { padding: 0; display: flex; flex-direction: column; }
  .alcb-settings { display: flex; flex-direction: column; min-height: 0; flex: 1; }
  .alcb-sp-head { padding: 16px 18px 0; flex: none; }
  .alcb-sp-title { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
  .alcb-sp-title h3 { font-size: 14px; font-weight: 800; margin: 0; line-height: 1.25; }
  .alcb-sp-title small { display: block; font-size: 11px; color: var(--alcb-ink-3); font-weight: 600; }
  .alcb-gear { margin-left: auto; border: none; background: none; cursor: pointer; font-size: 15px; color: var(--alcb-ink-3); }
  .alcb-gear:hover { color: var(--alcb-flame-deep); }
  .alcb-tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--alcb-line); }
  .alcb-tab { padding: 9px 13px; font-size: 12.5px; font-weight: 700; color: var(--alcb-ink-3); border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; }
  .alcb-tab.is-on { color: var(--alcb-flame-deep); border-bottom-color: var(--alcb-flame); }
  .alcb-sp-body { padding: 16px 18px; overflow-y: auto; flex: 1; }
  .alcb-sp-foot { padding: 13px 18px; border-top: 1px solid var(--alcb-line); flex: none; display: flex; align-items: center; gap: 8px; color: var(--alcb-ink-3); font-size: 11.5px; }
  .alcb-kbd { border: 1px solid var(--alcb-line-2); border-radius: 5px; padding: 2px 6px; font-size: 10.5px; font-weight: 700; background: #fff; }
  .alcb-sp-body .components-base-control { margin-bottom: 14px; }
  .alcb-sp-body .components-base-control__label { font-size: 11px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: var(--alcb-ink-2); }
  .alcb-narrow { max-width: 180px; }
  .alcb-pro h3 { margin-top: 0; }
  .alcb-pro li { margin-bottom: 4px; }
  ```
- [ ] **Step 6: Verify** — `npm run build` clean; wp-env: select a number field → General shows label/help/range; a dropdown → Options tab with reorder/image/default; formula → Formula tab with live validation mirrored to the canvas badge; Logic tab builds a sentence (`When [Area] [at least] [100] → Show`), value chip hides for "is empty"; deselect (or ⚙) → General/Design/Quote-form tabs, wizard toggle still Pro-gated; Pro header button → ProPanel; every edit live-renders the canvas.
- [ ] **Step 7: Commit**
  ```bash
  git add src/builder/SettingsPanel.jsx src/builder/panels/CalcGeneral.jsx src/builder/panels/CalcDesign.jsx src/builder/panels/CalcQuote.jsx src/builder/panels/ProPanel.jsx src/builder/StudioShell.jsx src/builder/builder.scss
  git commit -m "studio: contextual SettingsPanel — field tabs, calculator tabs, Pro panel"
  ```

### Task 4.6: Draft recovery (`draft.js`, tests first)

**Files:**
- Test: `src/builder/__tests__/draft.test.js` (new)
- Create: `src/builder/draft.js`
- Modify: `src/builder/StudioShell.jsx` (writer effect + restore/discard bar), `src/builder/builder.scss` (append section 09)

- [ ] **Step 1: Write the failing tests** — create `src/builder/__tests__/draft.test.js`:
  ```js
  import { saveDraft, loadDraft, clearDraft, isDraftNewer, parseModifiedGmt, draftKey, DRAFT_DEBOUNCE_MS } from '../draft';

  const memStorage = () => {
  	const m = new Map();
  	return {
  		getItem: ( k ) => ( m.has( k ) ? m.get( k ) : null ),
  		setItem: ( k, v ) => m.set( k, String( v ) ),
  		removeItem: ( k ) => m.delete( k ),
  	};
  };

  describe( 'draft storage', () => {
  	it( 'round-trips and stamps calcId + savedAt under the documented key', () => {
  		const s = memStorage();
  		expect( draftKey( 7 ) ).toBe( 'alovio_calc_draft_7' );
  		saveDraft( 7, { name: 'Roof', fields: [ { id: 'a' } ], settings: { x: 1 } }, s );
  		const d = loadDraft( 7, s );
  		expect( d.calcId ).toBe( 7 );
  		expect( d.name ).toBe( 'Roof' );
  		expect( typeof d.savedAt ).toBe( 'number' );
  	} );
  	it( 'returns null for missing, corrupt, or shape-invalid entries', () => {
  		const s = memStorage();
  		expect( loadDraft( 7, s ) ).toBeNull();
  		s.setItem( 'alovio_calc_draft_7', '{not json' );
  		expect( loadDraft( 7, s ) ).toBeNull();
  		s.setItem( 'alovio_calc_draft_7', JSON.stringify( { savedAt: 'nope' } ) );
  		expect( loadDraft( 7, s ) ).toBeNull();
  	} );
  	it( 'clears', () => {
  		const s = memStorage();
  		saveDraft( 7, { name: '', fields: [], settings: {} }, s );
  		clearDraft( 7, s );
  		expect( loadDraft( 7, s ) ).toBeNull();
  	} );
  	it( 'swallows storage failures (private mode / quota)', () => {
  		const broken = { getItem() { throw new Error( 'x' ); }, setItem() { throw new Error( 'x' ); }, removeItem() { throw new Error( 'x' ); } };
  		expect( () => saveDraft( 7, { fields: [] }, broken ) ).not.toThrow();
  		expect( loadDraft( 7, broken ) ).toBeNull();
  		expect( () => clearDraft( 7, broken ) ).not.toThrow();
  	} );
  } );

  describe( 'modified comparison', () => {
  	it( 'parses MySQL GMT as UTC; invalid → 0', () => {
  		expect( parseModifiedGmt( '2026-07-05 09:30:00' ) ).toBe( Date.UTC( 2026, 6, 5, 9, 30, 0 ) );
  		expect( parseModifiedGmt( '' ) ).toBe( 0 );
  		expect( parseModifiedGmt( 'garbage' ) ).toBe( 0 );
  		expect( parseModifiedGmt( null ) ).toBe( 0 );
  	} );
  	it( 'isDraftNewer compares savedAt to the server timestamp', () => {
  		const at = Date.UTC( 2026, 6, 5, 10, 0, 0 );
  		expect( isDraftNewer( { savedAt: at, fields: [] }, '2026-07-05 09:30:00' ) ).toBe( true );
  		expect( isDraftNewer( { savedAt: at, fields: [] }, '2026-07-05 11:00:00' ) ).toBe( false );
  		expect( isDraftNewer( null, '2026-07-05 09:30:00' ) ).toBe( false );
  		expect( isDraftNewer( { savedAt: at, fields: [] }, '' ) ).toBe( true ); // no server stamp → draft wins
  	} );
  	it( 'exports the 1s debounce constant', () => {
  		expect( DRAFT_DEBOUNCE_MS ).toBe( 1000 );
  	} );
  } );
  ```
- [ ] **Step 2: Run and watch it fail** — `npm test -- draft` → expected: `Cannot find module '../draft'`.
- [ ] **Step 3: Create `src/builder/draft.js`** (full code):
  ```js
  /**
   * localStorage draft recovery (spec §2.6). Pure module — Jest-tested with an
   * injectable storage. Best-effort by design: every storage failure is silent.
   */
  export const DRAFT_DEBOUNCE_MS = 1000;

  export function draftKey( calcId ) {
  	return `alovio_calc_draft_${ calcId }`;
  }

  export function saveDraft( calcId, data, storage = window.localStorage ) {
  	try {
  		storage.setItem( draftKey( calcId ), JSON.stringify( { ...data, calcId, savedAt: Date.now() } ) );
  	} catch ( e ) {
  		// quota / private mode — drafts are best-effort
  	}
  }

  export function loadDraft( calcId, storage = window.localStorage ) {
  	try {
  		const raw = storage.getItem( draftKey( calcId ) );
  		const d = raw ? JSON.parse( raw ) : null;
  		return d && Array.isArray( d.fields ) && 'number' === typeof d.savedAt ? d : null;
  	} catch ( e ) {
  		return null;
  	}
  }

  export function clearDraft( calcId, storage = window.localStorage ) {
  	try {
  		storage.removeItem( draftKey( calcId ) );
  	} catch ( e ) {
  		// ignore
  	}
  }

  /** MySQL GMT 'YYYY-MM-DD HH:MM:SS' → epoch ms (0 when absent/invalid). */
  export function parseModifiedGmt( modified ) {
  	if ( ! modified || 'string' !== typeof modified ) {
  		return 0;
  	}
  	const t = Date.parse( modified.replace( ' ', 'T' ) + 'Z' );
  	return Number.isNaN( t ) ? 0 : t;
  }

  export function isDraftNewer( draft, modifiedGmt ) {
  	return !! draft && draft.savedAt > parseModifiedGmt( modifiedGmt );
  }
  ```
- [ ] **Step 4: Run** — `npm test` → all green.
- [ ] **Step 5: Wire into `StudioShell.jsx`** — exact deltas:
  1. `import { saveDraft, loadDraft, clearDraft, isDraftNewer, DRAFT_DEBOUNCE_MS } from './draft';`
  2. State: `const [ draft, setDraft ] = useState( null );`
  3. In the load `.then`, after `modifiedRef.current = calc.modified || '';`:
     ```js
     const d = loadDraft( calculatorId );
     if ( isDraftNewer( d, calc.modified || '' ) ) {
     	setDraft( d ); // newest-wins prompt — the user decides (spec §8)
     }
     ```
  4. Writer effect (after the `beforeunload` effect):
     ```js
     useEffect( () => {
     	if ( loading || ! dirty ) {
     		return undefined;
     	}
     	const t = window.setTimeout( () => saveDraft( calculatorId, { name, fields, settings } ), DRAFT_DEBOUNCE_MS );
     	return () => window.clearTimeout( t );
     }, [ calculatorId, name, fields, settings, dirty, loading ] );
     ```
  5. In `save()` after `modifiedRef.current = …`: `clearDraft( calculatorId ); setDraft( null );`
  6. Bar JSX directly under `</div>` of `.alcb-hdr` (restore keeps the state dirty vs `savedRef`, so the pill turns amber and the user must Save):
     ```jsx
     { draft && (
     	<div className="alcb-draftbar" role="status">
     		<span>{ __( 'A newer unsaved draft of this calculator exists on this device.', 'alovio-calculator' ) }</span>
     		<button className="alcb-draftbar__restore" onClick={ () => { hydrate( draft.fields || [], draft.settings || {}, draft.name || '' ); setDraft( null ); } }>
     			{ __( 'Restore draft', 'alovio-calculator' ) }
     		</button>
     		<button className="alcb-draftbar__discard" onClick={ () => { clearDraft( calculatorId ); setDraft( null ); } }>
     			{ __( 'Discard', 'alovio-calculator' ) }
     		</button>
     	</div>
     ) }
     ```
- [ ] **Step 6: Append builder.scss section 09**:
  ```scss
  // ── 09 · Draft-restore bar
  .alcb-draftbar {
  	flex: none; display: flex; align-items: center; gap: 12px;
  	padding: 8px 20px; background: var(--alcb-flame-soft);
  	border-bottom: 1px solid var(--alcb-flame-border); color: var(--alcb-flame-deep); font-weight: 600;
  }
  .alcb-draftbar button { border: none; cursor: pointer; font-weight: 800; border-radius: var(--alcb-r-sm); padding: 5px 12px; }
  .alcb-draftbar__restore { background: var(--alcb-flame-deep); color: #fff; }
  .alcb-draftbar__discard { background: transparent; color: var(--alcb-ink-2); }
  ```
- [ ] **Step 7: Verify** — wp-env: edit without saving, reload → bar appears; Restore brings the edits back (pill amber); Discard clears; after a Save, reload shows no bar (draft cleared AND `modified` bumped server-side — the Task 2.2 behaviour).
- [ ] **Step 8: Commit**
  ```bash
  git add src/builder/draft.js src/builder/__tests__/draft.test.js src/builder/StudioShell.jsx src/builder/builder.scss
  git commit -m "studio: localStorage draft recovery with restore/discard bar (1s debounce, modified compare)"
  ```

### Task 4.7: Retire the v1 builder components

**Files:**
- Delete: `src/builder/Canvas.jsx`, `src/builder/Preview.jsx`, `src/builder/FieldSettings.jsx`, `src/builder/ConditionEditor.jsx`, `src/builder/OptionsEditor.jsx`, `src/builder/SettingsTab.jsx`, `src/builder/ProTab.jsx`, `src/builder/FieldPalette.jsx`
- Modify: `assets/css/builder.css` (prune dead blocks)

By this point nothing imports the eight files (palette swapped in 4.1, canvas unmounted in 3.2, right column swapped in 4.5, Preview unreferenced since chunk 1). `FormulaPanel.jsx`, `CalculatorList.jsx`, `EntriesList.jsx`, `TemplatePicker.jsx` survive.

- [ ] **Step 1: Prove they are unreferenced**
  ```bash
  grep -rn "FieldPalette\|FieldSettings\|SettingsTab\|ProTab\|ConditionEditor\|OptionsEditor\|from './Canvas'\|from './Preview'" src/ || echo CLEAN
  ```
  Expected output: `CLEAN`. If anything prints, fix that import first.
- [ ] **Step 2: Delete**
  ```bash
  git rm src/builder/Canvas.jsx src/builder/Preview.jsx src/builder/FieldSettings.jsx src/builder/ConditionEditor.jsx src/builder/OptionsEditor.jsx src/builder/SettingsTab.jsx src/builder/ProTab.jsx src/builder/FieldPalette.jsx
  ```
- [ ] **Step 3: Prune `assets/css/builder.css`** — delete exactly the rule blocks for: `.alc-build`, `.alc-palette` (+ `h3`, `__btn`), `.alc-canvas` and every `.alc-canvas*` selector (incl. the shared `.alc-canvas--empty, .alc-settings--empty` rule), `.alc-settings`, `.alc-row3`, `.alc-condition`, `.alc-rule`, `.alc-actions`, `.alc-options*`, `.alc-row4`, `.alc-hint`, `.alc-settings-tab*`, `.alc-narrow`. KEEP (verified in use by surviving components): `.alc-app*`, `.alc-topbar*`, `.alc-title-input`, `.alc-unsaved`, `.alc-table*`, `.alc-row--new`, `.alc-badge*`, `.alc-empty`, `.alc-danger-zone*`, `.alc-template-*`, `.alc-modal-actions`, `.alc-formula__ok`, `.alc-pagination`, `.alc-app--loading`.
- [ ] **Step 4: Verify** — `npm run build` clean; `npm test` green; wp-env: list view, entries view, New Calculator template modal, and the full studio all render/style correctly.
- [ ] **Step 5: Commit**
  ```bash
  git add assets/css/builder.css
  git commit -m "studio: retire v1 builder components (canvas/palette/settings/preview tabs) + prune dead CSS"
  ```

### Task 4.8: Chunk 4 gates, builder bundle budget, studio smoke checklist

**Files:**
- Modify: `docs/qa-checklist.md` (budget line + studio smoke items)

- [ ] **Step 1: Run all gates** — `vendor/bin/phpunit`, `npm test`, `vendor/bin/phpcs`, `npm run build` — all green/clean.
- [ ] **Step 2: Measure and record the builder budget** (spec §6: "builder bundle measured at the first Studio chunk group, then enforced"):
  ```bash
  gzip -c build/index.js | wc -c
  ```
  Compute BUDGET = measured bytes × 1.15, rounded UP to the nearest 1024. In `docs/qa-checklist.md`, in the Gates code block after the `build/frontend.js` line, add (with the real numbers substituted):
  ```bash
  gzip -c build/index.js | wc -c      # < BUDGET  (builder studio; measured N at v2 chunk 4)
  ```
- [ ] **Step 3: Add studio smoke items** to the Functional smoke list in `docs/qa-checklist.md` (after item 2):
  ```markdown
  - [ ] Studio: open a calculator → real calculator renders on the canvas; type values → total updates instantly; add a field by click (inserts after selection) and by drag (insertion line); undo/redo via buttons and ⌘Z/⌘⇧Z (suppressed while typing in text inputs); IF pill and formula-error badge appear when applicable; theme quick-switch re-renders; Save → pill flashes green; reload → no draft bar; edit → reload without saving → draft bar restores.
  ```
- [ ] **Step 4: wp-env end-to-end pass** — run exactly that checklist item manually in the sandbox, plus: create a NEW calculator from the template modal → studio opens with preset fields; entries + list views unaffected.
- [ ] **Step 5: Commit**
  ```bash
  git add docs/qa-checklist.md
  git commit -m "qa: builder bundle gz budget + studio smoke checklist"
  ```

**Chunk-group boundary:** all gates green on `feat/v2-studio`; Track A complete. Chunks 5–7 (repeater + new field types) start from here.

# Alovio Calculator 2.0 — Chunks 5–7 (Track B: repeater, new types, file upload, slider polish)

> Continuation of `docs/superpowers/plans/2026-07-05-alovio-calculator-v2.md`. All Conventions from that skeleton apply (branch `feat/v2-studio`, gates after every chunk, explicit `git add` paths, no version bumps, literal `'alovio-calculator'` text domain, `alovio_calc_` prefixes). Spec: `docs/superpowers/specs/2026-07-05-alovio-calculator-v2-product-design.md` §3.

**Engine decisions locked for these chunks (read once, they recur everywhere):**

- A repeater's rows are **condition-independent** (children carry no logic), so row math is computed ONCE in a pre-pass; only the repeater's own visibility gates whether its sum enters the value/condition maps (exactly like formula fields in the existing fixed-point loop).
- Raw repeater value semantics (identical PHP/JS): value **absent or non-array** ⇒ `minRows` rows of child defaults (matches what the renderer shows initially — no-FOUC parity); **empty array** ⇒ zero rows ⇒ sum 0; rows beyond `maxRows` (hard-capped 50) are **sliced off** at the engine, and additionally **rejected with 400** at the quote endpoint (defense in depth).
- Row label rule (identical PHP/JS): `rowLabel` with `{n}` replaced by the 1-based row number; empty `rowLabel` falls back to `<repeater label> <n>`.
- `rowExpression` that fails to **compile** is kept in the config and surfaces at runtime as `errors[repeaterId]` with sum 0 (same convention as formula fields — the builder badge shows it). A rowExpression that compiles but references a **non-child id** is blanked to `''` by `FieldSchema` (price mode) — cross-scope data flow is structurally impossible.
- Summary: one line item per row — `{ id: '<repId>__<n>', label: rowLabel, amount: rowTotal, isCurrency: true, repeaterId: '<repId>' }`. The `repeaterId` key lets entries surfaces group rows without string parsing; it exists on BOTH sides (parity fixtures assert it).
- Note (deviation from the chunk table): the compute.js engine mirror lands in **Chunk 5**, not 6 — the chunk-5 Jest parity gate cannot pass without it. Chunk 6 keeps all DOM/quote/entries/builder work.

---

## Chunk 5: Repeater schema + engine + shared parity fixtures

**Spec:** §3.1 (schema, aggregation, validation). Everything in this chunk is pure logic — no DOM, no REST, no SCSS. Gates green at the end (renderer emits nothing for an unknown-to-it `repeater` type until Chunk 6, which is harmless).

### Task 5.1: `repeater` type flags in FieldTypes

**Files:**
- Modify: `tests/Unit/Fields/FieldTypesTest.php` (append tests)
- Modify: `includes/Fields/FieldTypes.php` (FREE line 11, REFERENCEABLE line 19, `is_condition_controller()` lines 38–40, new const + helper)

- [ ] **Step 1: Write the failing tests.** Append to `tests/Unit/Fields/FieldTypesTest.php` (inside the existing class — Brain Monkey's default `apply_filters` passthrough covers `all()`):

```php
	public function test_repeater_type_flags(): void {
		$this->assertContains( 'repeater', FieldTypes::all() );
		$this->assertTrue( FieldTypes::is_referenceable( 'repeater' ) );
		$this->assertTrue( FieldTypes::is_condition_controller( 'repeater' ) );
		$this->assertFalse( FieldTypes::is_input( 'repeater' ) );
		$this->assertFalse( FieldTypes::is_choice( 'repeater' ) );
	}

	public function test_repeater_child_type_allowlist(): void {
		foreach ( [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ] as $type ) {
			$this->assertTrue( FieldTypes::is_repeater_child( $type ), $type );
		}
		foreach ( [ 'text', 'heading', 'html', 'formula', 'step', 'repeater' ] as $type ) {
			$this->assertFalse( FieldTypes::is_repeater_child( $type ), $type );
		}
	}
```

- [ ] **Step 2: Run and see it FAIL.** `vendor/bin/phpunit --filter FieldTypesTest` → expect `Error: Call to undefined method ...is_repeater_child()` / assertion failure on `all()`.
- [ ] **Step 3: Implement.** In `includes/Fields/FieldTypes.php`:
  - Line 11: append `'repeater'` to `FREE`.
  - Line 19: append `'repeater'` to `REFERENCEABLE` (spec §3.1: `{repeater_id}` usable in formulas).
  - After the `INPUT` const, add:

```php
	/** Types allowed INSIDE a repeater (spec §3.1: no nesting, no content/formula children). */
	public const REPEATER_CHILD_TYPES = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ];

	public static function is_repeater_child( string $type ): bool {
		return in_array( $type, self::REPEATER_CHILD_TYPES, true );
	}
```

  - Replace the body of `is_condition_controller()` (line 39) with:

```php
		return self::is_input( $type ) || 'formula' === $type || 'repeater' === $type;
```

- [ ] **Step 4: Run and see it PASS.** `vendor/bin/phpunit --filter FieldTypesTest` → all green; then `vendor/bin/phpunit` → full suite green (repeater has no behavior yet).
- [ ] **Step 5: Commit.**

```bash
git add includes/Fields/FieldTypes.php tests/Unit/Fields/FieldTypesTest.php
git commit -m "Add repeater field type flags (referenceable, condition controller)"
```

### Task 5.2: FieldSchema — normalize the repeater

**Files:**
- Modify: `tests/Unit/Fields/FieldSchemaTest.php` (append tests)
- Modify: `includes/Fields/FieldSchema.php` (imports; `normalize()` line 54 call site; `normalize_field()` signature line 74 + new `case 'repeater'`; two new private methods)

- [ ] **Step 1: Write the failing tests.** Append to `tests/Unit/Fields/FieldSchemaTest.php`:

```php
	public function test_repeater_children_restricted_and_caps_enforced(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'minRows' => 0, 'maxRows' => 900,
				'rowLabel' => 'Room {n}', 'addLabel' => 'Add a room', 'fields' => [
				[ 'id' => 'area', 'type' => 'number', 'label' => 'Area', 'default' => 5 ],
				[ 'id' => 'notes', 'type' => 'text', 'label' => 'Not allowed inside' ],
				[ 'id' => 'nested', 'type' => 'repeater', 'label' => 'No nesting' ],
			] ],
		] ) );
		$rep = $out['fields'][0];
		$this->assertSame( 'repeater', $rep['type'] );
		$this->assertSame( [ 'area' ], array_column( $rep['fields'], 'id' ) );
		$this->assertSame( 1, $rep['minRows'] );      // clamped up to 1
		$this->assertSame( 50, $rep['maxRows'] );     // hard server cap
		$this->assertSame( 'Room {n}', $rep['rowLabel'] );
		$this->assertSame( 'Add a room', $rep['addLabel'] );
		$this->assertSame( '', $rep['rowExpression'] );
	}

	public function test_repeater_slug_uniqueness_across_levels_and_no_child_logic(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'area', 'type' => 'number', 'label' => 'Top-level area' ],
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'fields' => [
				[ 'id' => 'area', 'type' => 'number', 'label' => 'Duplicate of top level' ],
				[ 'id' => 'qty', 'type' => 'quantity', 'label' => 'Qty', 'conditions' => [
					[ 'field' => 'area', 'operator' => 'is', 'value' => '1' ],
				], 'conditionAction' => 'require' ],
			] ],
			[ 'id' => 'qty', 'type' => 'number', 'label' => 'Shadowed by child, dropped' ],
		] ) );
		$rep = $out['fields'][1];
		$this->assertSame( [ 'qty' ], array_column( $rep['fields'], 'id' ) ); // child 'area' dropped (dup)
		$this->assertSame( [], $rep['fields'][0]['conditions'] );             // v2.0: no child logic
		$this->assertSame( 'show', $rep['fields'][0]['conditionAction'] );
		$this->assertSame( [ 'area', 'rooms' ], array_column( $out['fields'], 'id' ) ); // later top-level dup dropped
	}

	public function test_row_expression_child_refs_only(): void {
		$fields = [
			[ 'id' => 'outside', 'type' => 'number', 'label' => 'Outside' ],
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'R', 'rowExpression' => '{area} * {outside}', 'fields' => [
				[ 'id' => 'area', 'type' => 'number', 'label' => 'Area' ],
			] ],
		];
		$out = FieldSchema::normalize( $this->config( $fields ) );
		$this->assertSame( '', $out['fields'][1]['rowExpression'] ); // cross-scope ref ⇒ blanked (price mode)

		$fields[1]['rowExpression'] = '{area} * 2';
		$ok = FieldSchema::normalize( $this->config( $fields ) );
		$this->assertSame( '{area} * 2', $ok['fields'][1]['rowExpression'] ); // child refs pass

		$fields[1]['rowExpression'] = '{area} * * 2';
		$bad = FieldSchema::normalize( $this->config( $fields ) );
		$this->assertSame( '{area} * * 2', $bad['fields'][1]['rowExpression'] ); // compile error kept (runtime badge, formula parity)
	}

	public function test_repeater_is_valid_controller_but_children_are_not(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'R', 'fields' => [
				[ 'id' => 'a', 'type' => 'number', 'label' => 'A' ],
			] ],
			[ 'id' => 'note', 'type' => 'heading', 'label' => 'N', 'conditions' => [
				[ 'field' => 'rooms', 'operator' => 'gte', 'value' => '100' ],
			] ],
			[ 'id' => 'note2', 'type' => 'heading', 'label' => 'N2', 'conditions' => [
				[ 'field' => 'a', 'operator' => 'gte', 'value' => '1' ],
			] ],
		] ) );
		$this->assertCount( 1, $out['fields'][1]['conditions'] ); // repeater sum drives conditions (spec §3.1)
		$this->assertSame( [], $out['fields'][2]['conditions'] ); // child ids are row-scoped, never controllers
	}
```

- [ ] **Step 2: Run and see it FAIL.** `vendor/bin/phpunit --filter FieldSchemaTest` → the four new tests fail (repeater fields currently get no type-specific keys; child handling absent).
- [ ] **Step 3: Implement — imports and plumbing.** In `includes/Fields/FieldSchema.php`:
  - Below `namespace` (line 2), add:

```php
use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Formula\FormulaError;
```

  - Add const next to `EXPRESSION_LIMIT` (line 7): `public const REPEATER_MAX_ROWS = 50;`
  - Line 54: change the call to `$fields[] = self::normalize_field( $field, $id, $type, $seen );`
  - Line 74: change the signature to `private static function normalize_field( array $raw, string $id, string $type, array &$seen ): array {`
- [ ] **Step 4: Implement — the repeater case.** In the `switch` inside `normalize_field()` (after the `case 'step':` block, line ~123), add:

```php
			case 'repeater':
				$field['fields']   = self::normalize_repeater_children( (array) ( $raw['fields'] ?? [] ), $seen );
				$min               = isset( $raw['minRows'] ) && is_numeric( $raw['minRows'] ) ? (int) $raw['minRows'] : 1;
				$max               = isset( $raw['maxRows'] ) && is_numeric( $raw['maxRows'] ) ? (int) $raw['maxRows'] : 10;
				$field['minRows']  = max( 1, min( $min, self::REPEATER_MAX_ROWS ) );
				$field['maxRows']  = max( $field['minRows'], min( $max, self::REPEATER_MAX_ROWS ) );
				$field['addLabel'] = sanitize_text_field( (string) ( $raw['addLabel'] ?? '' ) );
				$field['rowLabel'] = sanitize_text_field( (string) ( $raw['rowLabel'] ?? '' ) );
				$field['rowExpression'] = self::normalize_row_expression(
					substr( trim( (string) ( $raw['rowExpression'] ?? '' ) ), 0, self::EXPRESSION_LIMIT ),
					array_column( $field['fields'], 'id' )
				);
				break;
```

- [ ] **Step 5: Implement — the two helpers.** Append below `normalize_options()`:

```php
	/**
	 * Children are restricted to REPEATER_CHILD_TYPES (no nesting) and carry NO
	 * conditional logic in v2.0 (spec §3.1). $seen is the GLOBAL slug registry —
	 * uniqueness holds across all levels.
	 */
	private static function normalize_repeater_children( array $rawChildren, array &$seen ): array {
		$children = [];
		foreach ( $rawChildren as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$type = (string) ( $child['type'] ?? '' );
			$id   = sanitize_key( (string) ( $child['id'] ?? '' ) );
			if ( '' === $id || isset( $seen[ $id ] ) || ! FieldTypes::is_repeater_child( $type ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$normalized  = self::normalize_field( $child, $id, $type, $seen );
			$normalized['conditions']      = [];
			$normalized['conditionMatch']  = 'all';
			$normalized['conditionAction'] = 'show';
			$children[] = $normalized;
		}
		return $children;
	}

	/**
	 * A rowExpression may reference CHILD ids only (spec §3.1 graph rule). Refs are
	 * extracted with the real Lexer/Parser. Compile failures are KEPT — they surface
	 * at runtime exactly like broken formula fields (error badge, sum 0).
	 */
	private static function normalize_row_expression( string $expr, array $childIds ): string {
		if ( '' === $expr ) {
			return '';
		}
		try {
			$refs = Formula::references( Formula::compile( $expr ) );
		} catch ( FormulaError $e ) {
			return $expr;
		}
		return array_diff( $refs, $childIds ) ? '' : $expr;
	}
```

- [ ] **Step 6: Run and see it PASS.** `vendor/bin/phpunit --filter FieldSchemaTest` → green. `vendor/bin/phpunit` → full suite green. `vendor/bin/phpcs includes/Fields/FieldSchema.php includes/Fields/FieldTypes.php` → 0 errors.
- [ ] **Step 7: Commit.**

```bash
git add includes/Fields/FieldSchema.php tests/Unit/Fields/FieldSchemaTest.php
git commit -m "Normalize repeater schema: children, row caps, rowExpression scope"
```

### Task 5.3: Evaluation — repeater pre-pass, fixed-point wiring, per-row summary

**Files:**
- Modify: `tests/Unit/Logic/EvaluationTest.php` (append tests)
- Modify: `includes/Logic/Evaluation.php` (`run()` lines 20–107, `compute_values()` lines 119–142, three new private methods)

- [ ] **Step 1: Write the failing tests.** Append to `tests/Unit/Logic/EvaluationTest.php`:

```php
	private function repeater_config( string $rowExpression = '{r_area} * {r_rate}' ): array {
		return FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'rowLabel' => 'Room {n}',
				'minRows' => 1, 'maxRows' => 5, 'showInSummary' => true, 'rowExpression' => $rowExpression, 'fields' => [
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area', 'default' => 0 ],
				[ 'id' => 'r_rate', 'type' => 'select', 'label' => 'Rate', 'options' => [
					[ 'value' => 'opt_std', 'label' => 'Standard', 'price' => 6 ],
					[ 'value' => 'opt_dlx', 'label' => 'Deluxe', 'price' => 9 ],
				] ],
			] ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'Total', 'showInSummary' => true, 'expression' => '{rooms}' ],
		] ] );
	}

	public function test_repeater_rows_sum_and_per_row_summary(): void {
		$r = Evaluation::run( $this->repeater_config(), [ 'rooms' => [
			[ 'r_area' => '20', 'r_rate' => 'opt_std' ],
			[ 'r_area' => '10', 'r_rate' => 'opt_dlx' ],
		] ] );
		$this->assertSame( 2100000, $r['values']['rooms'] );   // 120 + 90
		$this->assertSame( 2100000, $r['totalScaled'] );
		$this->assertSame( [ 'rooms__1', 'rooms__2', 'total' ], array_column( $r['lineItems'], 'id' ) );
		$this->assertSame( 'Room 1', $r['lineItems'][0]['label'] );
		$this->assertSame( 'rooms', $r['lineItems'][0]['repeaterId'] );
		$rows = $r['repeaters']['rooms']['rows'];
		$this->assertSame( 'Standard', $rows[0]['values']['r_rate'] ); // display label, not slug
		$this->assertSame( '20', $rows[0]['values']['r_area'] );
	}

	public function test_repeater_absent_value_yields_min_rows_defaults_and_empty_array_zero(): void {
		$config = $this->repeater_config( '{r_area} * 2 + 3' );
		$absent = Evaluation::run( $config, [] );                       // 1 default row: 0*2+3
		$this->assertSame( 30000, $absent['values']['rooms'] );
		$empty = Evaluation::run( $config, [ 'rooms' => [] ] );          // zero rows
		$this->assertSame( 0, $empty['values']['rooms'] );
		$this->assertSame( [ 'total' ], array_column( $empty['lineItems'], 'id' ) );
	}

	public function test_repeater_runtime_and_compile_errors_zero_the_sum(): void {
		$runtime = Evaluation::run( $this->repeater_config( '{r_area} / 0' ), [ 'rooms' => [ [ 'r_area' => '5' ] ] ] );
		$this->assertSame( 0, $runtime['values']['rooms'] );
		$this->assertSame( 'div_zero', $runtime['errors']['rooms'] );

		$compile = Evaluation::run( $this->repeater_config( '{r_area} * * 2' ), [ 'rooms' => [ [ 'r_area' => '5' ] ] ] );
		$this->assertSame( 0, $compile['values']['rooms'] );
		$this->assertSame( 'syntax', $compile['errors']['rooms'] );
	}

	public function test_hidden_repeater_contributes_zero_and_can_drive_conditions(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'gate', 'type' => 'toggle', 'label' => 'Gate', 'price' => 0 ],
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'rowExpression' => '{r_qty} * 60',
				'conditions' => [ [ 'field' => 'gate', 'operator' => 'is', 'value' => '1' ] ], 'fields' => [
				[ 'id' => 'r_qty', 'type' => 'quantity', 'label' => 'Qty', 'default' => 1 ],
			] ],
			[ 'id' => 'bulk', 'type' => 'heading', 'label' => 'Bulk!', 'conditions' => [
				[ 'field' => 'rooms', 'operator' => 'gte', 'value' => '100' ],
			] ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'T', 'expression' => '{rooms} + 1' ],
		] ] );
		$off = Evaluation::run( $config, [ 'gate' => '', 'rooms' => [ [ 'r_qty' => '2' ] ] ] );
		$this->assertFalse( $off['active']['rooms'] );
		$this->assertSame( 0, $off['values']['rooms'] );
		$this->assertSame( 10000, $off['totalScaled'] );
		$this->assertFalse( $off['active']['bulk'] );

		$on = Evaluation::run( $config, [ 'gate' => '1', 'rooms' => [ [ 'r_qty' => '2' ] ] ] );
		$this->assertSame( 1200000, $on['values']['rooms'] );
		$this->assertTrue( $on['active']['bulk'] ); // 120 ≥ 100, via the fixed-point
	}
```

- [ ] **Step 2: Run and see it FAIL.** `vendor/bin/phpunit --filter EvaluationTest` → new tests fail (`values['rooms']` is 0/unset, no `repeaters` key).
- [ ] **Step 3: Implement — pre-pass in `run()`.** In `includes/Logic/Evaluation.php`, after the formula-compile block (after line 43, `$errors[ $id ] = 'cycle';` loop), insert:

```php
		// Repeater pre-pass (spec §3.1): children carry no logic, so row math is
		// pass-invariant — computed ONCE here. Visibility gating happens inside the
		// loop below, exactly like formula fields.
		$repeaters = [];
		foreach ( $fields as $field ) {
			if ( 'repeater' !== $field['type'] ) {
				continue;
			}
			$repeaters[ $field['id'] ] = self::repeater_result( $field, $rawValues[ $field['id'] ] ?? null );
			if ( '' !== $repeaters[ $field['id'] ]['error'] ) {
				$errors[ $field['id'] ] = $repeaters[ $field['id'] ]['error'];
			}
		}
```

- [ ] **Step 4: Implement — loop + final pass wiring.** Still in `run()`:
  - Both `compute_values(...)` calls (lines 57 and 76) gain the new argument: `self::compute_values( $fields, $active, $asts, $errors, $graph['order'], $rawValues, $tmpErrors, $repeaters )` (and `$evalErrors` variant).
  - Inside the `foreach ( $fields as $field )` that builds `$nextCond` (lines 59–65), after the `'formula'` branch add:

```php
					if ( 'repeater' === $field['type'] ) {
						$nextCond[ $field['id'] ] = ( $active[ $field['id'] ] ?? true )
							? DecimalMath::fromScaled( $repeaters[ $field['id'] ]['sum'] )
							: '';
					}
```

  - Restructure the summary loop (lines 82–97): replace the single `if ( empty(...showInSummary...) ... ) { continue; }` + push with:

```php
			if ( 'formula' === $field['type'] && ( $active[ $id ] ?? true ) ) {
				$totalScaled = $values[ $id ];
			}
			if ( empty( $field['showInSummary'] ) || false === ( $active[ $id ] ?? true ) ) {
				continue;
			}
			if ( 'repeater' === $field['type'] ) {
				foreach ( $repeaters[ $id ]['rows'] as $n => $row ) {
					$lineItems[] = [
						'id'         => $id . '__' . ( $n + 1 ),
						'label'      => $row['label'],
						'amount'     => $row['total'],
						'isCurrency' => true,
						'repeaterId' => $id,
					];
				}
				continue;
			}
			if ( ! isset( $values[ $id ] ) ) {
				continue;
			}
			$isCurrency  = 'formula' === $field['type'] || self::is_priced( $field );
			$lineItems[] = [
				'id'         => $id,
				'label'      => $field['label'],
				'amount'     => $values[ $id ],
				'isCurrency' => $isCurrency,
			];
```

  - Add `'repeaters' => $repeaters,` to the returned array (line 99–106 block).
- [ ] **Step 5: Implement — `compute_values()` + the three new methods.** Change the signature (line 119) to append `, array $repeaters = []` and insert as the FIRST statement of its `foreach ( $fields as $field )` input loop:

```php
			if ( 'repeater' === $field['type'] ) {
				$values[ $field['id'] ] = ( $active[ $field['id'] ] ?? true ) ? $repeaters[ $field['id'] ]['sum'] : 0;
				continue;
			}
```

  Append the new private methods at the end of the class:

```php
	/**
	 * Row math for one repeater (spec §3.1). Absent/non-array raw ⇒ minRows default
	 * rows (renderer parity); rows past maxRows sliced; rowExpression evaluated
	 * row-locally with the SAME Evaluator, or children's price contributions when empty.
	 *
	 * @param mixed $raw Array of row objects { childId: value } or anything else.
	 * @return array{sum:int, rows: array<int, array{label:string, total:int, values:array<string,string>}>, error:string}
	 */
	private static function repeater_result( array $field, $raw ): array {
		$children = (array) ( $field['fields'] ?? [] );
		$maxRows  = min( (int) ( $field['maxRows'] ?? 50 ), 50 );
		$rowsRaw  = is_array( $raw )
			? array_slice( array_values( $raw ), 0, $maxRows )
			: array_fill( 0, (int) ( $field['minRows'] ?? 1 ), [] );

		$ast = null;
		if ( '' !== (string) ( $field['rowExpression'] ?? '' ) ) {
			try {
				$ast = Formula::compile( $field['rowExpression'] );
			} catch ( FormulaError $e ) {
				return [
					'sum'   => 0,
					'rows'  => [],
					'error' => $e->getErrorCode(),
				];
			}
		}

		$sum   = 0;
		$rows  = [];
		$error = '';
		foreach ( $rowsRaw as $i => $rowRaw ) {
			$rowRaw   = is_array( $rowRaw ) ? $rowRaw : [];
			$rowMap   = [];
			$display  = [];
			$priceSum = 0;
			foreach ( $children as $child ) {
				$cid              = $child['id'];
				$v                = $rowRaw[ $cid ] ?? null;
				$rowMap[ $cid ]   = self::input_amount( $child, $v );
				$priceSum         = DecimalMath::add( $priceSum, $rowMap[ $cid ] );
				$display[ $cid ]  = self::display_value( $child, $v );
			}
			if ( null !== $ast ) {
				try {
					$total = Formula::evaluate( $ast, $rowMap );
				} catch ( FormulaError $e ) {
					$total = 0;
					$error = $e->getErrorCode();
				}
			} else {
				$total = $priceSum;
			}
			$rows[] = [
				'label'  => self::row_label( $field, $i + 1 ),
				'total'  => $total,
				'values' => $display,
			];
			$sum = DecimalMath::add( $sum, $total );
		}

		return [
			'sum'   => $sum,
			'rows'  => $rows,
			'error' => $error,
		];
	}

	/** "{n}" substitution; empty template falls back to "<label> <n>". Mirrored in compute.js. */
	private static function row_label( array $field, int $n ): string {
		$tpl = (string) ( $field['rowLabel'] ?? '' );
		if ( '' === $tpl ) {
			return trim( (string) ( $field['label'] ?? '' ) . ' ' . $n );
		}
		return str_replace( '{n}', (string) $n, $tpl );
	}

	/** Human-readable child value for entries surfaces (PHP-only; not part of JS parity). */
	private static function display_value( array $child, $v ): string {
		switch ( $child['type'] ) {
			case 'number':
			case 'slider':
			case 'quantity':
				return DecimalMath::fromScaled( DecimalMath::toScaled( self::clamped_number( $child, $v ) ) );
			case 'select':
			case 'radio':
				$slug = self::valid_slug( $child, is_string( $v ) ? $v : '' );
				foreach ( $child['options'] as $opt ) {
					if ( $opt['value'] === $slug ) {
						return $opt['label'];
					}
				}
				return '';
			case 'checkbox_group':
				$selected = self::valid_slugs( $child, is_array( $v ) ? $v : [] );
				$labels   = [];
				foreach ( $child['options'] as $opt ) {
					if ( in_array( $opt['value'], $selected, true ) ) {
						$labels[] = $opt['label'];
					}
				}
				return implode( ', ', $labels );
			case 'toggle':
				return self::toggle_on( $child, $v ) ? '1' : '';
		}
		return '';
	}
```

- [ ] **Step 6: Run and see it PASS.** `vendor/bin/phpunit --filter EvaluationTest` → green; `vendor/bin/phpunit` → full suite green; `vendor/bin/phpcs includes/Logic/Evaluation.php` → 0.
- [ ] **Step 7: Commit.**

```bash
git add includes/Logic/Evaluation.php tests/Unit/Logic/EvaluationTest.php
git commit -m "Compute repeater sums in an Evaluation pre-pass with per-row summary items"
```

### Task 5.4: Shared fixture file + PHP parity test

**Files:**
- Create: `tests/fixtures/repeater-cases.json`
- Create: `tests/Unit/Logic/RepeaterCasesTest.php`

- [ ] **Step 1: Create the fixture.** All field arrays are HAND-NORMALIZED (the exact shape `FieldSchema::normalize` emits — same convention as `compute.test.js`). `repeater` block = raw row-level expectations (pre-visibility); `expected` = full-run results. Amount strings are `fromScaled` renderings. Write `tests/fixtures/repeater-cases.json`:

```json
{
    "cases": [
        {
            "name": "price mode rows sum child contributions",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": true, "minRows": 1, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "", "fields": [
                    { "id": "r_rate", "type": "select", "label": "Rate", "options": [ { "value": "opt_std", "label": "Standard", "price": 100, "image": 0 }, { "value": "opt_dlx", "label": "Deluxe", "price": 150, "image": 0 } ] },
                    { "id": "r_express", "type": "toggle", "label": "Express", "price": 15, "default": false }
                ] },
                { "id": "total", "type": "formula", "label": "Total", "showInSummary": true, "expression": "{rooms}" }
            ],
            "values": { "rooms": [ { "r_rate": "opt_std", "r_express": "1" }, { "r_rate": "opt_dlx" } ] },
            "repeater": { "id": "rooms", "sum": "265", "rows": [ { "label": "Room 1", "total": "115" }, { "label": "Room 2", "total": "150" } ] },
            "expected": {
                "values": { "rooms": "265", "total": "265" },
                "total": "265",
                "lineItems": [
                    { "id": "rooms__1", "label": "Room 1", "amount": "115", "isCurrency": true, "repeaterId": "rooms" },
                    { "id": "rooms__2", "label": "Room 2", "amount": "150", "isCurrency": true, "repeaterId": "rooms" },
                    { "id": "total", "label": "Total", "amount": "265", "isCurrency": true }
                ]
            }
        },
        {
            "name": "rowExpression evaluates row-locally",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": false, "minRows": 1, "maxRows": 10, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "{r_area} * {r_rate}", "fields": [
                    { "id": "r_area", "type": "number", "label": "Area", "min": null, "max": null, "step": null, "default": 0 },
                    { "id": "r_rate", "type": "select", "label": "Rate", "options": [ { "value": "opt_std", "label": "Standard", "price": 2.5, "image": 0 }, { "value": "opt_dlx", "label": "Deluxe", "price": 4, "image": 0 } ] }
                ] },
                { "id": "total", "type": "formula", "label": "Total", "showInSummary": true, "expression": "{rooms}" }
            ],
            "values": { "rooms": [ { "r_area": "20", "r_rate": "opt_std" }, { "r_area": "10", "r_rate": "opt_dlx" } ] },
            "repeater": { "id": "rooms", "sum": "90", "rows": [ { "label": "Room 1", "total": "50" }, { "label": "Room 2", "total": "40" } ] },
            "expected": { "values": { "rooms": "90", "total": "90" }, "total": "90" }
        },
        {
            "name": "rows beyond maxRows are sliced off",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": false, "minRows": 1, "maxRows": 2, "addLabel": "", "rowLabel": "", "rowExpression": "{r_qty} * 10", "fields": [
                    { "id": "r_qty", "type": "number", "label": "Qty", "min": null, "max": null, "step": null, "default": 0 }
                ] }
            ],
            "values": { "rooms": [ { "r_qty": "1" }, { "r_qty": "2" }, { "r_qty": "3" } ] },
            "repeater": { "id": "rooms", "sum": "30", "rows": [ { "label": "Rooms 1", "total": "10" }, { "label": "Rooms 2", "total": "20" } ] },
            "expected": { "values": { "rooms": "30" } }
        },
        {
            "name": "absent value yields minRows default rows",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": false, "minRows": 2, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "{r_area} * 3", "fields": [
                    { "id": "r_area", "type": "number", "label": "Area", "min": null, "max": null, "step": null, "default": 5 }
                ] }
            ],
            "values": {},
            "repeater": { "id": "rooms", "sum": "30", "rows": [ { "label": "Room 1", "total": "15" }, { "label": "Room 2", "total": "15" } ] },
            "expected": { "values": { "rooms": "30" } }
        },
        {
            "name": "hidden repeater contributes zero",
            "fields": [
                { "id": "gate", "type": "toggle", "label": "Gate", "price": 0, "default": false },
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": true, "minRows": 1, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "{r_qty} * 10", "conditions": [ { "field": "gate", "operator": "is", "value": "1" } ], "conditionMatch": "all", "conditionAction": "show", "fields": [
                    { "id": "r_qty", "type": "quantity", "label": "Qty", "min": null, "max": null, "step": null, "default": 5 }
                ] },
                { "id": "total", "type": "formula", "label": "Total", "showInSummary": true, "expression": "{rooms} + 1" }
            ],
            "values": { "gate": "" },
            "repeater": { "id": "rooms", "sum": "50", "rows": [ { "label": "Room 1", "total": "50" } ] },
            "expected": {
                "values": { "rooms": "0", "total": "1" },
                "total": "1",
                "active": { "rooms": false },
                "lineItems": [ { "id": "total", "label": "Total", "amount": "1", "isCurrency": true } ]
            }
        },
        {
            "name": "repeater referenced in a top formula",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": false, "minRows": 1, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "{r_qty}", "fields": [
                    { "id": "r_qty", "type": "number", "label": "Qty", "min": null, "max": null, "step": null, "default": 0 }
                ] },
                { "id": "total", "type": "formula", "label": "Total", "showInSummary": true, "expression": "{rooms} * 1.5 + 10" }
            ],
            "values": { "rooms": [ { "r_qty": "40" } ] },
            "repeater": { "id": "rooms", "sum": "40", "rows": [ { "label": "Room 1", "total": "40" } ] },
            "expected": { "values": { "rooms": "40", "total": "70" }, "total": "70" }
        },
        {
            "name": "repeater sum drives a condition",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": false, "minRows": 1, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "", "fields": [
                    { "id": "r_extra", "type": "toggle", "label": "Extra", "price": 60, "default": false }
                ] },
                { "id": "bulk_note", "type": "heading", "label": "Bulk discount applies", "conditions": [ { "field": "rooms", "operator": "gte", "value": "100" } ], "conditionMatch": "all", "conditionAction": "show" },
                { "id": "total", "type": "formula", "label": "Total", "showInSummary": true, "expression": "{rooms}" }
            ],
            "values": { "rooms": [ { "r_extra": "1" }, { "r_extra": "1" } ] },
            "repeater": { "id": "rooms", "sum": "120", "rows": [ { "label": "Room 1", "total": "60" }, { "label": "Room 2", "total": "60" } ] },
            "expected": { "values": { "rooms": "120" }, "total": "120", "active": { "bulk_note": true } }
        },
        {
            "name": "empty rows array yields zero and no summary lines",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": true, "minRows": 1, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "{r_qty} * 10", "fields": [
                    { "id": "r_qty", "type": "number", "label": "Qty", "min": null, "max": null, "step": null, "default": 5 }
                ] },
                { "id": "total", "type": "formula", "label": "Total", "showInSummary": true, "expression": "{rooms}" }
            ],
            "values": { "rooms": [] },
            "repeater": { "id": "rooms", "sum": "0", "rows": [] },
            "expected": {
                "values": { "rooms": "0", "total": "0" },
                "total": "0",
                "lineItems": [ { "id": "total", "label": "Total", "amount": "0", "isCurrency": true } ]
            }
        },
        {
            "name": "decimal-heavy row math stays exact",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": false, "minRows": 1, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "{r_a} + {r_b}", "fields": [
                    { "id": "r_a", "type": "number", "label": "A", "min": null, "max": null, "step": null, "default": 0 },
                    { "id": "r_b", "type": "number", "label": "B", "min": null, "max": null, "step": null, "default": 0 }
                ] }
            ],
            "values": { "rooms": [ { "r_a": "0.1", "r_b": "0.2" }, { "r_a": "4.1", "r_b": "8.2" } ] },
            "repeater": { "id": "rooms", "sum": "12.6", "rows": [ { "label": "Room 1", "total": "0.3" }, { "label": "Room 2", "total": "12.3" } ] },
            "expected": { "values": { "rooms": "12.6" } }
        },
        {
            "name": "rowExpression runtime error zeroes the row",
            "fields": [
                { "id": "rooms", "type": "repeater", "label": "Rooms", "showInSummary": false, "minRows": 1, "maxRows": 5, "addLabel": "", "rowLabel": "Room {n}", "rowExpression": "{r_a} / {r_b}", "fields": [
                    { "id": "r_a", "type": "number", "label": "A", "min": null, "max": null, "step": null, "default": 0 },
                    { "id": "r_b", "type": "number", "label": "B", "min": null, "max": null, "step": null, "default": 0 }
                ] }
            ],
            "values": { "rooms": [ { "r_a": "10", "r_b": "0" } ] },
            "repeater": { "id": "rooms", "sum": "0", "rows": [ { "label": "Room 1", "total": "0" } ] },
            "expected": { "values": { "rooms": "0" } }
        }
    ]
}
```

- [ ] **Step 2: Create the PHP parity test.** Write `tests/Unit/Logic/RepeaterCasesTest.php` (mirrors `FormulaCasesTest`'s fixture wiring — one JSON, dataProvider, expected strings compared via `fromScaled`):

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Logic;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Logic\Evaluation;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class RepeaterCasesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/** @dataProvider casesProvider */
	public function test_fixture_case( array $case ): void {
		$r = Evaluation::run( [ 'fields' => $case['fields'] ], $case['values'] );

		foreach ( $case['expected']['values'] as $id => $want ) {
			$this->assertSame( $want, DecimalMath::fromScaled( (int) $r['values'][ $id ] ), "value {$id}" );
		}
		if ( array_key_exists( 'total', $case['expected'] ) ) {
			$this->assertSame( $case['expected']['total'], DecimalMath::fromScaled( (int) $r['totalScaled'] ) );
		}
		foreach ( ( $case['expected']['active'] ?? [] ) as $id => $want ) {
			$this->assertSame( $want, $r['active'][ $id ], "active {$id}" );
		}
		if ( isset( $case['expected']['lineItems'] ) ) {
			$got = [];
			foreach ( $r['lineItems'] as $item ) {
				$g = [
					'id'         => $item['id'],
					'label'      => $item['label'],
					'amount'     => DecimalMath::fromScaled( $item['amount'] ),
					'isCurrency' => $item['isCurrency'],
				];
				if ( isset( $item['repeaterId'] ) ) {
					$g['repeaterId'] = $item['repeaterId'];
				}
				$got[] = $g;
			}
			$this->assertSame( $case['expected']['lineItems'], $got );
		}

		// Row-level block: raw (pre-visibility) sum + rows.
		$rep = $r['repeaters'][ $case['repeater']['id'] ];
		$this->assertSame( $case['repeater']['sum'], DecimalMath::fromScaled( $rep['sum'] ) );
		$rows = [];
		foreach ( $rep['rows'] as $row ) {
			$rows[] = [
				'label' => $row['label'],
				'total' => DecimalMath::fromScaled( $row['total'] ),
			];
		}
		$this->assertSame( $case['repeater']['rows'], $rows );
	}

	public function casesProvider(): iterable {
		$json = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/repeater-cases.json' ), true );
		foreach ( $json['cases'] as $case ) {
			yield $case['name'] => [ $case ];
		}
	}
}
```

- [ ] **Step 3: Run and see it PASS** (engine landed in 5.3): `vendor/bin/phpunit --filter RepeaterCasesTest` → 10 tests green. If any case fails, the ENGINE is wrong (fixture numbers above are hand-derived) — fix the engine, never the fixture.
- [ ] **Step 4: Commit.**

```bash
git add tests/fixtures/repeater-cases.json tests/Unit/Logic/RepeaterCasesTest.php
git commit -m "Add shared repeater parity fixtures with PHP consumer"
```

### Task 5.5: compute.js mirror + Jest parity consumer

**Files:**
- Create: `src/frontend/__tests__/repeater-parity.test.js`
- Modify: `src/frontend/compute.js` (imports line 6; `prepare()` lines 17–43; `computeValues()` lines 136–158; `run()` lines 172–219; new exported `repeaterResult`)

- [ ] **Step 1: Write the failing Jest parity test.** Create `src/frontend/__tests__/repeater-parity.test.js`:

```js
/**
 * JS side of tests/fixtures/repeater-cases.json — same file the PHP
 * RepeaterCasesTest consumes. Full-run parity via run() + raw row-level
 * parity via repeaterResult() (pre-visibility, like the PHP pre-pass).
 */
import cases from '../../../tests/fixtures/repeater-cases.json';
import { prepare, run, repeaterResult } from '../compute';
import { fromScaled } from '../../shared/formula';

describe( 'repeater PHP/JS parity fixtures', () => {
	cases.cases.forEach( ( c ) => {
		it( c.name, () => {
			const prepared = prepare( c.fields );
			const r = run( c.fields, prepared, c.values );

			Object.entries( c.expected.values ).forEach( ( [ id, want ] ) => {
				expect( fromScaled( r.values[ id ] ) ).toBe( want );
			} );
			if ( 'total' in c.expected ) {
				expect( fromScaled( r.totalScaled || 0 ) ).toBe( c.expected.total );
			}
			Object.entries( c.expected.active || {} ).forEach( ( [ id, want ] ) => {
				expect( r.active[ id ] ).toBe( want );
			} );
			if ( c.expected.lineItems ) {
				expect(
					r.lineItems.map( ( i ) => {
						const out = { id: i.id, label: i.label, amount: fromScaled( i.amount ), isCurrency: i.isCurrency };
						if ( i.repeaterId ) {
							out.repeaterId = i.repeaterId;
						}
						return out;
					} )
				).toEqual( c.expected.lineItems );
			}

			const field = c.fields.find( ( f ) => f.id === c.repeater.id );
			const rep = repeaterResult( field, prepared.repeaters[ field.id ], c.values[ field.id ] );
			expect( fromScaled( rep.sum ) ).toBe( c.repeater.sum );
			expect( rep.rows.map( ( row ) => ( { label: row.label, total: fromScaled( row.total ) } ) ) ).toEqual( c.repeater.rows );
		} );
	} );
} );
```

- [ ] **Step 2: Run and see it FAIL.** `npm test -- -t 'repeater PHP/JS parity'` → fails (`prepared.repeaters` undefined, no `repeaterResult` export).
- [ ] **Step 3: Implement in `src/frontend/compute.js`.**
  - Line 6, extend the decimal import: `import { add } from '../shared/formula/decimal';` (new line below the existing shared import).
  - In `prepare()` (before the `return` at line 42), add row-expression compilation and return it:

```js
	const repeaters = {};
	fields
		.filter( ( f ) => f.type === 'repeater' )
		.forEach( ( f ) => {
			if ( ! f.rowExpression ) {
				repeaters[ f.id ] = { ast: null, error: null };
				return;
			}
			try {
				repeaters[ f.id ] = { ast: compile( f.rowExpression ), error: null };
			} catch ( e ) {
				if ( ! ( e instanceof FormulaError ) ) {
					throw e;
				}
				repeaters[ f.id ] = { ast: null, error: e.code };
			}
		} );
	return { asts, errors, order: graph.order, repeaters };
```

  - Below `isPriced()` (line 133), add the mirror of `Evaluation::repeater_result` (JS skips the PHP-only `values` display map):

```js
/** "{n}" substitution; empty template falls back to "<label> <n>" (PHP row_label mirror). */
function rowLabel( field, n ) {
	const tpl = field.rowLabel || '';
	return tpl !== '' ? tpl.replace( '{n}', String( n ) ) : `${ field.label || '' } ${ n }`.trim();
}

/**
 * Mirror of Evaluation::repeater_result — condition-independent row math.
 * Exported for the shared parity fixtures.
 */
export function repeaterResult( field, prepared, raw ) {
	if ( prepared.error ) {
		return { sum: 0, rows: [], error: prepared.error };
	}
	const children = field.fields || [];
	const maxRows = Math.min( Number( field.maxRows ?? 50 ), 50 );
	const rowsRaw = Array.isArray( raw )
		? raw.slice( 0, maxRows )
		: Array.from( { length: Number( field.minRows ?? 1 ) }, () => ( {} ) );

	let sum = 0;
	let error = null;
	const rows = rowsRaw.map( ( rowRaw, i ) => {
		const source = rowRaw && typeof rowRaw === 'object' && ! Array.isArray( rowRaw ) ? rowRaw : {};
		const rowMap = {};
		let priceSum = 0;
		children.forEach( ( child ) => {
			rowMap[ child.id ] = inputAmount( child, source[ child.id ] );
			priceSum = add( priceSum, rowMap[ child.id ] );
		} );
		let total;
		if ( prepared.ast ) {
			try {
				total = evaluate( prepared.ast, rowMap );
			} catch ( e ) {
				if ( ! ( e instanceof FormulaError ) ) {
					throw e;
				}
				total = 0;
				error = e.code;
			}
		} else {
			total = priceSum;
		}
		sum = add( sum, total );
		return { label: rowLabel( field, i + 1 ), total };
	} );
	return { sum, rows, error };
}
```

  - In `computeValues()` (line 136): change the signature to `function computeValues( fields, prepared, active, rawValues, reps )` and replace the first `fields.filter(...)` block with:

```js
	fields.forEach( ( f ) => {
		if ( f.type === 'repeater' ) {
			values[ f.id ] = active[ f.id ] !== false ? reps[ f.id ].sum : 0;
			return;
		}
		if ( ! REFERENCEABLE_INPUTS.includes( f.type ) ) {
			return;
		}
		values[ f.id ] = active[ f.id ] !== false ? inputAmount( f, rawValues[ f.id ] ) : 0;
	} );
```

  - In `run()` (line 172): after `const baseCond = ...` add the pre-pass; thread `reps` through all three `computeValues` calls; extend the `nextCond` loop and the summary loop:

```js
	const reps = {};
	fields
		.filter( ( f ) => f.type === 'repeater' )
		.forEach( ( f ) => {
			reps[ f.id ] = repeaterResult( f, prepared.repeaters[ f.id ], rawValues[ f.id ] );
		} );
```

```js
			// inside the fields.forEach building nextCond, after the formula branch:
			if ( f.type === 'repeater' ) {
				nextCond[ f.id ] = active[ f.id ] !== false ? fromScaled( reps[ f.id ].sum ) : '';
			}
```

```js
		// summary loop: replace the single push block with
		if ( f.type === 'formula' && active[ f.id ] !== false ) {
			totalScaled = values[ f.id ];
		}
		if ( ! f.showInSummary || active[ f.id ] === false ) {
			return;
		}
		if ( f.type === 'repeater' ) {
			reps[ f.id ].rows.forEach( ( row, i ) => {
				lineItems.push( {
					id: `${ f.id }__${ i + 1 }`,
					label: row.label,
					amount: row.total,
					isCurrency: true,
					repeaterId: f.id,
				} );
			} );
			return;
		}
		if ( ! ( f.id in values ) ) {
			return;
		}
		lineItems.push( { id: f.id, label: f.label, amount: values[ f.id ], isCurrency: f.type === 'formula' || isPriced( f ) } );
```

- [ ] **Step 4: Run and see it PASS.** `npm test -- -t 'repeater PHP/JS parity'` → 10 green. Then `npm test` → full Jest suite green (87 baseline + new), `vendor/bin/phpunit` → green, `npm run build` → compiles.
- [ ] **Step 5: Chunk gate.** Run all four gates from the Conventions block; all green.
- [ ] **Step 6: Commit.**

```bash
git add src/frontend/compute.js src/frontend/__tests__/repeater-parity.test.js
git commit -m "Mirror repeater evaluation in compute.js with Jest parity consumer"
```

---

## Chunk 6: Repeater frontend, quote flow, entries surfaces, builder UX

**Spec:** §3.1 (frontend / quote flow / entries surfaces / builder). Depends on Chunk 5's engine (both sides) and touches Chunk 1's reducer history + Chunk 4's panel chrome only at clearly-marked seams.

### Task 6.1: CalculatorRenderer — rows, `<template>`, controls

**Files:**
- Modify: `tests/Unit/Frontend/CalculatorRendererTest.php` (append test)
- Modify: `includes/Frontend/CalculatorRenderer.php` (`render_input()` switch lines 85–183; three new private methods)

- [ ] **Step 1: Write the failing test.** Append to `tests/Unit/Frontend/CalculatorRendererTest.php` (its setUp already stubs `esc_*`, keeps `<b>` through `sanitize_text_field` for escaping assertions):

```php
	public function test_repeater_renders_rows_template_and_controls(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms <b>x</b>', 'minRows' => 2, 'maxRows' => 5,
				'rowLabel' => 'Room {n}', 'addLabel' => 'Add room', 'rowExpression' => '', 'fields' => [
				[ 'id' => 'r_rate', 'type' => 'radio', 'label' => 'Rate', 'options' => [
					[ 'value' => 'opt_a', 'label' => 'A', 'price' => 1 ],
				] ],
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area', 'default' => 3 ],
			] ],
		] ] );
		$html = CalculatorRenderer::render( 7, $config );

		// 2 server-rendered initial rows (minRows) + 1 inert copy inside <template>.
		$this->assertSame( 3, substr_count( $html, '<div class="alc-repeater__row" data-alc-row>' ) );
		$this->assertStringContainsString( '<template data-alc-row-template>', $html );
		$this->assertStringContainsString( 'name="alc_rooms_r_rate_1"', $html );      // row-scoped radio names
		$this->assertStringContainsString( 'name="alc_rooms_r_rate_2"', $html );
		$this->assertStringContainsString( 'name="alc_rooms_r_rate___ROW__"', $html ); // template placeholder
		$this->assertStringContainsString( 'data-alc-child="r_area"', $html );
		$this->assertStringContainsString( 'data-alc-add', $html );
		$this->assertStringContainsString( 'Add room', $html );
		$this->assertStringContainsString( 'data-alc-row-label>Room 1<', $html );
		$this->assertStringContainsString( 'Rooms &lt;b&gt;x&lt;/b&gt;', $html );      // legend escaped
		$this->assertStringNotContainsString( '<b>x</b>', $html );
	}
```

- [ ] **Step 2: Run and see it FAIL.** `vendor/bin/phpunit --filter CalculatorRendererTest` → new test fails (repeater renders empty string).
- [ ] **Step 3: Implement.** In `includes/Frontend/CalculatorRenderer.php`, add to the `render_input()` switch (before `case 'step':`, line ~175):

```php
			case 'repeater':
				return self::render_repeater( $field, $result );
```

  Append the three private methods (file uses `array()` style + `declare( strict_types=1 )`):

```php
	/** Rows container + one inert row copy in <template> (spec §3.1: PHP renders row markup ONCE). */
	private static function render_repeater( array $field, array $result ): string {
		$rows    = '';
		$initial = isset( $result['repeaters'][ $field['id'] ]['rows'] ) ? $result['repeaters'][ $field['id'] ]['rows'] : array();
		$count   = max( 1, count( $initial ) );
		for ( $i = 1; $i <= $count; $i++ ) {
			$rows .= self::render_repeater_row( $field, (string) $i );
		}
		$add = '' !== $field['addLabel'] ? $field['addLabel'] : __( 'Add row', 'alovio-calculator' );

		return '<fieldset class="alc-repeater"><legend>' . esc_html( $field['label'] ) . '</legend>'
			. '<div class="alc-repeater__rows" data-alc-rows>' . $rows . '</div>'
			. '<template data-alc-row-template>' . self::render_repeater_row( $field, '__ROW__' ) . '</template>'
			. '<button type="button" class="alc-repeater__add" data-alc-add>' . esc_html( $add ) . '</button>'
			. '</fieldset>';
	}

	private static function render_repeater_row( array $field, string $index ): string {
		$children = '';
		foreach ( $field['fields'] as $child ) {
			$children .= sprintf(
				'<div class="alc-repeater__child alc-repeater__child--%s" data-alc-child="%s">%s</div>',
				esc_attr( $child['type'] ),
				esc_attr( $child['id'] ),
				self::render_repeater_child( $field['id'], $child, $index )
			);
		}
		$label = '' !== $field['rowLabel']
			? str_replace( '{n}', $index, $field['rowLabel'] )
			: trim( $field['label'] . ' ' . $index );

		return '<div class="alc-repeater__row" data-alc-row>'
			. '<div class="alc-repeater__row-head">'
			. '<span class="alc-repeater__row-label" data-alc-row-label>' . esc_html( $label ) . '</span>'
			. '<button type="button" class="alc-repeater__remove" data-alc-remove aria-label="' . esc_attr__( 'Remove row', 'alovio-calculator' ) . '">&times;</button>'
			. '</div><div class="alc-repeater__row-fields">' . $children . '</div></div>';
	}

	/**
	 * Child controls use IMPLICIT label association (no id/for — nothing to reindex on
	 * clone). Only radio/checkbox names carry the row index; JS renumbers those.
	 * Option images are deliberately not rendered inside rows (template weight).
	 */
	private static function render_repeater_child( string $repId, array $child, string $index ): string {
		$label = esc_html( $child['label'] );
		switch ( $child['type'] ) {
			case 'number':
			case 'quantity':
				return sprintf( '<label>%s<input type="number"%s value="%s"></label>', $label, self::range_attrs( $child ), esc_attr( self::default_number( $child ) ) );
			case 'slider':
				$value = self::default_number( $child );
				return sprintf(
					'<label>%1$s<span class="alc-slider"><input type="range"%2$s value="%3$s"><output>%3$s</output></span></label>',
					$label,
					self::range_attrs( $child ),
					esc_attr( $value )
				);
			case 'select':
				$options = '<option value="">' . esc_html__( '— select —', 'alovio-calculator' ) . '</option>';
				foreach ( $child['options'] as $opt ) {
					$options .= sprintf( '<option value="%s">%s</option>', esc_attr( $opt['value'] ), esc_html( $opt['label'] ) );
				}
				return sprintf( '<label>%s<select>%s</select></label>', $label, $options );
			case 'radio':
			case 'checkbox_group':
				$type  = 'radio' === $child['type'] ? 'radio' : 'checkbox';
				$name  = sprintf( 'alc_%s_%s_%s', $repId, $child['id'], $index );
				$items = '';
				foreach ( $child['options'] as $opt ) {
					$items .= sprintf(
						'<label class="alc-choice"><input type="%1$s" name="%2$s" value="%3$s"><span class="alc-choice__label">%4$s</span></label>',
						$type,
						esc_attr( $name ),
						esc_attr( $opt['value'] ),
						esc_html( $opt['label'] )
					);
				}
				return sprintf( '<fieldset class="alc-choices"><legend>%s</legend>%s</fieldset>', $label, $items );
			case 'toggle':
				$checked = ! empty( $child['default'] ) ? ' checked' : '';
				return sprintf(
					'<label class="alc-toggle"><input type="checkbox"%s><span class="alc-toggle__track" aria-hidden="true"></span>%s</label>',
					$checked,
					$label
				);
		}
		return '';
	}
```

- [ ] **Step 4: Run and see it PASS.** `vendor/bin/phpunit --filter CalculatorRendererTest` → green; `vendor/bin/phpcs includes/Frontend/CalculatorRenderer.php` → 0.
- [ ] **Step 5: Commit.**

```bash
git add includes/Frontend/CalculatorRenderer.php tests/Unit/Frontend/CalculatorRendererTest.php
git commit -m "Render repeater rows, template and controls server-side"
```

### Task 6.2: repeater.js + value collection

**Files:**
- Create: `src/frontend/repeater.js`
- Create: `src/frontend/__tests__/repeater-dom.test.js`
- Modify: `src/frontend/calculator.js` (`collectRawValues` lines 7–44 refactor + export; wire `setupRepeaters` in `initCalculator` line ~165)

- [ ] **Step 1: Write the failing jsdom test.** Create `src/frontend/__tests__/repeater-dom.test.js`:

```js
/** @jest-environment jsdom */
import { setupRepeaters } from '../repeater';
import { collectRawValues } from '../calculator';

const FIELD = {
	id: 'rooms', type: 'repeater', label: 'Rooms', minRows: 1, maxRows: 2, rowLabel: 'Room {n}',
	fields: [
		{ id: 'r_area', type: 'number', label: 'Area' },
		{ id: 'r_rate', type: 'radio', label: 'Rate', options: [ { value: 'opt_a', label: 'A', price: 1 } ] },
	],
};

const row = ( index ) => `
	<div class="alc-repeater__row" data-alc-row>
		<div class="alc-repeater__row-head"><span data-alc-row-label>Room ${ index }</span><button type="button" data-alc-remove>×</button></div>
		<div class="alc-repeater__row-fields">
			<div data-alc-child="r_area"><label>Area<input type="number" value="5"></label></div>
			<div data-alc-child="r_rate"><fieldset><label><input type="radio" name="alc_rooms_r_rate_${ index }" value="opt_a"></label></fieldset></div>
		</div>
	</div>`;

function mount() {
	document.body.innerHTML = `
		<div class="alc-calculator">
			<div class="alc-field alc-field--repeater" data-alc-field="rooms"><fieldset class="alc-repeater">
				<div class="alc-repeater__rows" data-alc-rows>${ row( 1 ) }</div>
				<template data-alc-row-template>${ row( '__ROW__' ) }</template>
				<button type="button" data-alc-add>Add row</button>
			</fieldset></div>
		</div>`;
	return document.querySelector( '.alc-calculator' );
}

describe( 'repeater DOM behaviour', () => {
	it( 'adds rows from the template up to maxRows, renumbering names and labels', () => {
		const root = mount();
		const onChange = jest.fn();
		setupRepeaters( root, [ FIELD ], onChange );
		const add = root.querySelector( '[data-alc-add]' );

		add.click();
		const rows = root.querySelectorAll( '[data-alc-rows] [data-alc-row]' );
		expect( rows ).toHaveLength( 2 );
		expect( rows[ 1 ].querySelector( '[data-alc-row-label]' ).textContent ).toBe( 'Room 2' );
		expect( rows[ 1 ].querySelector( 'input[type="radio"]' ).name ).toBe( 'alc_rooms_r_rate_2' );
		expect( add.disabled ).toBe( true ); // maxRows reached
		expect( onChange ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'removes rows down to minRows and hides remove buttons at the floor', () => {
		const root = mount();
		setupRepeaters( root, [ FIELD ], jest.fn() );
		root.querySelector( '[data-alc-add]' ).click();
		root.querySelector( '[data-alc-rows] [data-alc-row] [data-alc-remove]' ).click();
		const rows = root.querySelectorAll( '[data-alc-rows] [data-alc-row]' );
		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].querySelector( '[data-alc-remove]' ).hidden ).toBe( true );
		expect( rows[ 0 ].querySelector( '[data-alc-row-label]' ).textContent ).toBe( 'Room 1' );
	} );

	it( 'collectRawValues returns one object per row keyed by child id', () => {
		const root = mount();
		setupRepeaters( root, [ FIELD ], jest.fn() );
		root.querySelector( '[data-alc-add]' ).click();
		root.querySelectorAll( '[data-alc-child="r_area"] input' )[ 1 ].value = '9';
		root.querySelectorAll( 'input[type="radio"]' )[ 1 ].checked = true;
		const raw = collectRawValues( root, [ FIELD ] );
		expect( raw.rooms ).toEqual( [ { r_area: '5', r_rate: '' }, { r_area: '9', r_rate: 'opt_a' } ] );
	} );
} );
```

- [ ] **Step 2: Run and see it FAIL.** `npm test -- -t 'repeater DOM behaviour'` → module not found / no export.
- [ ] **Step 3: Create `src/frontend/repeater.js`** (complete file):

```js
/**
 * Repeater row management (spec §3.1): the server renders row markup once in a
 * <template>; this module clones it, renumbers labels + radio/checkbox names,
 * and enforces minRows/maxRows. Value changes bubble to the calculator's own
 * input/change listeners; add/remove call onChange() explicitly.
 */

function rowLabel( field, n ) {
	const tpl = field.rowLabel || '';
	return tpl !== '' ? tpl.replace( '{n}', String( n ) ) : `${ field.label || '' } ${ n }`.trim();
}

function setupRepeater( root, field, onChange ) {
	const wrap = root.querySelector( `[data-alc-field="${ field.id }"]` );
	if ( ! wrap ) {
		return;
	}
	const rowsEl = wrap.querySelector( '[data-alc-rows]' );
	const template = wrap.querySelector( '[data-alc-row-template]' );
	const addBtn = wrap.querySelector( '[data-alc-add]' );
	if ( ! rowsEl || ! template || ! addBtn ) {
		return;
	}
	const rows = () => rowsEl.querySelectorAll( '[data-alc-row]' );

	const renumber = () => {
		const all = rows();
		all.forEach( ( row, i ) => {
			const label = row.querySelector( '[data-alc-row-label]' );
			if ( label ) {
				label.textContent = rowLabel( field, i + 1 );
			}
			row.querySelectorAll( '[name]' ).forEach( ( input ) => {
				// Names end in "_<row>"; the template ships "___ROW__".
				input.name = input.name.replace( /_(?:__ROW__|\d+)$/, `_${ i + 1 }` );
			} );
		} );
		addBtn.disabled = all.length >= field.maxRows;
		all.forEach( ( row ) => {
			const remove = row.querySelector( '[data-alc-remove]' );
			if ( remove ) {
				remove.hidden = all.length <= field.minRows;
			}
		} );
	};

	addBtn.addEventListener( 'click', () => {
		if ( rows().length >= field.maxRows ) {
			return;
		}
		rowsEl.appendChild( template.content.firstElementChild.cloneNode( true ) );
		renumber();
		onChange();
	} );

	wrap.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-alc-remove]' );
		if ( ! btn || ! wrap.contains( btn ) || rows().length <= field.minRows ) {
			return;
		}
		btn.closest( '[data-alc-row]' ).remove();
		renumber();
		onChange();
	} );

	renumber();
}

export function setupRepeaters( root, fields, onChange ) {
	fields.filter( ( f ) => f.type === 'repeater' ).forEach( ( f ) => setupRepeater( root, f, onChange ) );
}
```

- [ ] **Step 4: Refactor `collectRawValues` in `src/frontend/calculator.js`.** Replace lines 7–44 with a `readValue( scope, type )` helper + exported collector (behavior identical for existing types):

```js
/** Read one field-shaped value from a scope element (a field wrapper or a repeater child cell). */
function readValue( scope, type ) {
	switch ( type ) {
		case 'number':
		case 'slider':
		case 'quantity':
		case 'text': {
			const input = scope.querySelector( 'input' );
			return input ? input.value : '';
		}
		case 'select': {
			const select = scope.querySelector( 'select' );
			return select ? select.value : '';
		}
		case 'radio': {
			const checked = scope.querySelector( 'input:checked' );
			return checked ? checked.value : '';
		}
		case 'checkbox_group':
			return Array.from( scope.querySelectorAll( 'input:checked' ) ).map( ( i ) => i.value );
		case 'toggle': {
			const box = scope.querySelector( 'input[type="checkbox"]' );
			return box && box.checked ? '1' : '';
		}
	}
	return undefined;
}

/** Collect raw values from the DOM, scoped by [data-alc-field] wrappers. Exported for tests. */
export function collectRawValues( root, fields ) {
	const raw = {};
	fields.forEach( ( f ) => {
		const wrap = root.querySelector( `[data-alc-field="${ f.id }"]` );
		if ( ! wrap ) {
			return;
		}
		if ( f.type === 'repeater' ) {
			const rows = [];
			wrap.querySelectorAll( '[data-alc-rows] [data-alc-row]' ).forEach( ( rowEl ) => {
				const row = {};
				( f.fields || [] ).forEach( ( child ) => {
					const cell = rowEl.querySelector( `[data-alc-child="${ child.id }"]` );
					const v = cell ? readValue( cell, child.type ) : undefined;
					if ( v !== undefined ) {
						row[ child.id ] = v;
					}
				} );
				rows.push( row );
			} );
			raw[ f.id ] = rows;
			return;
		}
		const v = readValue( wrap, f.type );
		if ( v !== undefined ) {
			raw[ f.id ] = v;
		}
	} );
	return raw;
}
```

  Then in `initCalculator()` (line ~165, next to `wireQuoteForm`), add `import { setupRepeaters } from './repeater';` at the top and call `setupRepeaters( root, fields, recompute );` immediately before the initial `recompute();`.
- [ ] **Step 5: Run and see it PASS.** `npm test -- -t 'repeater DOM behaviour'` → 3 green; `npm test` → suite green; `npm run build` → compiles.
- [ ] **Step 6: Commit.**

```bash
git add src/frontend/repeater.js src/frontend/calculator.js src/frontend/__tests__/repeater-dom.test.js
git commit -m "Add repeater row UX and row-aware value collection"
```

### Task 6.3: QuoteController — payload caps + repeater snapshot

**Files:**
- Modify: `tests/Unit/Entries/QuoteControllerTest.php` (append tests)
- Modify: `includes/Entries/QuoteController.php` (values sanitation lines 68–77; snapshot lines 109–114; two new public static helpers)

- [ ] **Step 1: Write the failing tests.** Append to `tests/Unit/Entries/QuoteControllerTest.php`:

```php
	private function repeater_field(): array {
		return [
			'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'maxRows' => 2,
			'fields' => [
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area' ],
				[ 'id' => 'r_extras', 'type' => 'checkbox_group', 'label' => 'Extras', 'options' => [] ],
			],
		];
	}

	public function test_sanitize_repeater_rows_caps_and_filters(): void {
		$field = $this->repeater_field();
		// Over the cap ⇒ null (the endpoint answers 400, spec §3.1 server guards).
		$this->assertNull( QuoteController::sanitize_repeater_rows( $field, [ [], [], [] ] ) );
		// Unknown child keys dropped, scalars truncated, checkbox arrays stringified.
		$rows = QuoteController::sanitize_repeater_rows( $field, [
			[ 'r_area' => str_repeat( '9', 600 ), 'ghost' => 'x', 'r_extras' => [ 'opt_a', 7 ] ],
			'not-a-row',
		] );
		$this->assertSame( 500, strlen( $rows[0]['r_area'] ) );
		$this->assertArrayNotHasKey( 'ghost', $rows[0] );
		$this->assertSame( [ 'opt_a', '7' ], $rows[0]['r_extras'] );
		$this->assertSame( [], $rows[1] ); // garbage row ⇒ empty row object
		// Garbage instead of an array ⇒ zero rows (never trusted).
		$this->assertSame( [], QuoteController::sanitize_repeater_rows( $field, 'hax' ) );
	}

	public function test_repeater_snapshot_keeps_active_repeaters_with_labels(): void {
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		$fields = [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'fields' => [
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area' ],
				[ 'id' => 'r_rate', 'type' => 'select', 'label' => 'Rate', 'options' => [] ],
			] ],
			[ 'id' => 'hidden_rep', 'type' => 'repeater', 'label' => 'Hidden', 'fields' => [] ],
		];
		$result = [
			'active'    => [ 'rooms' => true, 'hidden_rep' => false ],
			'repeaters' => [
				'rooms'      => [ 'sum' => 1200000, 'rows' => [ [ 'label' => 'Room 1', 'total' => 1200000, 'values' => [ 'r_area' => '20', 'r_rate' => 'Standard' ] ] ], 'error' => '' ],
				'hidden_rep' => [ 'sum' => 0, 'rows' => [], 'error' => '' ],
			],
		];
		$snap = QuoteController::repeater_snapshot( $fields, $result );
		$this->assertCount( 1, $snap );
		$this->assertSame( 'rooms', $snap[0]['id'] );
		$this->assertSame( [ 'r_area' => 'Area', 'r_rate' => 'Rate' ], $snap[0]['children'] );
		$this->assertSame( [ 'r_area' => 'number', 'r_rate' => 'select' ], $snap[0]['types'] );
		$this->assertSame( 'Room 1', $snap[0]['rows'][0]['label'] );
		$this->assertSame( 1200000, $snap[0]['rows'][0]['total'] );
		$this->assertSame( '20', $snap[0]['rows'][0]['values']['r_area'] );
	}
```

- [ ] **Step 2: Run and see it FAIL.** `vendor/bin/phpunit --filter QuoteControllerTest` → undefined methods.
- [ ] **Step 3: Implement the pure helpers.** Append to `includes/Entries/QuoteController.php` (below `validate_contact()`):

```php
	/**
	 * Sanitize one repeater's submitted rows. Pure, unit-tested.
	 *
	 * @param array $field Normalized repeater field.
	 * @param mixed $raw   Client-submitted rows.
	 * @return array|null Cleaned rows, or NULL when the row count exceeds maxRows (⇒ 400).
	 */
	public static function sanitize_repeater_rows( array $field, $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$rows = array_values( $raw );
		$cap  = min( (int) ( $field['maxRows'] ?? 50 ), 50 );
		if ( count( $rows ) > $cap ) {
			return null;
		}
		$child_ids = array_column( (array) ( $field['fields'] ?? array() ), 'id' );
		$out       = array();
		foreach ( $rows as $row ) {
			$clean = array();
			if ( is_array( $row ) ) {
				foreach ( $child_ids as $cid ) {
					if ( ! array_key_exists( $cid, $row ) ) {
						continue;
					}
					$v = $row[ $cid ];
					if ( is_array( $v ) ) {
						$clean[ $cid ] = array_map( 'strval', array_slice( $v, 0, 50 ) );
					} elseif ( is_scalar( $v ) ) {
						$clean[ $cid ] = substr( (string) $v, 0, self::VALUE_LIMIT );
					}
				}
			}
			$out[] = $clean;
		}
		return $out;
	}

	/**
	 * Repeater block for the entry snapshot (spec §3.1 entries surfaces): one entry
	 * per ACTIVE repeater in field order, child id => label/type legends included so
	 * surfaces never re-read the calculator config.
	 *
	 * @return array[]
	 */
	public static function repeater_snapshot( array $fields, array $result ): array {
		$out = array();
		foreach ( $fields as $field ) {
			if ( 'repeater' !== $field['type'] || false === ( $result['active'][ $field['id'] ] ?? true ) ) {
				continue;
			}
			$children = array();
			$types    = array();
			foreach ( (array) ( $field['fields'] ?? array() ) as $child ) {
				$children[ $child['id'] ] = $child['label'];
				$types[ $child['id'] ]    = $child['type'];
			}
			$rows = array();
			foreach ( (array) ( $result['repeaters'][ $field['id'] ]['rows'] ?? array() ) as $row ) {
				$rows[] = array(
					'label'  => sanitize_text_field( (string) $row['label'] ),
					'total'  => (int) $row['total'],
					'values' => array_map( 'sanitize_text_field', $row['values'] ),
				);
			}
			$out[] = array(
				'id'       => $field['id'],
				'label'    => $field['label'],
				'children' => $children,
				'types'    => $types,
				'rows'     => $rows,
			);
		}
		return $out;
	}
```

- [ ] **Step 4: Wire into `handle()`.** Replace the raw-values sanitation block (lines 68–77) with:

```php
		$rawValues = $request->get_param( 'values' );
		$rawValues = is_array( $rawValues ) ? array_slice( $rawValues, 0, self::VALUES_MAX, true ) : array();

		$repeater_ids = array();
		foreach ( $config['fields'] as $field ) {
			if ( 'repeater' !== $field['type'] || ! array_key_exists( $field['id'], $rawValues ) ) {
				continue;
			}
			$rows = self::sanitize_repeater_rows( $field, $rawValues[ $field['id'] ] );
			if ( null === $rows ) {
				return $this->bad_request( 'too_many_rows', __( 'Too many rows submitted.', 'alovio-calculator' ) );
			}
			$rawValues[ $field['id'] ]      = $rows;
			$repeater_ids[ $field['id'] ]   = true;
		}
		foreach ( $rawValues as $k => $v ) {
			if ( isset( $repeater_ids[ $k ] ) ) {
				continue; // Already row-sanitized above.
			}
			if ( is_string( $v ) && strlen( $v ) > self::VALUE_LIMIT ) {
				$rawValues[ $k ] = substr( $v, 0, self::VALUE_LIMIT );
			}
			if ( is_array( $v ) ) {
				$rawValues[ $k ] = array_map( 'strval', array_slice( $v, 0, 50 ) );
			}
		}
		if ( strlen( (string) wp_json_encode( $rawValues ) ) > 65535 ) {
			return $this->bad_request( 'too_large', __( 'The submitted data is too large.', 'alovio-calculator' ) );
		}
```

  And extend the `$snapshot` array (line 109) with one line after `'lineItems'`:

```php
			'repeaters'   => self::repeater_snapshot( $config['fields'], $result ),
```

- [ ] **Step 5: Run and see it PASS.** `vendor/bin/phpunit --filter QuoteControllerTest` → green; `vendor/bin/phpunit` → suite green; `vendor/bin/phpcs includes/Entries/QuoteController.php` → 0.
- [ ] **Step 6: Commit.**

```bash
git add includes/Entries/QuoteController.php tests/Unit/Entries/QuoteControllerTest.php
git commit -m "Guard repeater quote payloads and snapshot grouped rows"
```

### Task 6.4: Entries surfaces — CSV cell, email lines, detail modal

**Files:**
- Modify: `tests/Unit/Entries/CsvExporterTest.php`, `tests/Unit/Entries/EntryMailerTest.php` (append tests)
- Modify: `includes/Entries/CsvExporter.php` (COLUMNS line 10, `handle()` lines 33–49, new `repeater_cell()`)
- Modify: `includes/Entries/EntryMailer.php` (line-items loop lines 24–27, new `repeater_lines()`)
- Modify: `src/builder/EntriesList.jsx` (imports line 4, modal lines 114–144)

- [ ] **Step 1: Write the failing PHP tests.** Append to `CsvExporterTest`:

```php
	public function test_repeater_cell_flattens_rows_per_spec(): void {
		$snapshot = [
			'currency'  => [ 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ],
			'repeaters' => [ [
				'id' => 'rooms', 'label' => 'Rooms',
				'children' => [ 'r_area' => 'Area', 'r_rate' => 'Rate' ],
				'types'    => [ 'r_area' => 'number', 'r_rate' => 'select' ],
				'rows'     => [
					[ 'label' => 'Room 1', 'total' => 1200000, 'values' => [ 'r_area' => '20', 'r_rate' => 'Standard' ] ],
					[ 'label' => 'Room 2', 'total' => 900000, 'values' => [ 'r_area' => '10', 'r_rate' => '' ] ],
				],
			] ],
		];
		$this->assertSame(
			'Room 1: r_area=20, r_rate=Standard ($120.00) | Room 2: r_area=10 ($90.00)',
			CsvExporter::repeater_cell( $snapshot )
		);
		$this->assertSame( '', CsvExporter::repeater_cell( [] ) );
	}
```

  Append to `EntryMailerTest` (uses the same snapshot shape):

```php
	public function test_repeater_lines_use_child_labels_and_currency(): void {
		$snapshot = [
			'currency'  => [ 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ],
			'repeaters' => [ [
				'id' => 'rooms', 'label' => 'Rooms',
				'children' => [ 'r_area' => 'Area', 'r_express' => 'Express' ],
				'types'    => [ 'r_area' => 'number', 'r_express' => 'toggle' ],
				'rows'     => [ [ 'label' => 'Room 1', 'total' => 1200000, 'values' => [ 'r_area' => '20', 'r_express' => '1' ] ] ],
			] ],
		];
		$this->assertSame(
			[ 'Room 1: Area 20, Express — $120.00' ],
			EntryMailer::repeater_lines( $snapshot, 'rooms' )
		);
		$this->assertSame( [], EntryMailer::repeater_lines( $snapshot, 'ghost' ) );
	}
```

- [ ] **Step 2: Run and see it FAIL.** `vendor/bin/phpunit --filter 'CsvExporterTest|EntryMailerTest'` → undefined methods.
- [ ] **Step 3: Implement `CsvExporter`.**
  - Line 10: `private const COLUMNS = array( 'id', 'calculator_id', 'created_at', 'name', 'email', 'phone', 'message', 'total', 'status', 'repeaters', 'snapshot' );` — then check this test file for a hard-coded header expectation and update it to the new column list.
  - In `handle()`, inside the `foreach ( $rows as $row )` loop (line 45), before echoing: `$row['repeaters'] = self::repeater_cell( (array) ( json_decode( (string) ( $row['snapshot'] ?? '' ), true ) ?: array() ) );`
  - Append (uses `Alovio\Calculator\Frontend\CurrencyFormatter` — add the `use` import at the top):

```php
	/**
	 * Spec §3.1: ONE cell per entry — rows joined with " | ", each as
	 * "Room 1: r_area=20, r_rate=Standard ($120.00)" (keys = child IDS, values =
	 * display labels). Empty displays skipped; the csv_row() injection guard and
	 * RFC-4180 quoting then apply to the whole cell unchanged.
	 */
	public static function repeater_cell( array $snapshot ): string {
		$parts = array();
		foreach ( (array) ( $snapshot['repeaters'] ?? array() ) as $rep ) {
			foreach ( (array) ( $rep['rows'] ?? array() ) as $row ) {
				$vals = array();
				foreach ( (array) ( $row['values'] ?? array() ) as $cid => $display ) {
					if ( '' === (string) $display ) {
						continue;
					}
					$vals[] = $cid . '=' . $display;
				}
				$money   = CurrencyFormatter::format( (int) ( $row['total'] ?? 0 ), (array) ( $snapshot['currency'] ?? array() ) + array( 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ) );
				$parts[] = $row['label'] . ( $vals ? ': ' . implode( ', ', $vals ) : ':' ) . ' (' . $money . ')';
			}
		}
		return implode( ' | ', $parts );
	}
```

- [ ] **Step 4: Implement `EntryMailer`.** Replace the line-items loop (lines 24–26) with repeater-aware output and append the helper (+ `use Alovio\Calculator\Frontend\CurrencyFormatter;`):

```php
		$printed = array();
		foreach ( $snapshot['lineItems'] as $item ) {
			$repId = (string) ( $item['repeaterId'] ?? '' );
			if ( '' !== $repId ) {
				if ( isset( $printed[ $repId ] ) ) {
					continue; // All rows of this repeater were already expanded below.
				}
				$printed[ $repId ] = true;
				foreach ( self::repeater_lines( $snapshot, $repId ) as $line ) {
					$lines[] = $line;
				}
				continue;
			}
			$lines[] = $item['label'] . ': ' . DecimalMath::fromScaled( $item['amount'] );
		}
```

```php
	/**
	 * Detail-modal-style lines (spec §3.1): "Room 1: Area 20, Rate Standard — $120.00".
	 * Toggle children print their label alone; empty displays are skipped. Pure, unit-tested.
	 *
	 * @return string[]
	 */
	public static function repeater_lines( array $snapshot, string $repId ): array {
		$lines = array();
		foreach ( (array) ( $snapshot['repeaters'] ?? array() ) as $rep ) {
			if ( ( $rep['id'] ?? '' ) !== $repId ) {
				continue;
			}
			foreach ( (array) ( $rep['rows'] ?? array() ) as $row ) {
				$parts = array();
				foreach ( (array) ( $row['values'] ?? array() ) as $cid => $display ) {
					if ( '' === (string) $display ) {
						continue;
					}
					$label   = (string) ( $rep['children'][ $cid ] ?? $cid );
					$parts[] = 'toggle' === (string) ( $rep['types'][ $cid ] ?? '' ) ? $label : $label . ' ' . $display;
				}
				$money   = CurrencyFormatter::format( (int) ( $row['total'] ?? 0 ), (array) ( $snapshot['currency'] ?? array() ) + array( 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ) );
				$lines[] = $row['label'] . ': ' . implode( ', ', $parts ) . ( $parts ? ' — ' : ' ' ) . $money;
			}
		}
		return $lines;
	}
```

- [ ] **Step 5: Run and see it PASS.** `vendor/bin/phpunit --filter 'CsvExporterTest|EntryMailerTest'` → green (fix any pre-existing header assertion); full `vendor/bin/phpunit` green; `vendor/bin/phpcs includes/Entries/CsvExporter.php includes/Entries/EntryMailer.php` → 0.
- [ ] **Step 6: Detail modal.** In `src/builder/EntriesList.jsx`:
  - Line 4 area: add `import { formatCurrency } from '../shared/currency';`
  - In the modal (line ~121), change the line-items map to skip repeater rows: `open.snapshot.lineItems.filter( ( item ) => ! item.repeaterId ).map( ... )` (body unchanged).
  - Directly ABOVE the line-items `<table>`, insert the grouped rows block:

```jsx
					{ open.snapshot && Array.isArray( open.snapshot.repeaters ) && open.snapshot.repeaters.map( ( rep ) => (
						<div key={ rep.id } className="alc-entry-repeater">
							<strong>{ rep.label }</strong>
							<ul>
								{ ( rep.rows || [] ).map( ( row, i ) => (
									<li key={ i }>
										{ row.label }
										{ ': ' }
										{ Object.entries( row.values || {} )
											.filter( ( [ , v ] ) => v !== '' )
											.map( ( [ cid, v ] ) => ( ( rep.types || {} )[ cid ] === 'toggle'
												? ( rep.children || {} )[ cid ] || cid
												: `${ ( rep.children || {} )[ cid ] || cid } ${ v }` ) )
											.join( ', ' ) }
										{ ' — ' }
										{ formatCurrency( row.total || 0, open.snapshot.currency ) }
									</li>
								) ) }
							</ul>
						</div>
					) ) }
```

- [ ] **Step 7: Verify + commit.** `npm test` green, `npm run build` compiles.

```bash
git add includes/Entries/CsvExporter.php includes/Entries/EntryMailer.php src/builder/EntriesList.jsx tests/Unit/Entries/CsvExporterTest.php tests/Unit/Entries/EntryMailerTest.php
git commit -m "Surface repeater rows in CSV export, notification email and entry detail"
```

### Task 6.5: Builder — child-aware reducer actions + "Row fields" panel

**Files:**
- Create: `src/builder/__tests__/reducer.test.js`
- Create: `src/builder/panels/RepeaterFields.jsx`
- Modify: `src/builder/reducer.js` (DEFAULTS lines 11–24, switch lines 38–103, actions lines 105–114)
- Seam note: Chunk 4 owns the panel chrome (`SettingsPanel.jsx` + `panels/OptionsTab.jsx`). This task delivers the repeater-specific panel COMPONENT and reducer actions; the only chrome edit is mounting `<RepeaterFields />` in the Options-tab slot when `field.type === 'repeater'`, and registering the four new action types in Chunk 1's history `remember()` list.

- [ ] **Step 1: Write the failing reducer tests.** Create `src/builder/__tests__/reducer.test.js`:

```js
import { reducer, actions, initialState, REPEATER_CHILD_TYPES } from '../reducer';

const withRepeater = () => reducer( initialState, { type: 'ADD_FIELD', fieldType: 'repeater', id: 'rep1' } );

describe( 'repeater child actions', () => {
	it( 'exposes the restricted child type list', () => {
		expect( REPEATER_CHILD_TYPES ).toEqual( [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ] );
	} );

	it( 'ADD_CHILD_FIELD appends a typed child to the parent only', () => {
		let s = withRepeater();
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'number', id: 'c1' } );
		const rep = s.fields.find( ( f ) => f.id === 'rep1' );
		expect( rep.fields ).toHaveLength( 1 );
		expect( rep.fields[ 0 ] ).toMatchObject( { id: 'c1', type: 'number', label: 'Number' } );
		expect( s.fields ).toHaveLength( 1 ); // child did NOT land at the top level
	} );

	it( 'UPDATE_CHILD_FIELD patches one child in place', () => {
		let s = withRepeater();
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'number', id: 'c1' } );
		s = reducer( s, { type: 'UPDATE_CHILD_FIELD', parentId: 'rep1', id: 'c1', patch: { label: 'Area', default: 5 } } );
		expect( s.fields[ 0 ].fields[ 0 ] ).toMatchObject( { label: 'Area', default: 5 } );
	} );

	it( 'REMOVE_CHILD_FIELD and REORDER_CHILD manage the child list', () => {
		let s = withRepeater();
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'number', id: 'c1' } );
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'toggle', id: 'c2' } );
		s = reducer( s, { type: 'REORDER_CHILD', parentId: 'rep1', from: 1, to: 0 } );
		expect( s.fields[ 0 ].fields.map( ( c ) => c.id ) ).toEqual( [ 'c2', 'c1' ] );
		s = reducer( s, { type: 'REORDER_CHILD', parentId: 'rep1', from: 0, to: 5 } );
		expect( s.fields[ 0 ].fields.map( ( c ) => c.id ) ).toEqual( [ 'c2', 'c1' ] ); // out of range ignored
		s = reducer( s, { type: 'REMOVE_CHILD_FIELD', parentId: 'rep1', id: 'c2' } );
		expect( s.fields[ 0 ].fields.map( ( c ) => c.id ) ).toEqual( [ 'c1' ] );
	} );
} );
```

- [ ] **Step 2: Run and see it FAIL.** `npm test -- -t 'repeater child actions'` → `DEFAULTS.repeater` missing, unknown actions fall through.
- [ ] **Step 3: Implement in `src/builder/reducer.js`.**
  - DEFAULTS (after `step`, line 23): `repeater: { label: 'Repeater', fields: [], minRows: 1, maxRows: 10, addLabel: '', rowLabel: 'Row {n}', rowExpression: '', showInSummary: true },`
  - Export next to DEFAULTS: `export const REPEATER_CHILD_TYPES = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ];`
  - Add a helper above `reducer()`:

```js
function mapParent( state, parentId, fn ) {
	return {
		...state,
		fields: state.fields.map( ( f ) => ( f.id === parentId && f.type === 'repeater' ? fn( f ) : f ) ),
	};
}
```

  - New switch cases (before `default:`):

```js
		case 'ADD_CHILD_FIELD': {
			if ( ! REPEATER_CHILD_TYPES.includes( action.fieldType ) ) {
				return state;
			}
			const defaults = DEFAULTS[ action.fieldType ];
			const child = { id: action.id, type: action.fieldType, ...defaults };
			if ( defaults.options ) {
				child.options = cloneOptions( defaults.options );
			}
			return mapParent( state, action.parentId, ( p ) => ( { ...p, fields: [ ...( p.fields || [] ), child ] } ) );
		}
		case 'UPDATE_CHILD_FIELD':
			return mapParent( state, action.parentId, ( p ) => ( {
				...p,
				fields: ( p.fields || [] ).map( ( c ) => ( c.id === action.id ? { ...c, ...action.patch } : c ) ),
			} ) );
		case 'REMOVE_CHILD_FIELD':
			return mapParent( state, action.parentId, ( p ) => ( {
				...p,
				fields: ( p.fields || [] ).filter( ( c ) => c.id !== action.id ),
			} ) );
		case 'REORDER_CHILD':
			return mapParent( state, action.parentId, ( p ) => {
				const children = [ ...( p.fields || [] ) ];
				if ( action.to < 0 || action.to >= children.length || action.from < 0 || action.from >= children.length ) {
					return p;
				}
				const [ moved ] = children.splice( action.from, 1 );
				children.splice( action.to, 0, moved );
				return { ...p, fields: children };
			} );
```

  - Action creators (exact shapes — Chunk 1's history wrapper and Chunk 4's panel both consume these):

```js
	addChildField: ( parentId, fieldType ) => ( { type: 'ADD_CHILD_FIELD', parentId, fieldType, id: makeId() } ),
	updateChildField: ( parentId, id, patch ) => ( { type: 'UPDATE_CHILD_FIELD', parentId, id, patch } ),
	removeChildField: ( parentId, id ) => ( { type: 'REMOVE_CHILD_FIELD', parentId, id } ),
	reorderChild: ( parentId, from, to ) => ( { type: 'REORDER_CHILD', parentId, from, to } ),
```

- [ ] **Step 4: Register history.** Locate Chunk 1's `remember()` action list (`grep -n "REMEMBERED\|remember" src/builder/reducer.js`) and add the four types `ADD_CHILD_FIELD`, `UPDATE_CHILD_FIELD`, `REMOVE_CHILD_FIELD`, `REORDER_CHILD` so child edits are undoable. (If Chunk 1 has not landed in this worktree, leave a `// history: added in chunk 1` note is NOT acceptable — coordinate; the branch order guarantees it exists.)
- [ ] **Step 5: Run and see it PASS.** `npm test -- -t 'repeater child actions'` → green.
- [ ] **Step 6: Create `src/builder/panels/RepeaterFields.jsx`** (complete component; local child selection, store mutations via the new actions):

```jsx
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';
import { REPEATER_CHILD_TYPES } from '../reducer';
import OptionsEditor from '../OptionsEditor';

const TYPE_LABELS = {
	number: __( 'Number', 'alovio-calculator' ),
	slider: __( 'Slider', 'alovio-calculator' ),
	select: __( 'Dropdown', 'alovio-calculator' ),
	radio: __( 'Radio', 'alovio-calculator' ),
	checkbox_group: __( 'Checkbox group', 'alovio-calculator' ),
	toggle: __( 'Toggle', 'alovio-calculator' ),
	quantity: __( 'Quantity', 'alovio-calculator' ),
};
const HAS_RANGE = [ 'number', 'slider', 'quantity' ];
const HAS_OPTIONS = [ 'select', 'radio', 'checkbox_group' ];

function num( v ) {
	return v === '' || v === null || v === undefined ? null : v;
}

/** "Row fields" editor — lives in the Options-tab slot when a repeater is selected (spec §3.1). */
export default function RepeaterFields( { field } ) {
	const { addChildField, updateChildField, removeChildField, reorderChild, updateField } = useDispatch( STORE );
	const [ childId, setChildId ] = useState( null );
	const [ newType, setNewType ] = useState( 'number' );
	const children = field.fields || [];
	const child = children.find( ( c ) => c.id === childId ) || null;
	const set = ( patch ) => updateField( field.id, patch );
	const setChild = ( patch ) => updateChildField( field.id, child.id, patch );

	return (
		<div className="alc-repeater-panel">
			<span className="alc-options__title">{ __( 'Row fields', 'alovio-calculator' ) }</span>
			{ children.map( ( c, i ) => (
				<div className={ `alc-repeater-panel__row${ c.id === childId ? ' is-selected' : '' }` } key={ c.id }>
					<button type="button" className="alc-repeater-panel__pick" onClick={ () => setChildId( c.id ) }>
						{ c.label || c.id } <em>({ TYPE_LABELS[ c.type ] })</em>
					</button>
					<Button size="small" disabled={ i === 0 } onClick={ () => reorderChild( field.id, i, i - 1 ) } aria-label={ __( 'Move up', 'alovio-calculator' ) }>↑</Button>
					<Button size="small" disabled={ i === children.length - 1 } onClick={ () => reorderChild( field.id, i, i + 1 ) } aria-label={ __( 'Move down', 'alovio-calculator' ) }>↓</Button>
					<Button size="small" isDestructive onClick={ () => { removeChildField( field.id, c.id ); if ( childId === c.id ) { setChildId( null ); } } } aria-label={ __( 'Remove row field', 'alovio-calculator' ) }>✕</Button>
				</div>
			) ) }
			<div className="alc-repeater-panel__add">
				<SelectControl
					label={ __( 'Add row field', 'alovio-calculator' ) }
					hideLabelFromVision
					value={ newType }
					options={ REPEATER_CHILD_TYPES.map( ( t ) => ( { value: t, label: TYPE_LABELS[ t ] } ) ) }
					onChange={ setNewType }
				/>
				<Button variant="secondary" size="small" onClick={ () => addChildField( field.id, newType ) }>
					{ __( '+ Add', 'alovio-calculator' ) }
				</Button>
			</div>

			{ child && (
				<div className="alc-repeater-panel__editor">
					<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ child.label || '' } onChange={ ( label ) => setChild( { label } ) } />
					{ HAS_RANGE.includes( child.type ) && (
						<div className="alc-row4">
							<TextControl type="number" label={ __( 'Min', 'alovio-calculator' ) } value={ child.min ?? '' } onChange={ ( v ) => setChild( { min: num( v ) } ) } />
							<TextControl type="number" label={ __( 'Max', 'alovio-calculator' ) } value={ child.max ?? '' } onChange={ ( v ) => setChild( { max: num( v ) } ) } />
							<TextControl type="number" label={ __( 'Step', 'alovio-calculator' ) } value={ child.step ?? '' } onChange={ ( v ) => setChild( { step: num( v ) } ) } />
							<TextControl type="number" label={ __( 'Default', 'alovio-calculator' ) } value={ child.default ?? '' } onChange={ ( v ) => setChild( { default: num( v ) } ) } />
						</div>
					) }
					{ child.type === 'toggle' && (
						<>
							<TextControl type="number" step="0.01" label={ __( 'Price when on', 'alovio-calculator' ) } value={ child.price === 0 || child.price ? String( child.price ) : '' } onChange={ ( price ) => setChild( { price } ) } />
							<ToggleControl label={ __( 'On by default', 'alovio-calculator' ) } checked={ !! child.default } onChange={ ( on ) => setChild( { default: on } ) } />
						</>
					) }
					{ HAS_OPTIONS.includes( child.type ) && <OptionsEditor field={ child } set={ setChild } /> }
				</div>
			) }

			<span className="alc-options__title">{ __( 'Rows', 'alovio-calculator' ) }</span>
			<div className="alc-row4">
				<TextControl type="number" label={ __( 'Min rows', 'alovio-calculator' ) } value={ field.minRows ?? 1 } onChange={ ( v ) => set( { minRows: num( v ) } ) } />
				<TextControl type="number" label={ __( 'Max rows', 'alovio-calculator' ) } value={ field.maxRows ?? 10 } onChange={ ( v ) => set( { maxRows: num( v ) } ) } />
			</div>
			<TextControl label={ __( 'Row label', 'alovio-calculator' ) } help={ __( 'Use {n} for the row number, e.g. "Room {n}".', 'alovio-calculator' ) } value={ field.rowLabel || '' } onChange={ ( rowLabel ) => set( { rowLabel } ) } />
			<TextControl label={ __( 'Add button label', 'alovio-calculator' ) } value={ field.addLabel || '' } onChange={ ( addLabel ) => set( { addLabel } ) } />
			<TextControl
				label={ __( 'Row expression', 'alovio-calculator' ) }
				help={ __( 'Optional. May reference this repeater’s row fields only, e.g. {area} * {rate}. Leave empty to sum option/toggle prices.', 'alovio-calculator' ) }
				value={ field.rowExpression || '' }
				onChange={ ( rowExpression ) => set( { rowExpression } ) }
			/>
		</div>
	);
}
```

- [ ] **Step 7: Mount in the Options-tab slot.** In Chunk 4's `src/builder/panels/OptionsTab.jsx` (or `SettingsPanel.jsx` if the slot lives there — grep for where choice types mount `OptionsEditor`), render `<RepeaterFields field={ field } />` when `field.type === 'repeater'`, and make the third tab visible for `repeater` (spec §2.4 already lists it).
- [ ] **Step 8: Verify + commit.** `npm test` green; `npm run build` compiles.

```bash
git add src/builder/reducer.js src/builder/__tests__/reducer.test.js src/builder/panels/RepeaterFields.jsx src/builder/panels/OptionsTab.jsx
git commit -m "Add repeater row-fields editor with child-aware reducer actions"
```

### Task 6.6: Repeater styles (base + 6 themes) + chunk gate

**Files:**
- Modify: `src/frontend/frontend-style.scss` (base block after `.alc-toggle` ~line 125; one small override per theme section)

- [ ] **Step 1: Base block.** Insert after the `.alc-toggle` block:

```scss
/* ---- REPEATER (base; themes override tokens/surfaces below) ---- */
.alc-repeater {
	border: 1px solid var(--alc-surface-border);
	border-radius: var(--alc-radius);
	padding: 12px;

	> legend { font-weight: 600; padding: 0 4px; }

	&__row {
		border-block-end: 1px solid var(--alc-divider);
		padding-block: 10px;

		&:last-child { border-block-end: 0; }
	}

	&__row-head { display: flex; align-items: center; justify-content: space-between; margin-block-end: 6px; }
	&__row-label { font-weight: 600; font-size: 0.9em; color: var(--alc-muted); }

	&__remove {
		border: 0;
		background: none;
		color: var(--alc-muted);
		font-size: 18px;
		line-height: 1;
		padding: 2px 6px;
		cursor: pointer;

		&:hover { color: #b91c1c; }
		&[hidden] { display: none !important; }
	}

	&__row-fields { display: grid; gap: 10px; }
	&__child > label { display: block; font-weight: 600; }

	&__add {
		margin-block-start: 10px;
		padding: 8px 14px;
		border: 1px dashed var(--alc-border);
		border-radius: var(--alc-radius);
		background: none;
		color: var(--alc-accent);
		font-weight: 600;
		cursor: pointer;

		&:disabled { opacity: 0.5; cursor: not-allowed; }
	}
}
```

- [ ] **Step 2: Theme overrides.** At the END of each of the six theme sections (`.alc-theme--classic` … `--slate`, sections start ~line 379), append one compact block reusing that theme's local tokens/surfaces (keep each ≤6 declarations), e.g.:

```scss
.alc-calculator.alc-theme--classic .alc-repeater { background: #fff; box-shadow: none; }
.alc-calculator.alc-theme--classic .alc-repeater__add { border-color: var(--alc-accent); }

.alc-calculator.alc-theme--minimal .alc-repeater { border: 0; border-block-start: 2px solid var(--alc-border); border-radius: 0; padding-inline: 0; }

.alc-calculator.alc-theme--midnight .alc-repeater { background: rgba(255, 255, 255, 0.03); border-color: var(--alc-surface-border); }

.alc-calculator.alc-theme--soft .alc-repeater { background: var(--alc-surface); border: 0; }

.alc-calculator.alc-theme--bold .alc-repeater { border-width: 2px; border-color: var(--alc-border); }

.alc-calculator.alc-theme--slate .alc-repeater { background: var(--alc-surface); border-color: var(--alc-surface-border); }
```

  Match each block's surface colors against the theme's existing `.alc-choices`/`.alc-summary` treatment (read the section before writing — the literals above are the classic/midnight ones already used in those sections).
- [ ] **Step 3: Chunk gate.** `npm run build` (SCSS compiles), `npm test`, `vendor/bin/phpunit`, `vendor/bin/phpcs` — ALL green. Sanity-check in wp-env: create a calculator with a repeater (via the REST save or a template tweak), load the frontend, add/remove rows, submit a quote, open the entry, export CSV.
- [ ] **Step 4: Commit.**

```bash
git add src/frontend/frontend-style.scss
git commit -m "Style repeater rows across base and six themes"
```

---

## Chunk 7: New field types, slider polish, quote-form file upload

**Spec:** §3.2 (date/email/phone/url/textarea), §3.4 (slider), §3.3 (file upload). File-upload hardening is ported from the donor `/Users/tahir/woo-checkout-fields/includes/Checkout/FileUploads.php` with these deliberate deltas: finfo MIME check (spec wording) instead of `wp_check_filetype_and_ext`, NO nonce (our quote endpoints are cache-safe by design, spec §10 — honeypot + rate limit instead), 24 h orphan GC (donor: 48 h), files fully blocked from direct access (`Require all denied`) because downloads go through a capability-gated REST route.

### Task 7.1: Register the five text-like types (schema + flags)

**Files:**
- Modify: `tests/Unit/Fields/FieldTypesTest.php`, `tests/Unit/Fields/FieldSchemaTest.php` (append)
- Modify: `includes/Fields/FieldTypes.php` (FREE line 11, INPUT line 16)
- Modify: `includes/Fields/FieldSchema.php` (`case 'text': case 'heading':` line ~116)

- [ ] **Step 1: Failing tests.** Append to `FieldTypesTest`:

```php
	public function test_text_like_types_are_inputs_and_controllers_not_referenceable(): void {
		foreach ( [ 'date', 'email', 'phone', 'url', 'textarea' ] as $type ) {
			$this->assertContains( $type, FieldTypes::all(), $type );
			$this->assertTrue( FieldTypes::is_input( $type ), $type );
			$this->assertTrue( FieldTypes::is_condition_controller( $type ), $type );
			$this->assertFalse( FieldTypes::is_referenceable( $type ), $type );
			$this->assertFalse( FieldTypes::is_repeater_child( $type ), $type );
		}
	}
```

  Append to `FieldSchemaTest`:

```php
	public function test_new_text_like_types_normalize_with_placeholder(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'visit', 'type' => 'date', 'label' => 'Visit date', 'placeholder' => ' pick one ' ],
			[ 'id' => 'notes', 'type' => 'textarea', 'label' => 'Notes', 'placeholder' => '<b>x</b>' ],
			[ 'id' => 'site', 'type' => 'url', 'label' => 'Site' ],
		] ) );
		$this->assertSame( 'pick one', $out['fields'][0]['placeholder'] );
		$this->assertSame( 'x', $out['fields'][1]['placeholder'] );
		$this->assertSame( '', $out['fields'][2]['placeholder'] );
	}
```

- [ ] **Step 2: FAIL.** `vendor/bin/phpunit --filter 'FieldTypesTest|FieldSchemaTest'` → unknown types stripped.
- [ ] **Step 3: Implement.** `FieldTypes.php` line 11: append `'date', 'email', 'phone', 'url', 'textarea'` to `FREE`; line 16: append the same five to `INPUT` (controllers come free via `is_condition_controller`). `FieldSchema.php` line ~116: extend the placeholder case to `case 'text': case 'heading': case 'date': case 'email': case 'phone': case 'url': case 'textarea':`.
- [ ] **Step 4: PASS + commit.** Both filters green, full suite green.

```bash
git add includes/Fields/FieldTypes.php includes/Fields/FieldSchema.php tests/Unit/Fields/FieldTypesTest.php tests/Unit/Fields/FieldSchemaTest.php
git commit -m "Register date, email, phone, url and textarea field types"
```

### Task 7.2: Engine + renderer + email for text-like values

Behavior note (flag for review): `showInSummary` becomes EFFECTIVE for the existing `text` type too — previously it was silently ignored for text fields (they never entered the value map). Purely additive; default remains off.

**Files:**
- Modify: `tests/Unit/Logic/EvaluationTest.php`, `tests/Unit/Frontend/CalculatorRendererTest.php` (append)
- Modify: `includes/Logic/Evaluation.php` (new `TEXT_LIKE` const; `condition_values()` line ~169 `case 'text'`; summary loop from Task 5.3)
- Modify: `includes/Frontend/CalculatorRenderer.php` (`render_input()` new cases; `render_summary()` line ~188)
- Modify: `includes/Entries/EntryMailer.php` (non-repeater line from Task 6.4)

- [ ] **Step 1: Failing tests.** Append to `EvaluationTest`:

```php
	public function test_text_like_fields_feed_conditions_and_summary_display(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'visit', 'type' => 'date', 'label' => 'Visit date', 'showInSummary' => true ],
			[ 'id' => 'mail', 'type' => 'email', 'label' => 'Email' ],
			[ 'id' => 'note', 'type' => 'heading', 'label' => 'Thanks!', 'conditions' => [
				[ 'field' => 'mail', 'operator' => 'is_not_empty', 'value' => '' ],
			] ],
		] ] );
		$r = Evaluation::run( $config, [ 'visit' => ' 2026-08-01 ', 'mail' => 'a@b.co' ] );
		$this->assertSame( '2026-08-01', $r['conditionValues']['visit'] ); // trimmed, text semantics
		$this->assertTrue( $r['active']['note'] );
		$this->assertSame(
			[ [ 'id' => 'visit', 'label' => 'Visit date', 'amount' => 0, 'isCurrency' => false, 'display' => '2026-08-01' ] ],
			$r['lineItems']
		);
		$empty = Evaluation::run( $config, [] );
		$this->assertSame( [], $empty['lineItems'] ); // empty text-like values emit no line
	}
```

  Append to `CalculatorRendererTest`:

```php
	public function test_new_types_render_native_controls_and_display_summary(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'visit', 'type' => 'date', 'label' => 'Visit date' ],
			[ 'id' => 'mail', 'type' => 'email', 'label' => 'Email', 'placeholder' => 'you@example.com' ],
			[ 'id' => 'cell', 'type' => 'phone', 'label' => 'Phone' ],
			[ 'id' => 'site', 'type' => 'url', 'label' => 'Site' ],
			[ 'id' => 'notes', 'type' => 'textarea', 'label' => 'Notes', 'placeholder' => 'Tell us more' ],
		] ] );
		$html = CalculatorRenderer::render( 7, $config );
		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'type="tel"', $html );
		$this->assertStringContainsString( 'type="url"', $html );
		$this->assertStringContainsString( '<textarea id="alc-notes" rows="3" placeholder="Tell us more">', $html );
		$this->assertStringContainsString( 'placeholder="you@example.com"', $html );
	}
```

- [ ] **Step 2: FAIL.** `vendor/bin/phpunit --filter 'EvaluationTest|CalculatorRendererTest'`.
- [ ] **Step 3: Implement `Evaluation`.**
  - Const next to `MAX_PASSES`: `private const TEXT_LIKE = [ 'text', 'date', 'email', 'phone', 'url', 'textarea' ];` and helper `private static function is_text_like( string $type ): bool { return in_array( $type, self::TEXT_LIKE, true ); }`
  - `condition_values()`: change `case 'text':` to fall through for all five new types (`case 'text': case 'date': case 'email': case 'phone': case 'url': case 'textarea':` — same trim body).
  - Summary loop (restructured in Task 5.3): between the repeater branch and the `! isset( $values[ $id ] )` guard, insert:

```php
			if ( self::is_text_like( $field['type'] ) ) {
				$text = (string) ( $conditionValues[ $id ] ?? '' );
				if ( '' !== $text ) {
					$lineItems[] = [
						'id'         => $id,
						'label'      => $field['label'],
						'amount'     => 0,
						'isCurrency' => false,
						'display'    => $text,
					];
				}
				continue;
			}
```

- [ ] **Step 4: Implement renderer.** `render_input()` new cases (beside `case 'text':`):

```php
			case 'date':
			case 'email':
			case 'phone':
			case 'url':
				$types = array(
					'date'  => 'date',
					'email' => 'email',
					'phone' => 'tel',
					'url'   => 'url',
				);
				return sprintf(
					'<label for="alc-%1$s">%2$s</label><input type="%3$s" id="alc-%1$s" placeholder="%4$s">',
					$id,
					$label,
					$types[ $field['type'] ],
					esc_attr( $field['placeholder'] ?? '' )
				);

			case 'textarea':
				return sprintf(
					'<label for="alc-%1$s">%2$s</label><textarea id="alc-%1$s" rows="3" placeholder="%3$s"></textarea>',
					$id,
					$label,
					esc_attr( $field['placeholder'] ?? '' )
				);
```

  `render_summary()` (line ~188): the value expression becomes:

```php
			$value = isset( $item['display'] )
				? (string) $item['display']
				: ( $item['isCurrency'] ? CurrencyFormatter::format( $item['amount'], $currency ) : DecimalMath::fromScaled( $item['amount'] ) );
```

  `EntryMailer` non-repeater line (from Task 6.4) becomes: `$lines[] = $item['label'] . ': ' . ( isset( $item['display'] ) ? (string) $item['display'] : DecimalMath::fromScaled( $item['amount'] ) );`
- [ ] **Step 5: PASS + commit.** Filters green, full suite green, `vendor/bin/phpcs includes/` → 0.

```bash
git add includes/Logic/Evaluation.php includes/Frontend/CalculatorRenderer.php includes/Entries/EntryMailer.php tests/Unit/Logic/EvaluationTest.php tests/Unit/Frontend/CalculatorRendererTest.php
git commit -m "Render and surface text-like informational fields"
```

### Task 7.3: JS mirror + builder registration for the new types

**Files:**
- Modify: `src/frontend/__tests__/compute.test.js` (append), `src/frontend/compute.js` (CONTROLLERS line 13, `conditionValues()` case, summary loop), `src/frontend/summary.js` (value expr line 19), `src/frontend/calculator.js` (`readValue` textarea), `src/builder/reducer.js` (DEFAULTS), `src/builder/EntriesList.jsx` (modal amounts), Chunk-4 seam: `src/builder/panels/FieldGeneral.jsx` + `PaletteV2.jsx`

- [ ] **Step 1: Failing Jest test.** Append to `src/frontend/__tests__/compute.test.js`:

```js
	it( 'text-like fields feed conditions and emit display line items (PHP parity)', () => {
		const fields = [
			norm( { id: 'visit', type: 'date', label: 'Visit date', showInSummary: true, placeholder: '' } ),
			norm( { id: 'mail', type: 'email', label: 'Email', placeholder: '' } ),
			norm( { id: 'note', type: 'heading', label: 'Thanks!', conditions: [
				{ field: 'mail', operator: 'is_not_empty', value: '' },
			] } ),
		];
		const r = run( fields, prepare( fields ), { visit: ' 2026-08-01 ', mail: 'a@b.co' } );
		expect( r.active.note ).toBe( true );
		expect( r.lineItems ).toEqual( [
			{ id: 'visit', label: 'Visit date', amount: 0, isCurrency: false, display: '2026-08-01' },
		] );
		expect( run( fields, prepare( fields ), {} ).lineItems ).toEqual( [] );
	} );
```

- [ ] **Step 2: FAIL.** `npm test -- -t 'text-like fields feed conditions'`.
- [ ] **Step 3: Implement `compute.js`.**
  - Line 13/14 area: `const TEXT_LIKE = [ 'text', 'date', 'email', 'phone', 'url', 'textarea' ];` and rebuild `CONTROLLERS` as `[ ...REFERENCEABLE_INPUTS, ...TEXT_LIKE ]` (identical membership: number/slider/select/radio/checkbox_group/toggle/quantity + text-like).
  - `conditionValues()`: replace `case 'text':` with a `TEXT_LIKE.includes( f.type )` default branch (same trim body).
  - Summary loop (from Task 5.5): between the repeater branch and the `f.id in values` guard:

```js
		if ( TEXT_LIKE.includes( f.type ) ) {
			const text = condValues[ f.id ] || '';
			if ( text !== '' ) {
				lineItems.push( { id: f.id, label: f.label, amount: 0, isCurrency: false, display: text } );
			}
			return;
		}
```

  - `summary.js` line 19: `row.querySelector( '.alc-line-value' ).textContent = item.display !== undefined ? item.display : ( item.isCurrency ? formatCurrency( item.amount, currency ) : fromScaled( item.amount ) );`
  - `calculator.js` `readValue()`: add `case 'date': case 'email': case 'phone': case 'url':` to the input-reading group and `case 'textarea': { const ta = scope.querySelector( 'textarea' ); return ta ? ta.value : ''; }`.
  - `EntriesList.jsx` modal line-items cell: `{ item.display !== undefined ? item.display : String( item.amount / 10000 ) }`.
- [ ] **Step 4: Builder registration.** `reducer.js` DEFAULTS additions:

```js
	date: { label: 'Date', placeholder: '', showInSummary: false },
	email: { label: 'Email', placeholder: '', showInSummary: false },
	phone: { label: 'Phone', placeholder: '', showInSummary: false },
	url: { label: 'Website', placeholder: '', showInSummary: false },
	textarea: { label: 'Notes', placeholder: '', showInSummary: false },
```

  Chunk-4 seam (two one-line edits, grep first): add the five types to the **Inputs** group in `src/builder/PaletteV2.jsx` (icons from `icons.js`; add five simple inline SVGs there) and extend `panels/FieldGeneral.jsx`'s placeholder-control condition from `type === 'text'` to the text-like list.
- [ ] **Step 5: PASS + commit.** `npm test` green, `npm run build` compiles.

```bash
git add src/frontend/compute.js src/frontend/summary.js src/frontend/calculator.js src/frontend/__tests__/compute.test.js src/builder/reducer.js src/builder/EntriesList.jsx src/builder/PaletteV2.jsx src/builder/panels/FieldGeneral.jsx src/builder/icons.js
git commit -m "Mirror text-like fields in compute, summary and builder"
```

### Task 7.4: Slider polish — bubble, min/max labels, unit suffix

**Files:**
- Modify: `tests/Unit/Fields/FieldSchemaTest.php`, `tests/Unit/Frontend/CalculatorRendererTest.php` (append)
- Modify: `includes/Fields/FieldSchema.php` (numeric case line ~91), `includes/Frontend/CalculatorRenderer.php` (`case 'slider':` lines 100–108), `src/frontend/calculator.js` (range handler lines 147–158), `src/frontend/frontend-style.scss` (`.alc-slider` lines 70–85 + theme slider blocks)
- Builder seam: slider `unit` text control in `panels/FieldGeneral.jsx` (one control, next to min/max); `reducer.js` slider DEFAULTS gain `unit: ''`.

- [ ] **Step 1: Failing tests.** `FieldSchemaTest`:

```php
	public function test_slider_unit_setting(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'area', 'type' => 'slider', 'label' => 'Area', 'unit' => ' m² <b>x</b> ' ],
			[ 'id' => 'qty', 'type' => 'number', 'label' => 'Qty', 'unit' => 'kg' ],
		] ) );
		$this->assertSame( 'm² x', $out['fields'][0]['unit'] ); // sanitized
		$this->assertArrayNotHasKey( 'unit', $out['fields'][1] ); // slider-only setting
	}
```

  `CalculatorRendererTest`:

```php
	public function test_slider_bubble_scale_and_unit(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'area', 'type' => 'slider', 'label' => 'Area', 'min' => 10, 'max' => 110, 'default' => 35, 'unit' => 'm2' ],
		] ] );
		$html = CalculatorRenderer::render( 7, $config );
		$this->assertStringContainsString( 'class="alc-slider" data-alc-unit="m2"', $html );
		$this->assertStringContainsString( '--alc-pos:25%', $html );                       // (35-10)/(110-10)
		$this->assertStringContainsString( 'class="alc-slider__bubble">35 m2<', $html );   // unit suffix
		$this->assertStringContainsString( '<div class="alc-slider__scale" aria-hidden="true"><span>10</span><span>110</span></div>', $html );
	}
```

- [ ] **Step 2: FAIL.** `vendor/bin/phpunit --filter 'FieldSchemaTest|CalculatorRendererTest'`.
- [ ] **Step 3: Implement schema + renderer.** `FieldSchema::normalize_field`, after the min/max/step/default loop in the numeric case, add:

```php
				if ( 'slider' === $type ) {
					$field['unit'] = sanitize_text_field( (string) ( $raw['unit'] ?? '' ) );
				}
```

  Replace the renderer `case 'slider':` (lines 100–108) with:

```php
			case 'slider':
				$value = self::default_number( $field );
				$unit  = (string) ( $field['unit'] ?? '' );
				$min   = isset( $field['min'] ) && null !== $field['min'] ? (float) $field['min'] : 0.0;
				$max   = isset( $field['max'] ) && null !== $field['max'] ? (float) $field['max'] : 100.0;
				$pos   = $max > $min ? ( ( (float) $value - $min ) / ( $max - $min ) ) * 100 : 0.0;
				return sprintf(
					'<label for="alc-%1$s">%2$s</label>'
					. '<div class="alc-slider" data-alc-unit="%5$s" style="--alc-pos:%6$s%%">'
					. '<div class="alc-slider__rail"><input type="range" id="alc-%1$s"%3$s value="%4$s">'
					. '<output for="alc-%1$s" class="alc-slider__bubble">%7$s</output></div>'
					. '<div class="alc-slider__scale" aria-hidden="true"><span>%8$s</span><span>%9$s</span></div>'
					. '</div>',
					$id,
					$label,
					self::range_attrs( $field ),
					esc_attr( $value ),
					esc_attr( $unit ),
					esc_attr( self::trim_float( round( $pos, 2 ) ) ),
					esc_html( $value . ( '' !== $unit ? ' ' . $unit : '' ) ),
					esc_html( self::trim_float( $min ) ),
					esc_html( self::trim_float( $max ) )
				);
```

- [ ] **Step 4: JS.** In `calculator.js`, replace the inline range-output block inside the `input` listener with `updateSliderUi( e.target );` and add (exported for tests):

```js
/** Bubble text (+unit) and thumb-tracking position. Works for top-level and repeater-row sliders. */
export function updateSliderUi( input ) {
	const holder = input.closest( '.alc-slider' );
	const out = holder && holder.querySelector( 'output' );
	if ( ! out ) {
		return;
	}
	const unit = holder.getAttribute( 'data-alc-unit' ) || '';
	out.textContent = input.value + ( unit ? ' ' + unit : '' );
	const min = parseFloat( input.min || '0' );
	const max = parseFloat( input.max || '100' );
	const pct = max > min ? ( ( parseFloat( input.value ) - min ) / ( max - min ) ) * 100 : 0;
	holder.style.setProperty( '--alc-pos', `${ pct }%` );
}
```

  Add a jsdom case to `repeater-dom.test.js` (same file, new `describe`): mount `<div class="alc-slider" data-alc-unit="m2"><div class="alc-slider__rail"><input type="range" min="10" max="110" value="60"><output></output></div></div>`, call `updateSliderUi(input)`, expect output text `60 m2` and `--alc-pos` = `50%`.
- [ ] **Step 5: SCSS.** Replace the base `.alc-slider` block (lines 70–85) with:

```scss
.alc-slider {
	position: relative;
	display: block;

	&__rail {
		position: relative;
		display: flex;
		align-items: center;
		padding-block-start: 28px;
	}

	input[type='range'] {
		flex: 1;
		accent-color: var(--alc-accent);
	}

	&__bubble {
		position: absolute;
		inset-block-start: 0;
		inset-inline-start: clamp(0%, var(--alc-pos, 50%), 100%);
		transform: translateX(-50%); /* physical on purpose: tracks the % offset */
		padding: 2px 8px;
		border-radius: 999px;
		background: var(--alc-accent);
		color: var(--alc-button-text);
		font-size: 0.8em;
		font-weight: 600;
		white-space: nowrap;
	}

	&__scale {
		display: flex;
		justify-content: space-between;
		margin-block-start: 2px;
		font-size: 0.8em;
		color: var(--alc-muted);
	}
}
```

  Then, in each of the six theme sections, find the existing `.alc-slider output` rules (classic's is at ~lines 502–515) and re-target them to `.alc-slider__bubble`, keeping each theme's colors; add nothing new beyond a bubble background/text pair per theme. Repeater-row sliders (`span.alc-slider`) keep the inline output — the base block only elevates `.alc-slider__bubble`, which rows don't render.
- [ ] **Step 6: Builder.** `reducer.js` slider DEFAULTS gain `unit: ''`; add a `unit` TextControl for sliders in `panels/FieldGeneral.jsx` (seam edit, next to the min/max row).
- [ ] **Step 7: PASS + commit.** PHP filters green, `npm test` green, `npm run build` compiles.

```bash
git add includes/Fields/FieldSchema.php includes/Frontend/CalculatorRenderer.php src/frontend/calculator.js src/frontend/frontend-style.scss src/frontend/__tests__/repeater-dom.test.js src/builder/reducer.js src/builder/panels/FieldGeneral.jsx tests/Unit/Fields/FieldSchemaTest.php tests/Unit/Frontend/CalculatorRendererTest.php
git commit -m "Polish slider with value bubble, scale labels and unit suffix"
```

### Task 7.5: File upload backend — settings schema + FileUploads.php + lifecycle

**Files:**
- Create: `includes/Entries/FileUploads.php`
- Create: `tests/Unit/Entries/FileUploadsTest.php`
- Modify: `includes/Fields/FieldSchema.php` (defaults lines 28–33, `normalize_settings()` quoteForm block lines 189–222), `includes/Plugin.php` (boot, line 40 area), `includes/Entries/EntriesRestController.php` (`delete_entry()` lines 106–113), `includes/Entries/Privacy.php` (`erase()` lines 74–82), `uninstall.php` (site cleanup lines 11–28)
- Modify: `tests/Unit/Fields/FieldSchemaTest.php` (append)

- [ ] **Step 1: Failing tests.** Append to `FieldSchemaTest`:

```php
	public function test_quote_form_file_settings(): void {
		$out = FieldSchema::normalize( [ 'fields' => [], 'settings' => [ 'quoteForm' => [
			'enabled' => 1,
			'file'    => [ 'enabled' => 1, 'label' => ' <b>Plan</b> ', 'types' => [ 'jpg', 'exe', 'pdf' ], 'maxMb' => 99 ],
		] ] ] );
		$file = $out['settings']['quoteForm']['file'];
		$this->assertTrue( $file['enabled'] );
		$this->assertSame( 'Plan', $file['label'] );
		$this->assertSame( [ 'jpg', 'pdf' ], $file['types'] );  // allowlist intersect
		$this->assertSame( 20, $file['maxMb'] );                 // clamped 1..20

		$d = FieldSchema::normalize( [] )['settings']['quoteForm']['file'];
		$this->assertFalse( $d['enabled'] );                     // off by default (spec §3.3)
		$this->assertSame( [ 'jpg', 'png', 'webp', 'pdf' ], $d['types'] );
		$this->assertSame( 5, $d['maxMb'] );
	}
```

  Create `tests/Unit/Entries/FileUploadsTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\FileUploads;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class FileUploadsTest extends TestCase {

	private function settings( array $over = [] ): array {
		return $over + [ 'enabled' => true, 'label' => '', 'types' => [ 'jpg', 'png', 'webp', 'pdf' ], 'maxMb' => 5 ];
	}

	public function test_validate_upload_enforces_size_and_type(): void {
		$ok = FileUploads::validate_upload( 'plan.PDF', 1024, 'application/pdf', $this->settings() );
		$this->assertTrue( $ok['ok'] );
		$this->assertSame( 'pdf', $ok['ext'] );

		$jpeg = FileUploads::validate_upload( 'photo.jpeg', 1024, 'image/jpeg', $this->settings() );
		$this->assertTrue( $jpeg['ok'] ); // jpeg alias maps to jpg

		$big = FileUploads::validate_upload( 'plan.pdf', 6 * 1048576, 'application/pdf', $this->settings() );
		$this->assertSame( 'too_large', $big['code'] );

		$lying = FileUploads::validate_upload( 'evil.pdf', 1024, 'application/x-httpd-php', $this->settings() );
		$this->assertSame( 'bad_type', $lying['code'] ); // finfo MIME must match the extension

		$narrow = FileUploads::validate_upload( 'pic.png', 1024, 'image/png', $this->settings( [ 'types' => [ 'pdf' ] ] ) );
		$this->assertSame( 'bad_type', $narrow['code'] ); // site narrowed the allowlist
	}

	public function test_consume_is_one_time_and_format_checked(): void {
		Functions\when( 'get_option' )->alias( static function ( $name ) {
			return 'alovio_calc_upload_' . str_repeat( 'a', 32 ) === $name
				? [ 'stored' => 'alc-x.pdf', 'name' => 'plan.pdf', 'time' => 1 ]
				: false;
		} );
		Functions\expect( 'delete_option' )->once()->with( 'alovio_calc_upload_' . str_repeat( 'a', 32 ) );

		$this->assertNull( FileUploads::consume( 'not-a-token' ) );
		$this->assertNull( FileUploads::consume( str_repeat( 'b', 32 ) ) );
		$this->assertSame(
			[ 'name' => 'plan.pdf', 'stored' => 'alc-x.pdf' ],
			FileUploads::consume( str_repeat( 'a', 32 ) )
		);
	}
}
```

- [ ] **Step 2: FAIL.** `vendor/bin/phpunit --filter 'FieldSchemaTest|FileUploadsTest'` → class missing / settings absent.
- [ ] **Step 3: Schema.** In `FieldSchema::defaults()` quoteForm (line 28–33), add:

```php
					'file'           => [
						'enabled' => false,
						'label'   => '',
						'types'   => [ 'jpg', 'png', 'webp', 'pdf' ],
						'maxMb'   => 5,
					],
```

  In `normalize_settings()`, before the return, compute and add to the returned `quoteForm` array:

```php
		$file  = (array) ( $quote['file'] ?? [] );
		$types = array_values( array_intersect( [ 'jpg', 'png', 'webp', 'pdf' ], array_map( 'strval', (array) ( $file['types'] ?? [] ) ) ) );
		$maxMb = isset( $file['maxMb'] ) && is_numeric( $file['maxMb'] ) ? (int) $file['maxMb'] : 5;
```

```php
				'file'           => [
					'enabled' => ! empty( $file['enabled'] ),
					'label'   => sanitize_text_field( (string) ( $file['label'] ?? '' ) ),
					'types'   => $types ? $types : $d['quoteForm']['file']['types'],
					'maxMb'   => max( 1, min( 20, $maxMb ) ),
				],
```

- [ ] **Step 4: Create `includes/Entries/FileUploads.php`** (complete file):

```php
<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Quote-form file upload (spec §3.3). Async upload → one-time 32-hex token →
 * token rides the quote and is written into the entry snapshot. Files live under
 * uploads/alovio-calc/ with random names; direct web access is denied (.htaccess;
 * on nginx the random names are the fallback) — admins stream downloads through
 * the capability-gated route below. Orphans are GC'd daily after 24 h.
 */
final class FileUploads {

	public const CRON_HOOK     = 'alovio_calc_file_gc';
	public const OPTION_PREFIX = 'alovio_calc_upload_';
	public const SUBDIR        = 'alovio-calc';

	private const RATE_LIMIT = 10; // uploads per hour per IP.
	private const MIMES      = array(
		'jpg'  => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'pdf'  => 'application/pdf',
	);

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'schedule_gc' ) );
		add_action( self::CRON_HOOK, array( $this, 'gc_orphans' ) );
		register_deactivation_hook( ALOVIO_CALC_FILE, array( __CLASS__, 'unschedule' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/quote-file',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public like /quote (spec §10): honeypot + rate limit, no nonce (cache-safe).
				'callback'            => array( $this, 'handle_upload' ),
			)
		);
		register_rest_route(
			'alovio-calc/v1',
			'/entries/(?P<id>\d+)/file',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'download' ),
			)
		);
	}

	/**
	 * Pure decision core — unit-tested without WP. $mime comes from finfo (content
	 * sniffing), so a renamed executable fails even with an allowed extension.
	 *
	 * @return array{ok:bool, code:string, ext:string}
	 */
	public static function validate_upload( string $name, int $size, string $mime, array $fileSettings ): array {
		$maxMb = (int) ( $fileSettings['maxMb'] ?? 5 );
		if ( $size <= 0 || $size > $maxMb * 1048576 ) {
			return array(
				'ok'   => false,
				'code' => 'too_large',
				'ext'  => '',
			);
		}
		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'jpeg' === $ext ) {
			$ext = 'jpg';
		}
		$allowed = array_intersect( (array) ( $fileSettings['types'] ?? array() ), array_keys( self::MIMES ) );
		if ( ! in_array( $ext, $allowed, true ) || self::MIMES[ $ext ] !== $mime ) {
			return array(
				'ok'   => false,
				'code' => 'bad_type',
				'ext'  => $ext,
			);
		}
		return array(
			'ok'   => true,
			'code' => '',
			'ext'  => $ext,
		);
	}

	/** @param \WP_REST_Request $request */
	public function handle_upload( $request ) {
		if ( '' !== (string) $request->get_param( 'alc_website' ) ) { // Honeypot — pretend success to bots.
			return new \WP_REST_Response(
				array(
					'token' => bin2hex( random_bytes( 16 ) ), // Never stored; unusable.
					'name'  => '',
				),
				201
			);
		}
		if ( ! $this->within_rate_limit() ) {
			return new \WP_Error( 'alc_rate_limited', __( 'Too many uploads. Please try again later.', 'alovio-calculator' ), array( 'status' => 429 ) );
		}

		$calculator_id = absint( $request->get_param( 'calculatorId' ) );
		$config        = ( new FieldRepository() )->get( $calculator_id );
		$fileSettings  = $config['settings']['quoteForm']['file'];
		if ( empty( $config['settings']['quoteForm']['enabled'] ) || empty( $fileSettings['enabled'] ) ) {
			return new \WP_Error( 'alc_uploads_disabled', __( 'File uploads are not enabled.', 'alovio-calculator' ), array( 'status' => 400 ) );
		}

		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;
		if ( null === $file || empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'alc_no_file', __( 'No file received.', 'alovio-calculator' ), array( 'status' => 400 ) );
		}

		$original = sanitize_file_name( (string) ( $file['name'] ?? 'file' ) );
		$finfo    = finfo_open( FILEINFO_MIME_TYPE );
		$mime     = false !== $finfo ? (string) finfo_file( $finfo, (string) $file['tmp_name'] ) : '';
		if ( false !== $finfo ) {
			finfo_close( $finfo );
		}
		$check = self::validate_upload( $original, (int) ( $file['size'] ?? 0 ), $mime, $fileSettings );
		if ( ! $check['ok'] ) {
			return 'too_large' === $check['code']
				? new \WP_Error( 'alc_too_large', __( 'The file is too large.', 'alovio-calculator' ), array( 'status' => 413 ) )
				: new \WP_Error( 'alc_bad_type', __( 'This file type is not allowed.', 'alovio-calculator' ), array( 'status' => 415 ) );
		}

		$token = bin2hex( random_bytes( 16 ) );
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'unique_filename_callback' => static function ( $dir, $name, $extension ) use ( $token ) {
					return 'alc-' . $token . strtolower( (string) $extension );
				},
			)
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		if ( ! is_array( $moved ) || empty( $moved['file'] ) || ! empty( $moved['error'] ) ) {
			return new \WP_Error( 'alc_upload_failed', __( 'Upload failed. Please try again.', 'alovio-calculator' ), array( 'status' => 500 ) );
		}

		update_option(
			self::OPTION_PREFIX . $token,
			array(
				'stored' => basename( (string) $moved['file'] ),
				'name'   => $original,
				'time'   => time(),
			),
			false
		);

		return new \WP_REST_Response(
			array(
				'token' => $token,
				'name'  => $original,
			),
			201
		);
	}

	/**
	 * One-time consume: the quote submission claims the token; the GC then never
	 * touches the file (the entry owns it). Pure enough to unit-test with stubs.
	 *
	 * @return array{name:string, stored:string}|null
	 */
	public static function consume( string $token ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		$row = get_option( self::OPTION_PREFIX . $token );
		if ( ! is_array( $row ) || empty( $row['stored'] ) ) {
			return null;
		}
		delete_option( self::OPTION_PREFIX . $token );
		return array(
			'name'   => (string) ( $row['name'] ?? '' ),
			'stored' => (string) $row['stored'],
		);
	}

	/** @param \WP_REST_Request $request */
	public function download( $request ) {
		$row      = ( new EntriesRepository() )->find( (int) $request['id'] );
		$snapshot = null !== $row ? json_decode( (string) $row['snapshot'], true ) : null;
		$file     = is_array( $snapshot ) && ! empty( $snapshot['file']['stored'] ) ? (array) $snapshot['file'] : null;
		if ( null === $file ) {
			return new \WP_Error( 'alc_not_found', __( 'No file for this entry.', 'alovio-calculator' ), array( 'status' => 404 ) );
		}
		$path = self::dir_path() . '/' . basename( (string) $file['stored'] );
		if ( ! file_exists( $path ) ) {
			return new \WP_Error( 'alc_not_found', __( 'The file is missing on disk.', 'alovio-calculator' ), array( 'status' => 404 ) );
		}
		$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$mime = self::MIMES[ 'jpeg' === $ext ? 'jpg' : $ext ] ?? 'application/octet-stream';
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) ( $file['name'] ?? 'file' ) ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- streaming a verified local file to an authorized admin.
		exit;
	}

	/** Entry delete + privacy erase call this before removing the row (spec §3.3). */
	public static function delete_entry_file( array $row ): void {
		$snapshot = json_decode( (string) ( $row['snapshot'] ?? '' ), true );
		if ( ! is_array( $snapshot ) || empty( $snapshot['file']['stored'] ) ) {
			return;
		}
		$path = self::dir_path() . '/' . basename( (string) $snapshot['file']['stored'] );
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * @param array<string,mixed> $dirs
	 * @return array<string,mixed>
	 */
	public static function upload_dir( array $dirs ): array {
		$dirs['subdir'] = '/' . self::SUBDIR;
		$dirs['path']   = $dirs['basedir'] . '/' . self::SUBDIR;
		$dirs['url']    = $dirs['baseurl'] . '/' . self::SUBDIR;
		if ( ! is_dir( $dirs['path'] ) ) {
			wp_mkdir_p( $dirs['path'] );
			@file_put_contents( $dirs['path'] . '/.htaccess', "Require all denied\n" ); // phpcs:ignore
			@file_put_contents( $dirs['path'] . '/index.html', '' ); // phpcs:ignore
		}
		return $dirs;
	}

	private static function dir_path(): string {
		$uploads = wp_upload_dir();
		return (string) $uploads['basedir'] . '/' . self::SUBDIR;
	}

	public function schedule_gc(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Delete never-claimed uploads older than 24 h (their token option still exists ⇒ orphan). */
	public function gc_orphans(): void {
		global $wpdb;
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'alovio\\_calc\\_upload\\_%'" ); // phpcs:ignore WordPress.DB
		foreach ( (array) $names as $name ) {
			$row = get_option( $name );
			if ( is_array( $row ) && ! empty( $row['time'] ) && ( time() - (int) $row['time'] ) > DAY_IN_SECONDS ) {
				if ( ! empty( $row['stored'] ) ) {
					$path = self::dir_path() . '/' . basename( (string) $row['stored'] );
					if ( file_exists( $path ) ) {
						wp_delete_file( $path );
					}
				}
				delete_option( $name );
			}
		}
	}

	private function within_rate_limit(): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // REMOTE_ADDR only — spec §10 (XFF is spoofable).
		$key   = 'alovio_calc_uplrl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}
}
```

- [ ] **Step 5: Lifecycle wiring.**
  - `includes/Plugin.php` line 40 area (after `QuoteController`): `( new Entries\FileUploads() )->register();`
  - `EntriesRestController::delete_entry()` (lines 106–113): capture the row and delete its file first:

```php
		$row = $this->repo->find( $id );
		if ( null === $row ) {
			return $this->not_found();
		}
		FileUploads::delete_entry_file( $row );
		$this->repo->delete( $id );
```

  - `Privacy::erase()` (lines 74–82): before `delete_by_email`, loop `( new EntriesRepository() )->get_by_email( $email_address )` and call `FileUploads::delete_entry_file( $row )` for each.
  - `uninstall.php` (inside `alovio_calc_uninstall_site()`, after the transient sweep line 27): purge tokens, files and the cron:

```php
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'alovio\\_calc\\_upload\\_%'" ); // phpcs:ignore WordPress.DB
	wp_clear_scheduled_hook( 'alovio_calc_file_gc' );
	$alovio_calc_uploads = wp_upload_dir();
	$alovio_calc_dir     = $alovio_calc_uploads['basedir'] . '/alovio-calc';
	if ( is_dir( $alovio_calc_dir ) ) {
		foreach ( (array) glob( $alovio_calc_dir . '/{,.}*', GLOB_BRACE ) as $alovio_calc_file ) {
			if ( is_file( $alovio_calc_file ) ) {
				wp_delete_file( $alovio_calc_file );
			}
		}
		@rmdir( $alovio_calc_dir ); // phpcs:ignore
	}
```

- [ ] **Step 6: PASS + commit.** `vendor/bin/phpunit --filter 'FieldSchemaTest|FileUploadsTest'` green; full suite green; `vendor/bin/phpcs includes/Entries/FileUploads.php` → 0.

```bash
git add includes/Entries/FileUploads.php includes/Fields/FieldSchema.php includes/Plugin.php includes/Entries/EntriesRestController.php includes/Entries/Privacy.php uninstall.php tests/Unit/Entries/FileUploadsTest.php tests/Unit/Fields/FieldSchemaTest.php
git commit -m "Add hardened quote-file upload pipeline with token lifecycle"
```

### Task 7.6: File upload — quote form UI, token consumption, entries surfaces

**Files:**
- Modify: `includes/Frontend/CalculatorRenderer.php` (payload lines 36–42; `render_quote_form()` lines 207–241), `src/frontend/quote-form.js`, `src/frontend/calculator.js` (i18n block line ~102), `includes/Entries/QuoteController.php` (`handle()` before the snapshot), `includes/Entries/CsvExporter.php` (COLUMNS + `handle()`), `includes/Entries/EntryMailer.php` (after the Total line), `src/builder/EntriesList.jsx` (modal)
- Modify tests: `tests/Unit/Frontend/CalculatorRendererTest.php`, `tests/Unit/Entries/CsvExporterTest.php`, `tests/Unit/Entries/EntryMailerTest.php`
- Builder seam: file settings controls (enabled/label/types/maxMb) belong to Chunk 4's `panels/CalcQuote.jsx` — add them there (four controls bound to `settings.quoteForm.file`).

- [ ] **Step 1: Failing renderer test.** Append to `CalculatorRendererTest` (extend `config()` locally):

```php
	public function test_quote_form_file_block_when_enabled(): void {
		$config = $this->config();
		$config['settings']['quoteForm']['file'] = [ 'enabled' => true, 'label' => '', 'types' => [ 'jpg', 'pdf' ], 'maxMb' => 5 ];
		$html = CalculatorRenderer::render( 7, $config );
		$this->assertStringContainsString( 'class="alc-quote__file"', $html );
		$this->assertStringContainsString( 'accept=".jpg,.pdf"', $html );
		$this->assertStringContainsString( 'name="alc_file_token"', $html );
		$this->assertStringContainsString( 'alc-quote__file-status', $html );
		$payload = $this->payload( $html );
		$this->assertTrue( $payload['settings']['quoteForm']['file']['enabled'] );
		$this->assertStringContainsString( '/quote-file', $payload['settings']['quoteForm']['file']['endpoint'] );

		$off = CalculatorRenderer::render( 7, $this->config() );
		$this->assertStringNotContainsString( 'alc-quote__file', $off );
		$this->assertFalse( $this->payload( $off )['settings']['quoteForm']['file']['enabled'] );
	}
```

- [ ] **Step 2: FAIL, then implement renderer.**
  - Payload `quoteForm` block (lines 36–42) gains:

```php
					'file'           => empty( $quote['file']['enabled'] ) ? array( 'enabled' => false ) : array(
						'enabled'  => true,
						'label'    => '' !== $quote['file']['label'] ? $quote['file']['label'] : __( 'Attach a file', 'alovio-calculator' ),
						'types'    => $quote['file']['types'],
						'maxMb'    => $quote['file']['maxMb'],
						'endpoint' => esc_url( rest_url( 'alovio-calc/v1/quote-file' ) ),
					),
```

  - In `render_quote_form()`, after the contact-fields loop (line ~232), append:

```php
		if ( ! empty( $quote['file']['enabled'] ) ) {
			$file_label = '' !== $quote['file']['label'] ? $quote['file']['label'] : __( 'Attach a file', 'alovio-calculator' );
			$inputs    .= sprintf(
				'<label class="alc-quote__field alc-quote__file-field">%1$s<input type="file" class="alc-quote__file" accept="%2$s"></label>'
				. '<input type="hidden" name="alc_file_token" value="">'
				. '<span class="alc-quote__file-status" role="status"></span>',
				esc_html( $file_label ),
				esc_attr( '.' . implode( ',.', $quote['file']['types'] ) )
			);
		}
```

- [ ] **Step 3: quote-form.js.** In `src/frontend/quote-form.js`: add a shared `state = { uploading: false }` at the top of `wireQuoteForm`, call `wireFileUpload( form, config, state )` before the submit listener, guard submission with `if ( state.uploading ) { setFeedback( config.i18n.fileUploading, 'error' ); button.disabled = false; return; }`, and add `fileToken: form.querySelector( '[name="alc_file_token"]' )?.value || ''` to the JSON body. Append:

```js
/** Async file upload (spec §3.3): upload on selection, keep only the returned token. */
function wireFileUpload( form, config, state ) {
	const fileCfg = config.settings.quoteForm.file;
	const picker = form.querySelector( '.alc-quote__file' );
	if ( ! fileCfg || ! fileCfg.enabled || ! picker ) {
		return;
	}
	const hidden = form.querySelector( '[name="alc_file_token"]' );
	const status = form.querySelector( '.alc-quote__file-status' );
	const say = ( text ) => {
		if ( status ) {
			status.textContent = text;
		}
	};
	picker.addEventListener( 'change', async () => {
		const file = picker.files && picker.files[ 0 ];
		hidden.value = '';
		if ( ! file ) {
			say( '' );
			return;
		}
		if ( file.size > fileCfg.maxMb * 1048576 ) {
			picker.value = '';
			say( config.i18n.fileTooLarge.replace( '%d', String( fileCfg.maxMb ) ) );
			return;
		}
		say( config.i18n.fileUploading );
		state.uploading = true;
		try {
			const body = new FormData();
			body.append( 'file', file );
			body.append( 'calculatorId', String( config.calculatorId ) );
			body.append( 'alc_website', '' );
			const resp = await window.fetch( fileCfg.endpoint, { method: 'POST', body } );
			const data = await resp.json();
			if ( ! resp.ok || ! data.token ) {
				throw new Error( ( data && data.message ) || config.i18n.networkError );
			}
			hidden.value = data.token;
			say( '✓ ' + data.name );
		} catch ( err ) {
			picker.value = '';
			say( err.message || config.i18n.networkError );
		}
		state.uploading = false;
	} );
}
```

  In `calculator.js`'s `config.i18n` block (line ~102), add `fileUploading: 'Uploading…', fileTooLarge: 'File is too large (max %d MB).',` (frontend bundle ships no wp-i18n — same convention as the neighbours).
- [ ] **Step 4: Token consumption.** In `QuoteController::handle()`, after required-validation passes and BEFORE `$snapshot` is built, add (+ nothing to import — same namespace):

```php
		$fileMeta = null;
		if ( ! empty( $config['settings']['quoteForm']['file']['enabled'] ) ) {
			$fileToken = (string) $request->get_param( 'fileToken' );
			if ( '' !== $fileToken ) {
				$fileMeta = FileUploads::consume( $fileToken );
				if ( null === $fileMeta ) {
					return $this->bad_request( 'file_invalid', __( 'The uploaded file could not be verified — please upload it again.', 'alovio-calculator' ) );
				}
			}
		}
```

  and after the `$snapshot = array( ... );` literal:

```php
		if ( null !== $fileMeta ) {
			$snapshot['file'] = array(
				'name'   => sanitize_file_name( $fileMeta['name'] ),
				'stored' => $fileMeta['stored'],
			);
		}
```

- [ ] **Step 5: Entries surfaces.**
  - `CsvExporter`: COLUMNS gains `'file'` between `'repeaters'` and `'snapshot'`; in `handle()` set `$row['file'] = (string) ( $snap['file']['name'] ?? '' );` (reuse the decoded `$snap` from Task 6.4 — refactor so the snapshot is decoded once per row). Test: append to `CsvExporterTest` a `csv_row` case asserting the original FILENAME (never a URL/path) lands in the cell.
  - `EntryMailer::notify()`: after the Total line (line ~27), add:

```php
		if ( ! empty( $snapshot['file']['name'] ) ) {
			$lines[] = '';
			/* translators: %s: uploaded file name. */
			$lines[] = sprintf( __( 'Attached file: %s', 'alovio-calculator' ), $snapshot['file']['name'] );
			/* translators: %s: admin dashboard URL. */
			$lines[] = sprintf( __( 'Download it from the entry in your dashboard: %s', 'alovio-calculator' ), admin_url( 'admin.php?page=alovio-calculator' ) );
		}
```

  (spec §3.3: the file itself is NOT attached). Test in `EntryMailerTest` with `Functions\when( 'admin_url' )` stubbed: message contains the filename and the dashboard URL, `attachments` stays empty.
  - `EntriesList.jsx` modal, above the repeater block:

```jsx
					{ open.snapshot && open.snapshot.file && open.snapshot.file.name && (
						<p>
							<strong>{ __( 'File:', 'alovio-calculator' ) }</strong>{ ' ' }
							<a href={ `${ window.ALOVIO_CALC_BUILDER.root }alovio-calc/v1/entries/${ open.id }/file?_wpnonce=${ window.ALOVIO_CALC_BUILDER.nonce }` }>
								{ open.snapshot.file.name }
							</a>
						</p>
					) }
```

  - SCSS: tiny block near `.alc-quote` — `.alc-quote__file-status { font-size: 0.85em; color: var(--alc-muted); }`.
- [ ] **Step 6: PASS + commit.** All PHP filters green, `npm test` green, `npm run build` compiles. wp-env round-trip: enable the file block, upload a jpg + a fake `.pdf` (renamed .txt — expect 415), submit, download from the entry modal, delete the entry and confirm the file is gone.

```bash
git add includes/Frontend/CalculatorRenderer.php src/frontend/quote-form.js src/frontend/calculator.js includes/Entries/QuoteController.php includes/Entries/CsvExporter.php includes/Entries/EntryMailer.php src/builder/EntriesList.jsx src/builder/panels/CalcQuote.jsx src/frontend/frontend-style.scss tests/Unit/Frontend/CalculatorRendererTest.php tests/Unit/Entries/CsvExporterTest.php tests/Unit/Entries/EntryMailerTest.php
git commit -m "Wire quote-file upload through form, quote flow and entries surfaces"
```

### Task 7.7: Bundle budget + chunk gate

**Files:** none (verification only)

- [ ] **Step 1: Build + measure.**

```bash
npm run build
GZ=$(gzip -c build/frontend.js | wc -c | tr -d ' ')
echo "frontend.js gzipped: ${GZ} bytes (budget 30720)"
test "$GZ" -le 30720 && echo "BUDGET OK" || echo "BUDGET EXCEEDED"
```

  Expected: `BUDGET OK` (spec §6: ≤30 KB gz INCLUDING the repeater). If exceeded, trim before proceeding — first candidates: dedupe the row-label helper (repeater.js vs compute.js — import one from the other), shorten error strings, confirm no accidental `@wordpress/*` import leaked into the frontend entry (`grep -rn "@wordpress" src/frontend/ src/shared/`).
- [ ] **Step 2: Full gates.** `vendor/bin/phpunit` (137 baseline + all new tests) · `npm test` (87 baseline + new) · `vendor/bin/phpcs` → 0 · `npm run build` clean.
- [ ] **Step 3: wp-env visual spot-check** (spec §6: existing calculators unchanged): load a pre-v2 calculator on the demo content — identical rendering; then one calculator using repeater + date + textarea + slider-with-unit + file upload across at least `classic`, `midnight`, `minimal` themes.
- [ ] **Step 4: Nothing to commit** — this task only verifies. If trimming was needed in Step 1, commit those trims with their own message (`git add <touched files>` explicitly).

---

**Cross-chunk seams recap (for the executor):**
- Chunk 1 owns the reducer history wrapper — Task 6.5 Step 4 registers the four child actions in its `remember()` list.
- Chunk 4 owns panel chrome + palette — Tasks 6.5/7.3/7.4/7.6 make small, clearly-scoped edits to `panels/OptionsTab.jsx`, `panels/FieldGeneral.jsx`, `panels/CalcQuote.jsx`, `PaletteV2.jsx`, `icons.js`. If a file is named differently after Chunk 4 lands, grep for where `OptionsEditor`/quote-form settings mount and apply the same edit there.
- Chunk 9 (readme/screenshots) advertises: 18 field types, free repeater, file upload — no readme edits in chunks 5–7.

# Alovio Calculator 2.0 — Plan Group 3: Chunks 8–9 (Track C + §5.3 beacon + Release)

> Part of `docs/superpowers/plans/2026-07-05-alovio-calculator-v2.md`. All **Conventions** and the **File structure** section of the skeleton apply (branch `feat/v2-studio`, gates after every chunk, explicit `git add` paths, literal `'alovio-calculator'` text domain, `alovio_calc_` prefixes, version stays 1.4.1 until Task 9.6).
>
> **Line-number note:** line refs below are against the v1.4.1 tree. Chunks 1–7 shift them, so every modification also names a content anchor (a unique string to locate). Trust the anchor.
>
> **Ordering:** Chunk 8 assumes chunks 1–7 are merged into `feat/v2-studio` (the `date`/`textarea` types from Chunk 7 exist; `builder.scss`, `StudioShell.jsx`, `PaletteV2.jsx`, `LiveCanvas.jsx` from Chunks 1–4 exist).

### The `CcbCalc` intermediate struct (group-level contract — research-independent)

Chunk 8 imports through four units in the new `includes/Import/` namespace: **CcbDetector** (is Cost Calculator Builder data present — plugin active OR stored calculators found), **CcbReader** (read-only CCB storage access → `CcbCalc`), **CcbMapper** (`CcbCalc` → our config, consumed by `FieldRepository::save()` → `FieldSchema::normalize()`), **ImportController** (`GET/POST alovio-calc/v1/import/ccb`, `manage_options`). Everything downstream of `CcbReader` codes against this plain array, so Task 8.1's discoveries touch **only** the reader internals and the constants listed in Task 8.1 Step 9:

```php
// CcbCalc (associative array, documented in CcbReader's docblock):
array{
  id: int,                 // CCB's own calculator id (post ID or table row id)
  title: string,
  fields: array<int, array{
    type: string,          // RAW CCB type token, lowercase (e.g. 'range', 'drop_down', 'total')
    alias: string,         // CCB field alias, sanitize_key()-safe, unique per calc (e.g. 'range_field_id_0')
    label: string,
    options?: array<int, array{label: string, price: float}>,  // choice types
    min?: ?float, max?: ?float, step?: ?float, default?: ?float, // numeric types
    price?: float,         // toggle per-item price
    formula?: string,      // RAW CCB formula string (total fields only)
    unsupported_reason?: string, // set by the READER when the raw field can't even be parsed
  }>
}
```

---

## Chunk 8: CCB importer (spec §4.1)

### Task 8.1: RESEARCH — record CCB's real storage format as fixtures

**Files:**
- Create: `tests/fixtures/ccb/README.md`
- Create: `tests/fixtures/ccb/sample-basic.json`, `tests/fixtures/ccb/sample-extras.json`, `tests/fixtures/ccb/sample-edge.json`

This is the ONLY task whose findings later steps may depend on. The dependency is bounded to: (a) the three fixture files, (b) the README, (c) the constants/extractors named in Step 9. Nothing else in chunks 8–9 may cite "what CCB does" without pointing at a fixture.

- [ ] **Step 1: Start the sandbox.** `cd /Users/tahir/alovio-calculator && npx wp-env start` → http://localhost:8888 (admin / password).
- [ ] **Step 2: Install CCB free from wp.org.** `npx wp-env run cli wp plugin install cost-calculator-builder --activate`. (Slug differs from our mapped plugin — the `--force` working-tree trap from Conventions does not apply, and we don't use `--force` anyway.)
- [ ] **Step 3: Pin the version.** `npx wp-env run cli wp plugin get cost-calculator-builder --field=version` — record it; it goes in the README as the verified/supported version.
- [ ] **Step 4: Build sample 1 — "CCB Basic"** in wp-admin → Cost Calculator: **Range** (Area, 10–500, step 5, default 50) + **Drop Down** with priced options (Standard 2.5 / Deep 4) + **Checkbox** group (2 priced items) + **Total** multiplying range × dropdown. Save.
- [ ] **Step 5: Build sample 2 — "CCB Extras"**: **Toggle/Switch** with a price, **Quantity**, **Radio** (or a second Drop Down), **Date Picker** and **Text** if free offers them, **Total** summing toggle+quantity. Save.
- [ ] **Step 6: Build sample 3 — "CCB Edge"**: a Drop Down with **unpriced** options, one free field type we do NOT map (Multi Range / File Upload / Geolocation — whatever the free palette offers), and a **Total** our engine can't express (their function syntax, or a reference to the unmapped field) — exercises the skip + formula-fallback paths. Save.
- [ ] **Step 7: Discover the storage location.** Run and note the output of each:
  ```bash
  npx wp-env run cli wp db query "SELECT DISTINCT post_type FROM wp_posts" 
  npx wp-env run cli wp db query "SHOW TABLES"          # look for cost/calc/stm-prefixed custom tables
  npx wp-env run cli wp post list --post_type=cost-calc --post_status=any --format=table  # adjust type token to what query 1 showed
  npx wp-env run cli wp post meta list <SAMPLE1_ID> --format=json   # if CPT+meta
  # if custom tables instead:
  npx wp-env run cli wp db query "SELECT * FROM <discovered_table> LIMIT 5" --format=json
  ```
  Decision to record: **CPT+meta or custom tables**, the post-type token / table names, and which meta key(s)/columns hold the field list and the formula.
- [ ] **Step 8: Dump one fixture per sample.** For each sample calculator, capture the RAW stored structures with `--format=json` (e.g. `npx wp-env run cli wp post meta get <ID> <FIELDS_META_KEY> --format=json`) and assemble, by hand, one JSON file per sample in this exact envelope (raw values verbatim under `meta` — do not prettify their content, only the envelope):
  ```json
  {
    "ccbVersion": "<pinned version>",
    "id": 123,
    "title": "CCB Basic",
    "meta": {
      "<fields_meta_key>": <raw decoded value>,
      "<formula_meta_key_if_separate>": <raw decoded value>
    }
  }
  ```
  (Custom-table variant: replace `"meta"` with `"rows"` holding the raw row dumps; README documents which one applies.)
- [ ] **Step 9: Write `tests/fixtures/ccb/README.md`** documenting: storage kind (CPT+meta vs tables) + exact keys/tables; pinned CCB version and the supported-range claim ("importer verified against vX.Y; unknown formats are reported as unsupported, never guessed"); the raw field-object shape (key names for alias/label/min/max/step/default/options/option-price encoding); the raw formula syntax with one real example; the full list of type tokens observed; which free types we intentionally skip. Then **reconcile assumptions** — this table lists every place the assumed format is coded; update ONLY these if the discovery differs, and nothing else:

  | Assumption (coded in 8.2–8.4) | Where to update if wrong |
  |---|---|
  | CCB calculators are a CPT `cost-calc` | `CcbDetector::POST_TYPE` |
  | Plugin basename `cost-calculator-builder/cost-calculator-builder.php` | `CcbDetector::PLUGIN` |
  | Field list lives in postmeta `stm-fields` (array of field arrays) | `CcbReader::META_FIELDS` + `CcbReader::read()` |
  | Field type is the alias prefix before `_field_id_` (e.g. `range_field_id_0` → `range`) | `CcbReader::type_from_alias()` |
  | Raw field keys: `alias`, `label`, `minValue`, `maxValue`, `step`, `defaultValue`, `options` (list of `{optionText, optionValue}` where `optionValue` is `"<price>_<index>"`), `checkedPrice` (toggle), `costCalcFormula` (total) | the small `CcbReader::parse_*()` extractors |
  | Totals live inside the same field list | if the formula is a separate meta, merge it into the raw array inside `CcbReader::read()` before calling `parse()` (a synthetic `total` entry) — `parse()` signature stays |
  | Type tokens → our types table | `CcbMapper::TYPE_MAP` keys only (values are OUR types and don't change) |

  The fixtures + README are the **source of truth** from here on: Tasks 8.3/8.4 tests load the fixtures, so any mismatch shows up as red tests, not as silent drift.
- [ ] **Step 10: Leave the three sample calculators in the wp-env DB** (they're reused by Task 8.6's manual check and the Chunk 9 e2e import item). Note their IDs in the README.
- [ ] **Step 11: Commit.**
  ```bash
  git add tests/fixtures/ccb/README.md tests/fixtures/ccb/sample-basic.json tests/fixtures/ccb/sample-extras.json tests/fixtures/ccb/sample-edge.json
  git commit -m "research: record Cost Calculator Builder storage format as fixtures (version pinned)"
  ```

### Task 8.2: CcbDetector (TDD)

**Files:**
- Create: `includes/Import/CcbDetector.php`
- Create: `tests/Unit/Import/CcbDetectorTest.php`

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Import/CcbDetectorTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Import\CcbDetector;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class CcbDetectorTest extends TestCase {

	private function wpdb_returning( $var ): object {
		return new class( $var ) {
			public $posts = 'wp_posts';
			private $var;
			public function __construct( $var ) { $this->var = $var; }
			public function prepare( $sql, ...$args ) { return $sql; }
			public function get_var( $sql ) { return $this->var; }
		};
	}

	public function test_present_when_plugin_active(): void {
		Functions\when( 'is_plugin_active' )->justReturn( true );
		$GLOBALS['wpdb'] = $this->wpdb_returning( null );
		$this->assertTrue( ( new CcbDetector() )->is_present() );
	}

	public function test_present_when_inactive_but_storage_found(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );
		$GLOBALS['wpdb'] = $this->wpdb_returning( '42' ); // one stored CCB calculator
		$this->assertTrue( ( new CcbDetector() )->is_present() );
	}

	public function test_absent_when_no_plugin_and_no_storage(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );
		$GLOBALS['wpdb'] = $this->wpdb_returning( null );
		$this->assertFalse( ( new CcbDetector() )->is_present() );
	}
}
```

- [ ] **Step 2: Run it — expect FAIL** (class missing): `vendor/bin/phpunit --filter CcbDetectorTest` → `Error: Class "Alovio\Calculator\Import\CcbDetector" not found`.
- [ ] **Step 3: Write the class.** Create `includes/Import/CcbDetector.php`:

```php
<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Detects whether Cost Calculator Builder data is present on this site —
 * either their plugin is active or their stored calculators exist (a site
 * that deactivated CCB can still import).
 *
 * Storage facts are pinned by tests/fixtures/ccb/README.md (Task 8.1); the
 * two constants below are the ONLY place they touch this class.
 */
final class CcbDetector {

	/** CCB's calculator post type (verified against the recorded fixtures). */
	public const POST_TYPE = 'cost-calc';

	/** CCB's plugin basename, for the is-active check. */
	public const PLUGIN = 'cost-calculator-builder/cost-calculator-builder.php';

	public function is_present(): bool {
		return $this->is_plugin_active() || $this->has_stored_calculators();
	}

	public function is_plugin_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::PLUGIN );
	}

	/** Direct query: their post type is unregistered while CCB is inactive, so WP_Query 'any' is unreliable. */
	public function has_stored_calculators(): bool {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb.
				self::POST_TYPE
			)
		);
		return null !== $found && '' !== (string) $found;
	}
}
```

- [ ] **Step 4: Run — expect PASS**: `vendor/bin/phpunit --filter CcbDetectorTest` → 3 tests green. Full suite still green: `vendor/bin/phpunit`.
- [ ] **Step 5: WPCS**: `vendor/bin/phpcs includes/Import/CcbDetector.php` → 0 errors (run `vendor/bin/phpcbf` first if needed).
- [ ] **Step 6: Commit.**
  ```bash
  git add includes/Import/CcbDetector.php tests/Unit/Import/CcbDetectorTest.php
  git commit -m "Add CcbDetector — is Cost Calculator Builder data present"
  ```

### Task 8.3: CcbReader (TDD against the recorded fixtures)

**Files:**
- Create: `includes/Import/CcbReader.php`
- Create: `tests/Unit/Import/CcbReaderTest.php`

Format knowledge lives ONLY in this class. `parse()` is a pure static (no DB) so the fixture tests hit it directly; `list()`/`read()` are thin DB shims. The literal expectations below are written against the Task 8.1 assumption table — if Step 9 of 8.1 recorded differences, the constants/extractors were already updated there; align the test literals with the actual fixture content in the same commit (this is the bounded research dependency, nothing else moves).

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Import/CcbReaderTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Import\CcbReader;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class CcbReaderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
	}

	private function fixture( string $name ): array {
		$path = dirname( __DIR__, 2 ) . '/fixtures/ccb/' . $name;
		return json_decode( (string) file_get_contents( $path ), true );
	}

	private function parse_fixture( string $name ): ?array {
		$fx = $this->fixture( $name );
		return CcbReader::parse( (int) $fx['id'], (string) $fx['title'], $fx['meta'][ CcbReader::META_FIELDS ] ?? null );
	}

	private function by_type( array $calc, string $type ): ?array {
		foreach ( $calc['fields'] as $f ) {
			if ( $type === $f['type'] ) {
				return $f;
			}
		}
		return null;
	}

	public function test_parses_the_basic_sample_into_ccbcalc(): void {
		$calc = $this->parse_fixture( 'sample-basic.json' );
		$this->assertNotNull( $calc );
		$this->assertSame( 'CCB Basic', $calc['title'] );
		$this->assertGreaterThanOrEqual( 4, count( $calc['fields'] ) ); // range + dropdown + checkbox + total
		foreach ( $calc['fields'] as $f ) {
			$this->assertNotSame( '', $f['alias'] );
			$this->assertNotSame( '', $f['type'] );
		}
		// Range carries bounds:
		$range = $this->by_type( $calc, 'range' );
		$this->assertSame( 10.0, $range['min'] );
		$this->assertSame( 500.0, $range['max'] );
		$this->assertSame( 50.0, $range['default'] );
		// Dropdown options carry labels + prices:
		$drop = $this->by_type( $calc, 'drop_down' );
		$this->assertSame( 'Standard', $drop['options'][0]['label'] );
		$this->assertSame( 2.5, $drop['options'][0]['price'] );
		// Total carries the raw formula:
		$total = $this->by_type( $calc, 'total' );
		$this->assertNotSame( '', (string) $total['formula'] );
	}

	public function test_unparseable_raw_field_gets_unsupported_reason_not_dropped(): void {
		$calc = CcbReader::parse( 9, 'X', array( array( 'label' => 'no alias here' ) ) );
		$this->assertCount( 1, $calc['fields'] );
		$this->assertArrayHasKey( 'unsupported_reason', $calc['fields'][0] );
	}

	public function test_returns_null_for_garbage_meta(): void {
		$this->assertNull( CcbReader::parse( 9, 'X', 'not-an-array' ) );
		$this->assertNull( CcbReader::parse( 9, 'X', array() ) );
	}
}
```

- [ ] **Step 2: Run — expect FAIL**: `vendor/bin/phpunit --filter CcbReaderTest` → class not found.
- [ ] **Step 3: Write the class.** Create `includes/Import/CcbReader.php`:

```php
<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to Cost Calculator Builder storage → the CcbCalc struct
 * (shape documented in tests/fixtures/ccb/README.md and the v2 plan). The
 * mapper and UI depend on CcbCalc only, never on CCB's raw format: ALL format
 * knowledge is confined to parse()/type_from_alias()/the small extractors,
 * verified against tests/fixtures/ccb/ (Task 8.1).
 */
final class CcbReader {

	/** Meta key CCB stores its field list under (pinned by the fixtures). */
	public const META_FIELDS = 'stm-fields';

	/** @return array<int, array{id:int, title:string, fieldCount:int}> */
	public function list(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' ) ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				CcbDetector::POST_TYPE
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$calc = $this->read( (int) $row->ID );
			if ( null !== $calc ) {
				$out[] = array(
					'id'         => $calc['id'],
					'title'      => $calc['title'],
					'fieldCount' => count( $calc['fields'] ),
				);
			}
		}
		return $out;
	}

	/** @return array|null CcbCalc, or null when the stored data is unreadable. */
	public function read( int $id ): ?array {
		$raw   = get_post_meta( $id, self::META_FIELDS, true ); // WP unserializes stored arrays for us.
		$title = get_post_field( 'post_title', $id );
		return self::parse( $id, is_string( $title ) ? $title : '', $raw );
	}

	/**
	 * Pure parse: raw stored value → CcbCalc. Unit-tested against the fixtures.
	 *
	 * @param mixed $raw_fields Raw meta value (expected: array of field arrays).
	 */
	public static function parse( int $id, string $title, $raw_fields ): ?array {
		if ( ! is_array( $raw_fields ) || array() === $raw_fields ) {
			return null;
		}
		$fields = array();
		foreach ( $raw_fields as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$fields[] = self::parse_field( $f );
		}
		if ( array() === $fields ) {
			return null;
		}
		return array(
			'id'     => $id,
			'title'  => sanitize_text_field( $title ),
			'fields' => $fields,
		);
	}

	/** @param array $f Raw CCB field array. */
	private static function parse_field( array $f ): array {
		$alias = sanitize_key( (string) ( $f['alias'] ?? '' ) );
		if ( '' === $alias ) {
			return array(
				'type'               => 'unknown',
				'alias'              => '',
				'label'              => sanitize_text_field( (string) ( $f['label'] ?? '' ) ),
				'unsupported_reason' => 'unrecognized structure (no alias)',
			);
		}
		$type = self::type_from_alias( $alias );
		$out  = array(
			'type'  => $type,
			'alias' => $alias,
			'label' => sanitize_text_field( (string) ( $f['label'] ?? $alias ) ),
		);
		if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
			$out['options'] = self::parse_options( $f['options'] );
		}
		foreach ( array( 'min' => 'minValue', 'max' => 'maxValue', 'step' => 'step', 'default' => 'defaultValue' ) as $ours => $theirs ) {
			if ( isset( $f[ $theirs ] ) && is_numeric( $f[ $theirs ] ) ) {
				$out[ $ours ] = (float) $f[ $theirs ];
			}
		}
		if ( isset( $f['checkedPrice'] ) && is_numeric( $f['checkedPrice'] ) ) {
			$out['price'] = (float) $f['checkedPrice'];
		}
		if ( 'total' === $type ) {
			$out['formula'] = trim( (string) ( $f['costCalcFormula'] ?? '' ) );
		}
		return $out;
	}

	/** CCB aliases encode the type as the prefix before "_field_id_". */
	private static function type_from_alias( string $alias ): string {
		$pos = strpos( $alias, '_field_id_' );
		if ( false === $pos ) {
			return 'unknown';
		}
		return substr( $alias, 0, $pos );
	}

	/**
	 * CCB option encoding (per fixtures): [{optionText, optionValue}] where
	 * optionValue is "<price>_<index>" (e.g. "2.5_0").
	 */
	private static function parse_options( array $raw ): array {
		$out = array();
		foreach ( $raw as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$value = (string) ( $opt['optionValue'] ?? '' );
			$out[] = array(
				'label' => sanitize_text_field( (string) ( $opt['optionText'] ?? '' ) ),
				'price' => (float) strtok( $value, '_' ),
			);
		}
		return $out;
	}
}
```

- [ ] **Step 4: Run — expect PASS**: `vendor/bin/phpunit --filter CcbReaderTest`. If a literal assertion fails, the fixture disagrees with the assumed key names — fix the extractor (or the test literal to match the FIXTURE, never the other way) per the Task 8.1 Step 9 table.
- [ ] **Step 5: Full suite + WPCS**: `vendor/bin/phpunit` green; `vendor/bin/phpcs includes/Import/CcbReader.php` → 0.
- [ ] **Step 6: Commit.**
  ```bash
  git add includes/Import/CcbReader.php tests/Unit/Import/CcbReaderTest.php
  git commit -m "Add CcbReader — CCB storage to the CcbCalc intermediate struct (fixture-verified)"
  ```

### Task 8.4: CcbMapper (TDD — research-independent, consumes CcbCalc)

**Files:**
- Create: `includes/Import/CcbMapper.php`
- Create: `tests/Unit/Import/CcbMapperTest.php`

Mapper tests feed hand-written `CcbCalc` arrays — they stay green regardless of what 8.1 discovered (only `TYPE_MAP` **keys** may gain/lose raw tokens per the 8.1 table).

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Import/CcbMapperTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Import\CcbMapper;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class CcbMapperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	private function calc( array $fields ): array {
		return array( 'id' => 7, 'title' => 'From CCB', 'fields' => $fields );
	}

	public function test_maps_range_to_slider_with_bounds(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'range', 'alias' => 'range_field_id_0', 'label' => 'Area', 'min' => 10.0, 'max' => 500.0, 'step' => 5.0, 'default' => 50.0 ),
		) ) );
		$f = $r['config']['fields'][0];
		$this->assertSame( 'slider', $f['type'] );
		$this->assertSame( 'range_field_id_0', $f['id'] );
		$this->assertSame( 10.0, $f['min'] );
		$this->assertSame( 500.0, $f['max'] );
		$this->assertSame( array(), $r['skipped'] );
	}

	public function test_maps_dropdown_options_with_prices(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'drop_down', 'alias' => 'drop_down_field_id_1', 'label' => 'Service', 'options' => array(
				array( 'label' => 'Standard', 'price' => 2.5 ),
				array( 'label' => 'Deep', 'price' => 4.0 ),
			) ),
		) ) );
		$f = $r['config']['fields'][0];
		$this->assertSame( 'select', $f['type'] );
		$this->assertSame( 2.5, $f['options'][0]['price'] );
		$this->assertArrayNotHasKey( 'value', $f['options'][0] ); // FieldSchema::normalize generates opt_ slugs on save
	}

	public function test_translates_total_formula_to_ref_syntax(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'range', 'alias' => 'range_field_id_0', 'label' => 'Area' ),
			array( 'type' => 'drop_down', 'alias' => 'drop_down_field_id_1', 'label' => 'Rate', 'options' => array( array( 'label' => 'A', 'price' => 2.0 ) ) ),
			array( 'type' => 'total', 'alias' => 'total_field_id_2', 'label' => 'Total', 'formula' => 'range_field_id_0 * drop_down_field_id_1 + 10' ),
		) ) );
		$total = $r['config']['fields'][2];
		$this->assertSame( 'formula', $total['type'] );
		$this->assertSame( '{range_field_id_0} * {drop_down_field_id_1} + 10', $total['expression'] );
		$this->assertSame( array(), $r['warnings'] );
	}

	public function test_untranslatable_or_skipped_ref_formula_imports_empty_with_warning(): void {
		// (a) their function syntax; (b) a reference to a field we skipped.
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'range', 'alias' => 'range_field_id_0', 'label' => 'Area' ),
			array( 'type' => 'geolocation', 'alias' => 'geolocation_field_id_1', 'label' => 'Where' ),
			array( 'type' => 'total', 'alias' => 'total_field_id_2', 'label' => 'T1', 'formula' => 'their_func(range_field_id_0)' ),
			array( 'type' => 'total', 'alias' => 'total_field_id_3', 'label' => 'T2', 'formula' => 'geolocation_field_id_1 * 2' ),
		) ) );
		$this->assertCount( 1, $r['skipped'] ); // geolocation
		$this->assertSame( '', $r['config']['fields'][1]['expression'] );
		$this->assertSame( '', $r['config']['fields'][2]['expression'] );
		$this->assertCount( 2, $r['warnings'] );
	}

	public function test_unsupported_fields_reported_and_duplicate_aliases_suffixed(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'unknown', 'alias' => '', 'label' => 'Mystery', 'unsupported_reason' => 'unrecognized structure (no alias)' ),
			array( 'type' => 'file_upload', 'alias' => 'file_upload_field_id_0', 'label' => 'Plans' ),
			array( 'type' => 'toggle', 'alias' => 'toggle_field_id_1', 'label' => 'Express', 'price' => 30.0 ),
			array( 'type' => 'toggle', 'alias' => 'toggle_field_id_1', 'label' => 'Dup', 'price' => 5.0 ),
		) ) );
		$this->assertCount( 2, $r['skipped'] );
		$this->assertSame( 'toggle', $r['config']['fields'][0]['type'] );
		$this->assertSame( 30.0, $r['config']['fields'][0]['price'] );
		$ids = array_column( $r['config']['fields'], 'id' );
		$this->assertCount( 2, array_unique( $ids ) ); // second toggle got a _2 suffix, not dropped
	}
}
```

- [ ] **Step 2: Run — expect FAIL**: `vendor/bin/phpunit --filter CcbMapperTest` → class not found.
- [ ] **Step 3: Write the class.** Create `includes/Import/CcbMapper.php`:

```php
<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

defined( 'ABSPATH' ) || exit;

/**
 * CcbCalc → our calculator config (spec §4.1). Pure: no DB, no writes.
 * Output feeds FieldRepository::save() → FieldSchema::normalize(), which
 * regenerates option slugs and re-validates everything — the mapper only has
 * to produce FieldSchema-compatible structures.
 *
 * An import NEVER hard-fails on content: unmappable fields land in skipped[],
 * untranslatable formulas import as an empty expression + warnings[] entry.
 */
final class CcbMapper {

	/**
	 * Raw CCB type token → our field type. KEYS may be adjusted by Task 8.1's
	 * reconciliation table; values are our canonical FieldTypes tokens.
	 */
	public const TYPE_MAP = array(
		'range'       => 'slider',
		'drop_down'   => 'select',
		'dropdown'    => 'select',
		'checkbox'    => 'checkbox_group',
		'radio'       => 'radio',
		'toggle'      => 'toggle',
		'switch'      => 'toggle',
		'quantity'    => 'quantity',
		'text'        => 'text',
		'date_picker' => 'date',
		'datepicker'  => 'date',
		'total'       => 'formula',
	);

	/**
	 * Value-bearing types: legal inside translated formulas (mirror of
	 * FieldTypes::REFERENCEABLE minus 'number', which we never produce) AND
	 * shown in the summary by default; informational types are neither.
	 */
	private const VALUE_TYPES = array( 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'formula' );

	/**
	 * @param array $ccb CcbCalc (see CcbReader docblock).
	 * @return array{title:string, config:array, skipped:array<int,string>, warnings:array<int,string>}
	 */
	public static function map( array $ccb ): array {
		$fields   = array();
		$skipped  = array();
		$warnings = array();
		$ref_ids  = array(); // ids legal inside translated formulas
		$used_ids = array();
		$totals   = array();

		foreach ( (array) ( $ccb['fields'] ?? array() ) as $f ) {
			$label = (string) ( '' !== (string) ( $f['label'] ?? '' ) ? $f['label'] : ( $f['alias'] ?? '' ) );
			if ( ! empty( $f['unsupported_reason'] ) ) {
				/* translators: 1: field label, 2: technical reason */
				$skipped[] = sprintf( __( '“%1$s” skipped — %2$s.', 'alovio-calculator' ), $label, $f['unsupported_reason'] );
				continue;
			}
			$our_type = self::TYPE_MAP[ strtolower( (string) ( $f['type'] ?? '' ) ) ] ?? null;
			if ( null === $our_type ) {
				/* translators: 1: field label, 2: CCB field type token */
				$skipped[] = sprintf( __( '“%1$s” skipped — the “%2$s” field type has no free equivalent in Alovio Calculator.', 'alovio-calculator' ), $label, (string) ( $f['type'] ?? '' ) );
				continue;
			}
			if ( 'formula' === $our_type ) {
				$totals[] = $f;
				continue; // second pass — all referenceable ids must be known first
			}
			$mapped                    = self::map_simple( $f, $our_type, $label, $used_ids );
			$used_ids[ $mapped['id'] ] = true;
			$fields[]                  = $mapped;
			if ( in_array( $our_type, self::VALUE_TYPES, true ) ) {
				$ref_ids[] = $mapped['id'];
			}
		}

		foreach ( $totals as $f ) {
			$label      = (string) ( '' !== (string) ( $f['label'] ?? '' ) ? $f['label'] : $f['alias'] );
			$raw        = (string) ( $f['formula'] ?? '' );
			$translated = self::translate_formula( $raw, $ref_ids );
			if ( ! $translated['ok'] ) {
				/* translators: 1: formula field label, 2: the original CCB formula */
				$warnings[] = sprintf( __( 'The formula for “%1$s” could not be translated automatically and was imported empty — rebuild it in the builder. Original: %2$s', 'alovio-calculator' ), $label, $raw );
			}
			$id         = self::unique_id( (string) $f['alias'], $used_ids );
			$used_ids[ $id ] = true;
			$fields[]   = array(
				'id'            => $id,
				'type'          => 'formula',
				'label'         => $label,
				'expression'    => $translated['expression'],
				'showInSummary' => true,
			);
			$ref_ids[]  = $id; // later totals may reference earlier ones
		}

		return array(
			'title'    => (string) ( $ccb['title'] ?? '' ),
			'config'   => array(
				'schemaVersion' => 1,
				'fields'        => $fields,
				'settings'      => array(), // FieldSchema::normalize fills every default
			),
			'skipped'  => $skipped,
			'warnings' => $warnings,
		);
	}

	/** @param array<string,bool> $used_ids */
	private static function map_simple( array $f, string $our_type, string $label, array $used_ids ): array {
		$out = array(
			'id'            => self::unique_id( (string) $f['alias'], $used_ids ),
			'type'          => $our_type,
			'label'         => $label,
			'showInSummary' => in_array( $our_type, self::VALUE_TYPES, true ),
		);
		switch ( $our_type ) {
			case 'slider':
			case 'quantity':
				foreach ( array( 'min', 'max', 'step', 'default' ) as $k ) {
					if ( isset( $f[ $k ] ) ) {
						$out[ $k ] = (float) $f[ $k ];
					}
				}
				break;
			case 'select':
			case 'radio':
			case 'checkbox_group':
				$out['options'] = array();
				foreach ( (array) ( $f['options'] ?? array() ) as $opt ) {
					$out['options'][] = array(
						'label' => (string) ( $opt['label'] ?? '' ),
						'price' => (float) ( $opt['price'] ?? 0 ),
						// no 'value': FieldSchema::normalize_options generates fresh opt_ slugs
					);
				}
				break;
			case 'toggle':
				$out['price'] = (float) ( $f['price'] ?? 0 );
				break;
			// 'text' and 'date' carry label only.
		}
		return $out;
	}

	/** @param array<string,bool> $used_ids */
	private static function unique_id( string $alias, array $used_ids ): string {
		$id = '' !== $alias ? $alias : 'ccb_field';
		$n  = 2;
		$candidate = $id;
		while ( isset( $used_ids[ $candidate ] ) ) {
			$candidate = $id . '_' . $n;
			++$n;
		}
		return $candidate;
	}

	/**
	 * CCB formula → our {ref} syntax. Grammar accepted: known aliases, decimal
	 * numbers, + - * / ( ) and whitespace. ANYTHING else (their functions,
	 * conditionals, unknown/skipped aliases) aborts to the empty-expression
	 * fallback — we never guess at semantics.
	 *
	 * @param string[] $known_ids
	 * @return array{expression:string, ok:bool}
	 */
	public static function translate_formula( string $raw, array $known_ids ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array( 'expression' => '', 'ok' => false );
		}
		$parts = preg_split( '/([a-zA-Z_][a-zA-Z0-9_]*)/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = '';
		foreach ( (array) $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part ) ) {
				$id = strtolower( $part );
				if ( ! in_array( $id, $known_ids, true ) ) {
					return array( 'expression' => '', 'ok' => false );
				}
				$out .= '{' . $id . '}';
				continue;
			}
			if ( ! preg_match( '/^[\s0-9+\-*\/().]*$/', $part ) ) {
				return array( 'expression' => '', 'ok' => false );
			}
			$out .= $part;
		}
		return array( 'expression' => trim( $out ), 'ok' => true );
	}
}
```

- [ ] **Step 4: Run — expect PASS**: `vendor/bin/phpunit --filter CcbMapperTest` → 5 tests green.
- [ ] **Step 5: Sanity-check the mapper output survives normalization.** Add ONE more test to `CcbMapperTest` (needs the same sanitize stubs as `FieldSchemaTest::setUp` — copy those 5 `Functions\when` aliases into this test method): run `FieldSchema::normalize( $r['config'] )` on the Step-1 dropdown result and assert the field count is unchanged and `options[0]['value']` matches `/^opt_/`. Run `vendor/bin/phpunit --filter CcbMapperTest` → 6 green.
- [ ] **Step 6: Full suite + WPCS**: `vendor/bin/phpunit`; `vendor/bin/phpcs includes/Import/CcbMapper.php` → 0.
- [ ] **Step 7: Commit.**
  ```bash
  git add includes/Import/CcbMapper.php tests/Unit/Import/CcbMapperTest.php
  git commit -m "Add CcbMapper — CcbCalc to native config with type map, formula translation and skip report"
  ```

### Task 8.5: ImportController REST + service registration (TDD)

**Files:**
- Create: `includes/Import/ImportController.php`
- Create: `tests/Unit/Import/ImportControllerTest.php`
- Modify: `includes/Plugin.php` (boot() service list, currently lines 38–51 — anchor `( new Admin\ProLink() )->register();`)
- Modify: `includes/Admin/BuilderAssets.php` (localized array, currently lines 51–63 — anchor `'exportNonce'`)

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Import/ImportControllerTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Import\ImportController;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class ImportControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// FieldSchema::normalize runs inside FieldRepository::save.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->alias( static fn( $c ) => preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : '' );
		Functions\when( 'sanitize_email' )->alias( static fn( $e ) => filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '' );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'rest_ensure_response' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \stdClass && isset( $v->wp_error ) );
		Functions\when( 'get_post_field' )->justReturn( 'CCB Basic' );
	}

	/** One stored CCB calc (id 5): a quantity field. Raw format per fixtures. */
	private function stub_ccb_storage(): void {
		$GLOBALS['wpdb'] = new class() {
			public $posts = 'wp_posts';
			public function prepare( $sql, ...$args ) { return $sql; }
			public function get_var( $sql ) { return '5'; }
			public function get_results( $sql ) { return array( (object) array( 'ID' => 5, 'post_title' => 'CCB Basic' ) ); }
		};
		Functions\when( 'get_post_meta' )->alias( static function ( $id ) {
			return 5 === (int) $id ? array( array( 'alias' => 'quantity_field_id_0', 'label' => 'Windows', 'defaultValue' => 2 ) ) : '';
		} );
	}

	private function request( array $params ): object {
		return new class( $params ) {
			private $p;
			public function __construct( $p ) { $this->p = $p; }
			public function get_param( $k ) { return $this->p[ $k ] ?? null; }
		};
	}

	public function test_import_maps_then_creates_via_repository(): void {
		$this->stub_ccb_storage();
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 77 );
		Functions\expect( 'update_post_meta' )->once()->with( 77, '_alovio_calc_config', \Mockery::type( 'string' ) );
		$res = ( new ImportController() )->import( $this->request( array( 'ids' => array( 5 ) ) ) );
		$this->assertSame( 77, $res['results'][0]['created'] );
		$this->assertSame( 5, $res['results'][0]['ccbId'] );
		$this->assertSame( array(), $res['results'][0]['skipped'] );
	}

	public function test_unreadable_calculator_is_isolated_others_still_import(): void {
		$this->stub_ccb_storage(); // id 9 → get_post_meta returns '' → reader returns null
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 78 );
		Functions\expect( 'update_post_meta' )->once();
		$res = ( new ImportController() )->import( $this->request( array( 'ids' => array( 9, 5 ) ) ) );
		$this->assertNull( $res['results'][0]['created'] );
		$this->assertNotSame( '', $res['results'][0]['error'] );
		$this->assertSame( 78, $res['results'][1]['created'] );
	}

	public function test_failed_insert_reports_error_no_meta_written(): void {
		$this->stub_ccb_storage();
		$err = new \stdClass();
		$err->wp_error = true;
		Functions\expect( 'wp_insert_post' )->once()->andReturn( $err );
		Functions\expect( 'update_post_meta' )->never();
		$res = ( new ImportController() )->import( $this->request( array( 'ids' => array( 5 ) ) ) );
		$this->assertNull( $res['results'][0]['created'] );
	}

	public function test_permission_is_manage_options(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		$this->assertFalse( ( new ImportController() )->can_manage() );
	}
}
```

- [ ] **Step 2: Run — expect FAIL**: `vendor/bin/phpunit --filter ImportControllerTest` → class not found.
- [ ] **Step 3: Write the class.** Create `includes/Import/ImportController.php`:

```php
<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

use Alovio\Calculator\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * REST for the CCB importer (spec §4.1). manage_options only. Per-calculator
 * isolation: one bad calculator never aborts the batch, and we only write
 * after its map completed in full (map is pure; the write is the last step).
 */
final class ImportController {

	/** @var CcbDetector */
	private $detector;

	/** @var CcbReader */
	private $reader;

	/** @var FieldRepository */
	private $repo;

	public function __construct( ?CcbDetector $detector = null, ?CcbReader $reader = null, ?FieldRepository $repo = null ) {
		$this->detector = $detector ?? new CcbDetector();
		$this->reader   = $reader ?? new CcbReader();
		$this->repo     = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/import/ccb',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_available' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'import' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'ids' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_available() {
		if ( ! $this->detector->is_present() ) {
			return rest_ensure_response(
				array(
					'present' => false,
					'items'   => array(),
				)
			);
		}
		return rest_ensure_response(
			array(
				'present' => true,
				'items'   => $this->reader->list(),
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function import( $request ) {
		$ids     = array_map( 'absint', (array) $request->get_param( 'ids' ) );
		$results = array();
		foreach ( array_filter( array_unique( $ids ) ) as $ccb_id ) {
			$results[] = $this->import_one( $ccb_id );
		}
		return rest_ensure_response( array( 'results' => $results ) );
	}

	/** Isolation boundary: everything for ONE calculator happens inside this try/catch. */
	private function import_one( int $ccb_id ): array {
		try {
			$calc = $this->reader->read( $ccb_id );
			if ( null === $calc ) {
				return $this->failure( $ccb_id, __( 'Could not read this calculator — its stored data is missing or in an unknown format.', 'alovio-calculator' ) );
			}
			$mapped = CcbMapper::map( $calc ); // pure; no writes until this succeeded in full
			$title  = '' !== $mapped['title'] ? $mapped['title'] : __( 'Imported calculator', 'alovio-calculator' );

			$post_id = wp_insert_post(
				array(
					'post_type'   => FieldRepository::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return $this->failure( $ccb_id, __( 'Could not create the calculator post.', 'alovio-calculator' ) );
			}
			$this->repo->save( (int) $post_id, $mapped['config'] );

			return array(
				'ccbId'    => $ccb_id,
				'title'    => $title,
				'created'  => (int) $post_id,
				'skipped'  => $mapped['skipped'],
				'warnings' => $mapped['warnings'],
			);
		} catch ( \Throwable $e ) {
			return $this->failure( $ccb_id, __( 'Import failed for this calculator.', 'alovio-calculator' ) );
		}
	}

	private function failure( int $ccb_id, string $message ): array {
		return array(
			'ccbId'    => $ccb_id,
			'title'    => '',
			'created'  => null,
			'error'    => $message,
			'skipped'  => array(),
			'warnings' => array(),
		);
	}
}
```

- [ ] **Step 4: Run — expect PASS**: `vendor/bin/phpunit --filter ImportControllerTest` → 4 green. Full suite green.
- [ ] **Step 5: Register the service.** In `includes/Plugin.php` `boot()`, after the anchor line `( new Admin\ProLink() )->register();` add:

```php
		( new Import\ImportController() )->register();
```

- [ ] **Step 6: Expose detection to the builder app.** In `includes/Admin/BuilderAssets.php`, inside the `wp_localize_script` array after the `'adminPost'` entry (anchor `'adminPost'   => esc_url_raw( admin_url( 'admin-post.php' ) ),`) add:

```php
				'ccbDetected' => ( new \Alovio\Calculator\Import\CcbDetector() )->is_present(),
```

- [ ] **Step 7: WPCS + suite**: `vendor/bin/phpcs includes/Import/ImportController.php includes/Plugin.php includes/Admin/BuilderAssets.php` → 0; `vendor/bin/phpunit` green.
- [ ] **Step 8: Commit.**
  ```bash
  git add includes/Import/ImportController.php tests/Unit/Import/ImportControllerTest.php includes/Plugin.php includes/Admin/BuilderAssets.php
  git commit -m "Add CCB import REST endpoints (list + import with per-calculator mapping report)"
  ```

### Task 8.6: Import UI — menu, selection modal, mapping report

**Files:**
- Modify: `src/builder/api.js` (append after `previewCalculator`, currently line 16)
- Modify: `src/builder/CalculatorList.jsx` (topbar Import button, currently lines 109–119 — anchor `onClick={ () => fileInputRef.current`; new modal component appended in the same file per the skeleton's locked file list)

Per spec §7, heavy React component tests are skipped — this task is verified in wp-env (Step 6) and again in the Chunk 9 e2e checklist.

- [ ] **Step 1: API helpers.** Append to `src/builder/api.js`:

```js
export const listCcbImport = () => apiFetch( { path: 'alovio-calc/v1/import/ccb' } );
export const runCcbImport = ( ids ) => apiFetch( { path: 'alovio-calc/v1/import/ccb', method: 'POST', data: { ids } } );
```

- [ ] **Step 2: Swap the Import button for a menu.** In `src/builder/CalculatorList.jsx`: extend the `@wordpress/components` import line with `DropdownMenu, MenuGroup, MenuItem, Modal, CheckboxControl`; extend the `./api` import with `listCcbImport, runCcbImport`; add state `const [ ccbOpen, setCcbOpen ] = useState( false );` beside the other `useState` calls; then replace the single Import `<Button …>{ __( 'Import', 'alovio-calculator' ) }</Button>` (keep the hidden `<input type="file">` right after it) with:

```jsx
				<DropdownMenu text={ __( 'Import', 'alovio-calculator' ) } icon={ null } label={ __( 'Import', 'alovio-calculator' ) } toggleProps={ { variant: 'secondary' } }>
					{ ( { onClose } ) => (
						<MenuGroup>
							<MenuItem onClick={ () => { onClose(); if ( fileInputRef.current ) { fileInputRef.current.click(); } } }>
								{ __( 'From JSON file', 'alovio-calculator' ) }
							</MenuItem>
							{ !! ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.ccbDetected ) && (
								<MenuItem onClick={ () => { onClose(); setCcbOpen( true ); } }>
									{ __( 'From Cost Calculator Builder', 'alovio-calculator' ) }
								</MenuItem>
							) }
						</MenuGroup>
					) }
				</DropdownMenu>
```

- [ ] **Step 3: Mount the modal.** Just before the closing `</div>` of the list view (after the `{ picking && … }` block) add:

```jsx
			{ ccbOpen && (
				<CcbImportModal
					onClose={ () => {
						setCcbOpen( false );
						refresh();
					} }
				/>
			) }
```

- [ ] **Step 4: The modal + report component.** Append to the bottom of `src/builder/CalculatorList.jsx`:

```jsx
function CcbImportModal( { onClose } ) {
	const [ items, setItems ] = useState( null );
	const [ checked, setChecked ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ report, setReport ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		listCcbImport()
			.then( ( r ) => {
				setItems( r.present ? r.items : [] );
				const all = {};
				( r.items || [] ).forEach( ( it ) => ( all[ it.id ] = true ) ); // default: import everything
				setChecked( all );
			} )
			.catch( () => setError( __( 'Could not read Cost Calculator Builder data.', 'alovio-calculator' ) ) );
	}, [] );

	const selectedIds = Object.keys( checked ).filter( ( id ) => checked[ id ] ).map( Number );

	const run = async () => {
		setBusy( true );
		setError( null );
		try {
			const r = await runCcbImport( selectedIds );
			setReport( r.results || [] );
		} catch ( e ) {
			setError( __( 'Import failed. Please try again.', 'alovio-calculator' ) );
		}
		setBusy( false );
	};

	return (
		<Modal
			title={ __( 'Import from Cost Calculator Builder', 'alovio-calculator' ) }
			onRequestClose={ onClose }
			className="alc-ccb-modal"
		>
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			{ items === null && ! error && <Spinner /> }

			{ items !== null && ! report && (
				<>
					{ ! items.length && <p>{ __( 'No Cost Calculator Builder calculators were found.', 'alovio-calculator' ) }</p> }
					{ items.map( ( it ) => (
						<CheckboxControl
							key={ it.id }
							label={ `${ it.title || __( '(untitled)', 'alovio-calculator' ) } — ${ it.fieldCount } ${ __( 'fields', 'alovio-calculator' ) }` }
							checked={ !! checked[ it.id ] }
							onChange={ ( on ) => setChecked( { ...checked, [ it.id ]: on } ) }
						/>
					) ) }
					<div className="alc-modal-actions">
						<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'alovio-calculator' ) }</Button>
						<Button variant="primary" onClick={ run } isBusy={ busy } disabled={ busy || ! selectedIds.length }>
							{ __( 'Import selected', 'alovio-calculator' ) }
						</Button>
					</div>
				</>
			) }

			{ report && (
				<div className="alc-ccb-report">
					{ report.map( ( r ) => (
						<div key={ r.ccbId } className="alc-ccb-report__item">
							<h3>{ ( r.created ? '✓ ' : '✕ ' ) + ( r.title || __( '(untitled)', 'alovio-calculator' ) ) }</h3>
							{ ! r.created && !! r.error && <p className="alc-ccb-report__error">{ r.error }</p> }
							{ !! ( r.skipped && r.skipped.length ) && (
								<ul className="alc-ccb-report__skipped">{ r.skipped.map( ( s, i ) => <li key={ i }>{ s }</li> ) }</ul>
							) }
							{ !! ( r.warnings && r.warnings.length ) && (
								<ul className="alc-ccb-report__warnings">{ r.warnings.map( ( w, i ) => <li key={ i }>{ w }</li> ) }</ul>
							) }
						</div>
					) ) }
					<div className="alc-modal-actions">
						<Button variant="primary" onClick={ onClose }>{ __( 'Done', 'alovio-calculator' ) }</Button>
					</div>
				</div>
			) }
		</Modal>
	);
}
```

- [ ] **Step 5: Styles.** Append to `src/builder/builder.scss` (created in Chunk 1):

```scss
/* CCB import modal */
.alc-ccb-modal { max-width: 560px; }
.alc-ccb-report__item { border-bottom: 1px solid rgba( 128, 128, 128, 0.2 ); padding: 8px 0; }
.alc-ccb-report__item h3 { margin: 0 0 4px; font-size: 14px; }
.alc-ccb-report__error { color: #b91c1c; margin: 0; }
.alc-ccb-report__skipped li,
.alc-ccb-report__warnings li { margin: 2px 0 2px 16px; list-style: disc; font-size: 12px; }
.alc-ccb-report__warnings li { color: #92400e; }
```

- [ ] **Step 6: Build + manual verify in wp-env** (CCB samples from Task 8.1 are still in the sandbox DB): `npm run build` (clean), then in http://localhost:8888/wp-admin → Calculator: Import menu shows BOTH entries; "From Cost Calculator Builder" opens the modal listing the 3 samples with counts; import all 3 → report shows "CCB Basic"/"CCB Extras" created (✓) and "CCB Edge" created with skipped + formula warning; Done → list shows 3 new calculators; open one in the Studio → fields render, totals compute. Also: `npx wp-env run cli wp plugin deactivate cost-calculator-builder` → reload list → Import menu STILL shows the CCB entry (storage-found path); reactivate after.
- [ ] **Step 7: Jest + lint**: `npm test` (87+ baseline green — no new JS tests here), `npm run build` clean.
- [ ] **Step 8: Commit.**
  ```bash
  git add src/builder/api.js src/builder/CalculatorList.jsx src/builder/builder.scss
  git commit -m "Import UI: CCB source in the Import menu, selection modal and mapping report"
  ```

### Task 8.7: Chunk 8 gates

- [ ] **Step 1: Full gate run** from `/Users/tahir/alovio-calculator`:
  ```bash
  vendor/bin/phpunit          # green (baseline 137 + new Import tests)
  npm test                    # green (baseline 87+)
  vendor/bin/phpcs            # 0 errors
  npm run build               # clean
  ```
- [ ] **Step 2: Plugin Check** (wp-env): `npx wp-env run cli wp plugin check alovio-calculator` → no ERRORS (install `plugin-check` first if the sandbox was recreated).
- [ ] **Step 3:** Fix anything red, amend the responsible commit if trivial or add `fix:`-prefixed commits with explicit paths. Chunk ends releasable.

---

## Chunk 9: Onboarding + funnel beacon + release (spec §4.2, §5.3, §6, §7)

### Task 9.1: Onboarding notices (TDD on the pure decision logic)

**Files:**
- Create: `includes/Admin/Onboarding.php`
- Create: `tests/Unit/Admin/OnboardingTest.php` (new `tests/Unit/Admin/` dir — phpunit scans `tests/Unit` recursively)
- Modify: `includes/Plugin.php` — `activate()` (currently lines 54–57, anchor `update_option( 'alovio_calc_version'`) and `boot()` service list (anchor `( new Admin\ProLink() )->register();`)

Design (guideline-safe, NO activation redirect): two one-time dismissible notices driven by two option flags. Fresh install → `alovio_calc_welcome_notice` (set inside the activation hook only when no version option existed before). Update crossing the 2.0.0 line → `alovio_calc_whatsnew_notice` (set by an `admin_init` version compare — activation hooks never fire on updates). Both link to the builder; visiting the builder page auto-clears both (goal achieved). Dismiss uses the same `admin_post_` pattern as `ReviewNudge`.

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Admin/OnboardingTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Admin;

use Alovio\Calculator\Admin\Onboarding;
use Alovio\Calculator\Tests\TestCase;

class OnboardingTest extends TestCase {

	public function test_whatsnew_flagged_only_when_crossing_2_0_0(): void {
		$this->assertTrue( Onboarding::should_flag_whatsnew( '1.4.1', '2.0.0' ) );
		$this->assertTrue( Onboarding::should_flag_whatsnew( '1.0.0', '2.1.0' ) );
		$this->assertFalse( Onboarding::should_flag_whatsnew( '2.0.0', '2.0.1' ) );
		$this->assertFalse( Onboarding::should_flag_whatsnew( '2.0.0', '2.0.0' ) );
	}

	public function test_welcome_wins_over_whatsnew(): void {
		$this->assertSame( 'welcome', Onboarding::notice_to_show( true, true ) );
		$this->assertSame( 'welcome', Onboarding::notice_to_show( true, false ) );
		$this->assertSame( 'whatsnew', Onboarding::notice_to_show( false, true ) );
		$this->assertNull( Onboarding::notice_to_show( false, false ) );
	}
}
```

- [ ] **Step 2: Run — expect FAIL**: `vendor/bin/phpunit --filter OnboardingTest` → class not found.
- [ ] **Step 3: Write the class.** Create `includes/Admin/Onboarding.php`:

```php
<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Light onboarding (spec §4.2, guideline-safe): NO activation redirect. Two
 * one-time dismissible notices — a post-activation welcome (fresh installs)
 * and a "What's new in 2.0" for updaters. Opening the builder clears both.
 */
final class Onboarding {

	public const OPTION_WELCOME  = 'alovio_calc_welcome_notice';
	public const OPTION_WHATSNEW = 'alovio_calc_whatsnew_notice';
	private const DISMISS_ACTION = 'alovio_calc_dismiss_onboarding';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'detect_update' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'dismiss' ) );
	}

	/** Updates never fire activation hooks — catch the version change here. */
	public function detect_update(): void {
		$stored = (string) get_option( 'alovio_calc_version', '' );
		if ( '' === $stored || ALOVIO_CALC_VERSION === $stored ) {
			return; // fresh installs are handled by the activation hook
		}
		if ( self::should_flag_whatsnew( $stored, ALOVIO_CALC_VERSION ) ) {
			update_option( self::OPTION_WHATSNEW, 1 );
		}
		update_option( 'alovio_calc_version', ALOVIO_CALC_VERSION );
	}

	/** Pure: the what's-new notice fires only when crossing the 2.0.0 line. */
	public static function should_flag_whatsnew( string $from, string $to ): bool {
		return version_compare( $from, '2.0.0', '<' ) && version_compare( $to, '2.0.0', '>=' );
	}

	/** Pure: which notice (if any) to show; a fresh install outranks an update. */
	public static function notice_to_show( bool $welcome_flag, bool $whatsnew_flag ): ?string {
		if ( $welcome_flag ) {
			return 'welcome';
		}
		if ( $whatsnew_flag ) {
			return 'whatsnew';
		}
		return null;
	}

	public function maybe_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_' . AdminPage::SLUG === $screen->id ) {
			// They reached the builder — the notices did their job.
			delete_option( self::OPTION_WELCOME );
			delete_option( self::OPTION_WHATSNEW );
			return;
		}
		$which = self::notice_to_show( (bool) get_option( self::OPTION_WELCOME ), (bool) get_option( self::OPTION_WHATSNEW ) );
		if ( null === $which ) {
			return;
		}
		$builder_url = admin_url( 'admin.php?page=' . AdminPage::SLUG );
		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION . '&which=' . $which ), self::DISMISS_ACTION );
		$message     = 'welcome' === $which
			? __( 'Alovio Calculator is ready. Start from a template — you can have a working price calculator with a quote form in about ten minutes.', 'alovio-calculator' )
			: __( 'Alovio Calculator 2.0 is here: the new Builder Studio, the free repeater field and 18 field types. Your existing calculators work unchanged.', 'alovio-calculator' );
		$cta         = 'welcome' === $which
			? __( 'Create your first calculator', 'alovio-calculator' )
			: __( 'Open the new Studio', 'alovio-calculator' );
		printf(
			'<div class="notice notice-info"><p>%s</p><p><a class="button button-primary" href="%s">%s</a> <a href="%s">%s</a></p></div>',
			esc_html( $message ),
			esc_url( $builder_url ),
			esc_html( $cta ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'alovio-calculator' )
		);
	}

	public function dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'alovio-calculator' ) );
		}
		check_admin_referer( self::DISMISS_ACTION );
		$which = isset( $_GET['which'] ) ? sanitize_key( (string) wp_unslash( $_GET['which'] ) ) : '';
		delete_option( 'welcome' === $which ? self::OPTION_WELCOME : self::OPTION_WHATSNEW );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}
```

- [ ] **Step 4: Hook it up.** In `includes/Plugin.php`: (a) in `boot()` after the `Import\ImportController` line from Task 8.5 add `( new Admin\Onboarding() )->register();`; (b) replace the body of `activate()` (anchor `update_option( 'alovio_calc_version', ALOVIO_CALC_VERSION );`) with:

```php
	public function activate( bool $network_wide = false ): void {
		Entries\EntriesTable::install_for_network( $network_wide );
		if ( '' === (string) get_option( 'alovio_calc_version', '' ) ) {
			update_option( Admin\Onboarding::OPTION_WELCOME, 1 ); // fresh install → one-time welcome notice (no redirect)
		}
		update_option( 'alovio_calc_version', ALOVIO_CALC_VERSION );
	}
```

- [ ] **Step 5: Run — expect PASS**: `vendor/bin/phpunit --filter OnboardingTest` → 2 tests / 8 assertions green; full suite green.
- [ ] **Step 6: WPCS**: `vendor/bin/phpcs includes/Admin/Onboarding.php includes/Plugin.php` → 0.
- [ ] **Step 7: Commit.**
  ```bash
  git add includes/Admin/Onboarding.php tests/Unit/Admin/OnboardingTest.php includes/Plugin.php
  git commit -m "Onboarding: welcome + what's-new notices (dismissible, no redirect, builder visit clears)"
  ```

### Task 9.2: Rich empty state in the calculator list

**Files:**
- Modify: `src/builder/CalculatorList.jsx` (empty-state block, v1.4.1 lines 126–128 — anchor `alc-empty`)
- Modify: `src/builder/builder.scss` (append)

- [ ] **Step 1: Replace the empty paragraph.** In `CalculatorList.jsx`, add `const templates = ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.templates ) || [];` at the top of the component (after the `useRef` line), then replace the `{ ! items.length && ( <p className="alc-empty">…</p> ) }` block with:

```jsx
				{ ! items.length && (
					<div className="alc-start">
						<h2 className="alc-start__title">{ __( 'Build your first calculator', 'alovio-calculator' ) }</h2>
						<p className="alc-start__lead">
							{ __( 'Start from a ready template — prices, formulas and conditional logic included — or from a blank canvas.', 'alovio-calculator' ) }
						</p>
						<div className="alc-start__grid">
							<button type="button" className="alc-start__card alc-start__card--blank" onClick={ () => setPicking( true ) }>
								<span className="alc-start__card-title">{ __( 'Start blank', 'alovio-calculator' ) }</span>
								<span className="alc-start__card-desc">{ __( 'An empty canvas — add fields from the palette.', 'alovio-calculator' ) }</span>
							</button>
							{ templates.map( ( t ) => (
								<button
									type="button"
									key={ t.key }
									className="alc-start__card"
									onClick={ async () => {
										const created = await createCalculator( { title: t.title, template: t.key } );
										onEdit( created.id );
									} }
								>
									<span className="alc-start__card-title">{ t.title }</span>
									<span className="alc-start__card-desc">{ t.description }</span>
								</button>
							) ) }
						</div>
					</div>
				) }
```

- [ ] **Step 2: Styles.** Append to `src/builder/builder.scss`:

```scss
/* Rich start screen (empty list state) */
.alc-start { margin: 32px auto; max-width: 860px; text-align: center; }
.alc-start__title { font-size: 22px; margin: 0 0 4px; }
.alc-start__lead { margin: 0 0 20px; opacity: 0.75; }
.alc-start__grid { display: grid; grid-template-columns: repeat( auto-fill, minmax( 240px, 1fr ) ); gap: 12px; text-align: left; }
.alc-start__card {
	display: flex; flex-direction: column; gap: 4px; padding: 16px;
	border: 1px solid rgba( 128, 128, 128, 0.25 ); border-radius: var( --alcb-radius, 8px );
	background: transparent; cursor: pointer; transition: border-color 0.15s ease, transform 0.15s ease;
}
.alc-start__card:hover { border-color: var( --alcb-flame, #f97316 ); transform: translateY( -1px ); }
.alc-start__card--blank { border-style: dashed; }
.alc-start__card-title { font-weight: 600; }
.alc-start__card-desc { font-size: 12px; opacity: 0.7; }
```

- [ ] **Step 3: Verify in wp-env.** `npm run build`; temporarily empty the list on the sandbox: `npx wp-env run cli wp post list --post_type=alovio_calculator --format=ids` then `npx wp-env run cli wp post delete <ids> --force` (sandbox data only!). Reload Calculator admin → start screen shows Blank + 11 template cards; clicking "Cleaning Price Calculator" creates it and opens the Studio.
- [ ] **Step 4: Jest/build green**: `npm test`, `npm run build`.
- [ ] **Step 5: Commit.**
  ```bash
  git add src/builder/CalculatorList.jsx src/builder/builder.scss
  git commit -m "Rich empty state: template cards + start blank on the calculator list"
  ```

### Task 9.3: 3-step Studio pointer tour (TDD on the pure sequencing)

**Files:**
- Create: `src/builder/tour.js`
- Create: `src/builder/__tests__/tour.test.js`
- Modify: `src/builder/StudioShell.jsx` (Chunk 1) — `data-tour="save"` on the Save button, tour start effect
- Modify: `src/builder/PaletteV2.jsx` (Chunk 4) — `data-tour="palette"` on its root element
- Modify: `src/builder/LiveCanvas.jsx` (Chunk 2) — `data-tour="canvas"` on its root element

- [ ] **Step 1: Write the failing test.** Create `src/builder/__tests__/tour.test.js`:

```js
import { TOUR_STEPS, nextTourState, shouldStartTour, markTourDone, STORAGE_KEY } from '../tour';

const fakeStorage = ( initial = {} ) => {
	const data = { ...initial };
	return {
		getItem: ( k ) => ( k in data ? data[ k ] : null ),
		setItem: ( k, v ) => {
			data[ k ] = String( v );
		},
	};
};

describe( 'tour steps', () => {
	it( 'defines exactly 3 steps: palette → canvas → save', () => {
		expect( TOUR_STEPS.map( ( s ) => s.target ) ).toEqual( [
			'[data-tour="palette"]',
			'[data-tour="canvas"]',
			'[data-tour="save"]',
		] );
	} );
} );

describe( 'nextTourState', () => {
	const start = { index: 0, done: false };
	it( 'advances through all steps then completes', () => {
		let s = nextTourState( start, 'next' );
		expect( s ).toEqual( { index: 1, done: false } );
		s = nextTourState( s, 'next' );
		expect( s ).toEqual( { index: 2, done: false } );
		s = nextTourState( s, 'next' );
		expect( s.done ).toBe( true );
	} );
	it( 'dismiss completes from any step', () => {
		expect( nextTourState( { index: 1, done: false }, 'dismiss' ).done ).toBe( true );
	} );
	it( 'back never goes below step 0 and a done tour ignores actions', () => {
		expect( nextTourState( start, 'back' ).index ).toBe( 0 );
		expect( nextTourState( { index: 2, done: true }, 'next' ).done ).toBe( true );
	} );
} );

describe( 'dismissed flag', () => {
	it( 'starts only when the flag is absent, and markTourDone sets it', () => {
		const storage = fakeStorage();
		expect( shouldStartTour( storage ) ).toBe( true );
		markTourDone( storage );
		expect( storage.getItem( STORAGE_KEY ) ).toBe( '1' );
		expect( shouldStartTour( storage ) ).toBe( false );
	} );
	it( 'never starts when storage throws (private mode)', () => {
		const throwing = { getItem: () => { throw new Error( 'nope' ); } };
		expect( shouldStartTour( throwing ) ).toBe( false );
	} );
} );
```

- [ ] **Step 2: Run — expect FAIL**: `npm test -- --testPathPattern tour` → cannot find module '../tour'.
- [ ] **Step 3: Write `src/builder/tour.js`** (pure sequencing + minimal dependency-free DOM pointer):

```js
import { __ } from '@wordpress/i18n';

export const STORAGE_KEY = 'alovio_calc_tour_done';

export const TOUR_STEPS = [
	{
		target: '[data-tour="palette"]',
		title: __( 'Add fields', 'alovio-calculator' ),
		body: __( 'Click a field type to add it, or drag it straight to a spot on the canvas.', 'alovio-calculator' ),
	},
	{
		target: '[data-tour="canvas"]',
		title: __( 'This IS your calculator', 'alovio-calculator' ),
		body: __( 'The canvas runs the real calculator — type values and totals update instantly. Click any field to edit it.', 'alovio-calculator' ),
	},
	{
		target: '[data-tour="save"]',
		title: __( 'Save when ready', 'alovio-calculator' ),
		body: __( 'Save publishes your changes. Undo and redo have your back while you experiment.', 'alovio-calculator' ),
	},
];

/** Pure step sequencing — Jest-tested. */
export function nextTourState( state, action, stepCount = TOUR_STEPS.length ) {
	if ( state.done ) {
		return state;
	}
	if ( action === 'dismiss' ) {
		return { index: state.index, done: true };
	}
	if ( action === 'next' ) {
		const index = state.index + 1;
		return index >= stepCount ? { index: state.index, done: true } : { index, done: false };
	}
	if ( action === 'back' ) {
		return { index: Math.max( 0, state.index - 1 ), done: false };
	}
	return state;
}

export function shouldStartTour( storage ) {
	try {
		const s = storage || window.localStorage;
		return s.getItem( STORAGE_KEY ) !== '1';
	} catch ( e ) {
		return false; // storage unavailable → never nag, never crash
	}
}

export function markTourDone( storage ) {
	try {
		( storage || window.localStorage ).setItem( STORAGE_KEY, '1' );
	} catch ( e ) {
		// Ignore: worst case the tour shows again next session.
	}
}

/** Minimal pointer overlay: one floating card highlighting the current target. */
export function startTour( doc = document ) {
	let state = { index: 0, done: false };
	let card = null;
	let highlighted = null;

	const cleanup = () => {
		if ( card ) {
			card.remove();
			card = null;
		}
		if ( highlighted ) {
			highlighted.classList.remove( 'alcb-tour-target' );
			highlighted = null;
		}
	};

	const advance = ( action ) => {
		state = nextTourState( state, action );
		if ( state.done ) {
			cleanup();
			markTourDone();
			return;
		}
		render();
	};

	const button = ( label, action, primary ) => {
		const b = doc.createElement( 'button' );
		b.type = 'button';
		b.className = primary ? 'alcb-tour__btn alcb-tour__btn--primary' : 'alcb-tour__btn';
		b.textContent = label;
		b.addEventListener( 'click', () => advance( action ) );
		return b;
	};

	const render = () => {
		cleanup();
		const step = TOUR_STEPS[ state.index ];
		const target = doc.querySelector( step.target );
		if ( ! target ) {
			advance( 'next' ); // target missing (e.g. narrow viewport) → skip the step
			return;
		}
		highlighted = target;
		target.classList.add( 'alcb-tour-target' );

		card = doc.createElement( 'div' );
		card.className = 'alcb-tour';
		card.setAttribute( 'role', 'dialog' );
		card.setAttribute( 'aria-label', step.title );

		const title = doc.createElement( 'strong' );
		title.className = 'alcb-tour__title';
		title.textContent = step.title;
		const body = doc.createElement( 'p' );
		body.className = 'alcb-tour__body';
		body.textContent = step.body;
		const meta = doc.createElement( 'span' );
		meta.className = 'alcb-tour__meta';
		meta.textContent = `${ state.index + 1 } / ${ TOUR_STEPS.length }`;
		const actions = doc.createElement( 'div' );
		actions.className = 'alcb-tour__actions';
		actions.appendChild( button( __( 'Skip tour', 'alovio-calculator' ), 'dismiss', false ) );
		actions.appendChild(
			button(
				state.index === TOUR_STEPS.length - 1 ? __( 'Done', 'alovio-calculator' ) : __( 'Next', 'alovio-calculator' ),
				'next',
				true
			)
		);
		card.appendChild( title );
		card.appendChild( body );
		card.appendChild( meta );
		card.appendChild( actions );
		doc.body.appendChild( card );

		const rect = target.getBoundingClientRect();
		const top = Math.min( rect.bottom + 8, window.innerHeight - card.offsetHeight - 16 );
		const left = Math.min( Math.max( 8, rect.left ), window.innerWidth - card.offsetWidth - 16 );
		card.style.top = `${ Math.max( 8, top ) }px`;
		card.style.left = `${ left }px`;
	};

	render();
	return { advance, cleanup }; // exposed for debugging; production flow is button-driven
}
```

- [ ] **Step 4: Run — expect PASS**: `npm test -- --testPathPattern tour` → 6 tests green.
- [ ] **Step 5: Anchors + start hook.** (a) In `src/builder/PaletteV2.jsx` add `data-tour="palette"` to the palette's root element; (b) in `src/builder/LiveCanvas.jsx` add `data-tour="canvas"` to the canvas root; (c) in `src/builder/StudioShell.jsx` add `data-tour="save"` to the Save button, and in the component that renders once the calculator has loaded add:

```jsx
	useEffect( () => {
		if ( ! loading && shouldStartTour() ) {
			startTour();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ loading ] );
```

with `import { shouldStartTour, startTour } from './tour';`. (Exact insertion point: beside StudioShell's existing post-load effects; the guard runs once per mount and `startTour` self-gates via localStorage.)
- [ ] **Step 6: Tour styles.** Append to `src/builder/builder.scss`:

```scss
/* First-open pointer tour */
.alcb-tour {
	position: fixed; z-index: 100000; max-width: 300px; padding: 14px 16px;
	background: var( --alcb-coal, #1c1917 ); color: #fff;
	border-radius: var( --alcb-radius, 8px ); box-shadow: 0 8px 30px rgba( 0, 0, 0, 0.35 );
}
.alcb-tour__title { display: block; margin-bottom: 4px; }
.alcb-tour__body { margin: 0 0 8px; font-size: 12px; opacity: 0.85; }
.alcb-tour__meta { font-size: 11px; opacity: 0.6; }
.alcb-tour__actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
.alcb-tour__btn { background: transparent; border: 0; color: #fff; opacity: 0.7; cursor: pointer; }
.alcb-tour__btn--primary { opacity: 1; color: var( --alcb-flame, #f97316 ); font-weight: 600; }
.alcb-tour-target { outline: 2px solid var( --alcb-flame, #f97316 ); outline-offset: 2px; border-radius: 4px; }
```

- [ ] **Step 7: Verify in wp-env.** `npm run build`; clear the flag in devtools (`localStorage.removeItem('alovio_calc_tour_done')`), open a calculator → tour points at palette → canvas → Save; Done sets the flag; reload → no tour; Skip on step 1 also sets it.
- [ ] **Step 8: Full Jest + build**: `npm test`, `npm run build` clean.
- [ ] **Step 9: Commit.**
  ```bash
  git add src/builder/tour.js src/builder/__tests__/tour.test.js src/builder/StudioShell.jsx src/builder/PaletteV2.jsx src/builder/LiveCanvas.jsx src/builder/builder.scss
  git commit -m "First-open Studio tour: 3 pointer steps, localStorage dismissal, pure sequencing tested"
  ```

### Task 9.4: Analytics Counter — public `/track` endpoint (TDD)

**Files:**
- Create: `includes/Analytics/Counter.php` (new `includes/Analytics/` dir)
- Create: `tests/Unit/Analytics/CounterTest.php`
- Modify: `includes/Plugin.php` (boot() service list)
- Modify: `uninstall.php` (options list, currently line 23 — anchor `alovio_calc_review_dismissed`)

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Analytics/CounterTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Analytics;

use Alovio\Calculator\Analytics\Counter;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

class CounterTest extends TestCase {

	public function test_prune_drops_buckets_older_than_90_days_keeps_boundary(): void {
		$buckets = array(
			'2026-01-01' => 5,   // 185 days old — dropped
			'2026-04-06' => 4,   // exactly 90 days — kept (cutoff is exclusive)
			'2026-04-05' => 3,   // 91 days — dropped
			'2026-07-05' => 1,
		);
		$out = Counter::prune( $buckets, '2026-07-05' );
		$this->assertSame( array( '2026-04-06' => 4, '2026-07-05' => 1 ), $out );
	}

	public function test_record_increments_today_prunes_and_fires_action(): void {
		Functions\when( 'get_post_meta' )->justReturn( array( '2026-01-01' => 9, '2026-07-05' => 2 ) );
		Functions\expect( 'update_post_meta' )->once()->with( 7, '_alovio_calc_views', array( '2026-07-05' => 3 ) );
		Actions\expectDone( 'alovio_calc_event_recorded' )->once()->with( 7, 'view', '2026-07-05' );
		( new Counter() )->record( 7, 'view', '2026-07-05' );
	}

	public function test_interact_event_writes_the_interactions_meta(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' ); // no meta yet
		Functions\expect( 'update_post_meta' )->once()->with( 7, '_alovio_calc_interactions', array( '2026-07-05' => 1 ) );
		Actions\expectDone( 'alovio_calc_event_recorded' )->once();
		( new Counter() )->record( 7, 'interact', '2026-07-05' );
	}
}
```

- [ ] **Step 2: Run — expect FAIL**: `vendor/bin/phpunit --filter CounterTest` → class not found.
- [ ] **Step 3: Write the class.** Create `includes/Analytics/Counter.php`:

```php
<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Analytics;

use Alovio\Calculator\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Anonymous funnel beacon (spec §5.3), free-side support for Pro analytics.
 *
 * GDPR by construction: no cookies, no PII, no user agents; REMOTE_ADDR is
 * only md5'd into a transient key for rate limiting and expires within a
 * minute — never stored. Counts are approximate under full-page caching
 * (documented in readme). Buckets older than 90 days are pruned during the
 * increment write — no cron, storage stays bounded.
 */
final class Counter {

	public const META_VIEWS        = '_alovio_calc_views';
	public const META_INTERACTIONS = '_alovio_calc_interactions';
	private const EVENTS           = array( 'view', 'interact' );
	private const RATE_LIMIT       = 20; // events / minute / IP — several calculators per page still fit
	private const RETENTION_DAYS   = 90;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public + anonymous by design (see class docblock; same cache-safe reasoning as /quote).
				'callback'            => array( $this, 'handle' ),
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function handle( $request ) {
		if ( ! $this->within_rate_limit() ) {
			return new \WP_REST_Response( array( 'ok' => false ), 429 );
		}
		$calc  = absint( $request->get_param( 'calc' ) );
		$event = (string) $request->get_param( 'event' );
		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}
		if ( $calc <= 0 || FieldRepository::POST_TYPE !== get_post_type( $calc ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}
		$this->record( $calc, $event );
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Increment today's bucket, prune old ones in the same write.
	 *
	 * @param string|null $today Y-m-d override for tests (defaults to gmdate).
	 */
	public function record( int $calc_id, string $event, ?string $today = null ): void {
		$meta_key = 'view' === $event ? self::META_VIEWS : self::META_INTERACTIONS;
		$today    = null !== $today ? $today : gmdate( 'Y-m-d' );

		$buckets = get_post_meta( $calc_id, $meta_key, true );
		$buckets = is_array( $buckets ) ? $buckets : array();

		$buckets[ $today ] = (int) ( $buckets[ $today ] ?? 0 ) + 1;
		$buckets           = self::prune( $buckets, $today );

		update_post_meta( $calc_id, $meta_key, $buckets );
		do_action( 'alovio_calc_event_recorded', $calc_id, $event, $today );
	}

	/**
	 * Pure: drop buckets older than RETENTION_DAYS relative to $today.
	 * Y-m-d strings compare correctly lexicographically. No WP constants —
	 * unit-testable without the WP runtime.
	 *
	 * @param array<string,int> $buckets
	 * @return array<string,int>
	 */
	public static function prune( array $buckets, string $today ): array {
		$cutoff = gmdate( 'Y-m-d', (int) strtotime( $today . ' UTC -' . self::RETENTION_DAYS . ' days' ) );
		foreach ( array_keys( $buckets ) as $day ) {
			if ( ! is_string( $day ) || $day < $cutoff ) {
				unset( $buckets[ $day ] );
			}
		}
		return $buckets;
	}

	private function within_rate_limit(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // REMOTE_ADDR only — X-Forwarded-For is spoofable (matches QuoteController).
		// The rl_ prefix keeps these inside uninstall.php's existing transient sweep (LIKE '%alovio_calc_rl_%').
		$key   = 'alovio_calc_rl_trk_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
```

- [ ] **Step 4: Run — expect PASS**: `vendor/bin/phpunit --filter CounterTest` → 3 green; full suite green.
- [ ] **Step 5: Register + uninstall coverage.** (a) `includes/Plugin.php` `boot()`: add `( new Analytics\Counter() )->register();` after the Onboarding line. (b) `uninstall.php`: extend the options array (anchor `'alovio_calc_delete_on_uninstall'`) to:

```php
	foreach ( array( 'alovio_calc_version', 'alovio_calc_entry_count', 'alovio_calc_review_dismissed', 'alovio_calc_delete_on_uninstall', 'alovio_calc_welcome_notice', 'alovio_calc_whatsnew_notice' ) as $opt ) {
```

  (The `_alovio_calc_views`/`_alovio_calc_interactions` postmeta needs no extra sweep — `wp_delete_post( $id, true )` in the loop above already deletes each calculator's meta; the track rate-limit transients are caught by the existing `alovio_calc_rl_` LIKE sweep by construction.)
- [ ] **Step 6: WPCS**: `vendor/bin/phpcs includes/Analytics/Counter.php includes/Plugin.php uninstall.php` → 0.
- [ ] **Step 7: Commit.**
  ```bash
  git add includes/Analytics/Counter.php tests/Unit/Analytics/CounterTest.php includes/Plugin.php uninstall.php
  git commit -m "Add /track beacon endpoint: per-day view/interaction buckets with prune-on-write"
  ```

### Task 9.5: Frontend beacon wiring

**Files:**
- Modify: `includes/Frontend/CalculatorRenderer.php` — `$payload` array (v1.4.1 line 31, anchor `'quoteEndpoint'`)
- Modify: `tests/Unit/Frontend/CalculatorRendererTest.php` — payload test (anchor `test_wrapper_payload_and_no_secret_leaks`)
- Modify: `src/frontend/calculator.js` — `initCalculator` (anchor `wireQuoteForm(`) and the `input`/`change` listeners (anchor `e.target.closest( '.alc-quote' )`)

- [ ] **Step 1: Extend the payload test FIRST.** In `CalculatorRendererTest::test_wrapper_payload_and_no_secret_leaks`, after the `quoteEndpoint` assertion add:

```php
		$this->assertSame( 'https://example.test/wp-json/alovio-calc/v1/track', $payload['trackEndpoint'] );
```

  Run `vendor/bin/phpunit --filter CalculatorRendererTest` — expect FAIL (key missing).
- [ ] **Step 2: Add the endpoint to the payload.** In `CalculatorRenderer::render()`, directly under the `'quoteEndpoint'` line add:

```php
			'trackEndpoint' => esc_url( rest_url( 'alovio-calc/v1/track' ) ),
```

  Run the filter again — PASS. Full suite green.
- [ ] **Step 3: The tracker.** In `src/frontend/calculator.js` add above `initCalculator` (module scope):

```js
/**
 * Anonymous funnel beacon: at most ONE view + ONE interact per calculator per
 * pageload. Disabled inside wp-admin (the Studio canvas runs this same
 * bundle). sendBeacon survives navigation; fetch(keepalive) is the fallback.
 * No cookies, no PII — the payload is { calc, event } only.
 */
function createTracker( config ) {
	const url = config.trackEndpoint;
	const disabled = ! url || ! config.calculatorId || document.body.classList.contains( 'wp-admin' );
	const sent = {};
	const send = ( event ) => {
		if ( disabled || sent[ event ] ) {
			return;
		}
		sent[ event ] = true;
		const body = JSON.stringify( { calc: config.calculatorId, event } );
		if ( window.navigator && window.navigator.sendBeacon ) {
			window.navigator.sendBeacon( url, new window.Blob( [ body ], { type: 'application/json' } ) );
		} else if ( window.fetch ) {
			window.fetch( url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true } ).catch( () => {} );
		}
	};
	return { view: () => send( 'view' ), interact: () => send( 'interact' ) };
}
```

- [ ] **Step 4: Wire it.** Inside `initCalculator`, after the config JSON parse add `const tracker = createTracker( config );`. In BOTH the `input` and `change` listeners, immediately after the `if ( e.target.closest( '.alc-quote' ) ) { return; }` guard add `tracker.interact();` (the `sent` map dedupes across the two listeners). After the initial `recompute();` call add `tracker.view();`.
- [ ] **Step 5: Verify end-to-end in wp-env.** `npm run build`, view a published calculator page **logged out**, then:
  ```bash
  npx wp-env run cli wp post meta get <CALC_ID> _alovio_calc_views --format=json          # {"<today>":1}
  # change an input on the page, then:
  npx wp-env run cli wp post meta get <CALC_ID> _alovio_calc_interactions --format=json   # {"<today>":1}
  # reloading fires exactly one more view; typing more does NOT add interactions this pageload.
  # rate limit:
  for i in $(seq 1 25); do curl -s -o /dev/null -w '%{http_code} ' -X POST 'http://localhost:8888/?rest_route=/alovio-calc/v1/track' -H 'Content-Type: application/json' -d '{"calc":<CALC_ID>,"event":"view"}'; done; echo
  # → twenty 200s then 429s. Bad event / unknown calc → 400 (repeat with "event":"x" and "calc":999999).
  ```
  Open the same calculator in the Studio → interact with canvas inputs → meta counters unchanged (wp-admin guard).
- [ ] **Step 6: Gates**: `vendor/bin/phpunit`, `npm test`, `npm run build`, plus the frontend budget: `gzip -c build/frontend.js | wc -c` < 30720.
- [ ] **Step 7: Commit.**
  ```bash
  git add includes/Frontend/CalculatorRenderer.php tests/Unit/Frontend/CalculatorRendererTest.php src/frontend/calculator.js
  git commit -m "Frontend beacon: one view + one interact per pageload via sendBeacon (admin-guarded)"
  ```

### Task 9.6: readme.txt overhaul + version bump to 2.0.0

**Files:**
- Modify: `readme.txt` (full-file replacement below)
- Modify: `alovio-calculator.php` — `Version:` header (line 6) and `ALOVIO_CALC_VERSION` (line 18)

- [ ] **Step 1: Bump the plugin.** In `alovio-calculator.php`: ` * Version: 2.0.0` and `define( 'ALOVIO_CALC_VERSION', '2.0.0' );`. (This is the release chunk — the only permitted bump.)
- [ ] **Step 2: Replace `readme.txt` in full** with the text below (existing header fields kept, `Stable tag` bumped; the one bracketed placeholder means "keep those existing changelog entries verbatim"):

```
=== Alovio Calculator – Cost, Price & Quote Calculator Builder ===
Contributors: 74h1r
Tags: cost calculator, price calculator, quote calculator, calculator builder, estimation
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cost, price and quote calculators with live totals. Studio builder, repeater and conditional logic free, decimal-safe math, lead capture.

== Description ==

**Alovio Calculator** is a calculator-first form builder: give visitors an instant, accurate price while you collect the lead. Build a cost calculator, a price estimate or an instant quote for almost any service — cleaning, moving, construction and renovation, solar panels, landscaping, catering, flooring and tiling, print, agencies, salons or equipment rental — in minutes.

**Build in the new Studio.** No preview tab, no guesswork: the 2.0 builder canvas IS your live calculator. Type values while you build and watch totals update, drag fields exactly where you want them, undo and redo freely, and recover unsaved work after a crash. What you see is literally what your visitors get — the canvas runs the same rendering engine as your site.

**Conditional logic is free.** Show, hide or require any field based on another field — or even on the running total — with AND/OR rules and operators for equality, ranges (≥, ≤) and presence (is empty / is not empty). No upgrade wall in front of the feature a quote calculator actually needs.

**The repeater is free too.** Charge per room, per window, per guest: the repeater duplicates a group of fields for each row your visitor adds, computes a per-row total (its own little formula if you want one), and feeds the sum straight into your main formula — a field competitors reserve for paid tiers.

**The math is exact.** Every calculation runs on a fixed-point decimal engine, mirrored in PHP and JavaScript and locked together by a shared test suite — so 0.1 + 0.2 is 0.3, totals never drift by a cent, and the price your visitor sees is the price your email says. Works on any host running PHP 7.4 or newer.

= Features (all free) =

* Builder Studio: a live canvas that IS the calculator — real engine, real themes, real math while you edit — with undo/redo, drag-and-drop placement, keyboard shortcuts and local draft recovery
* 18 field types: number, slider, quantity, text, textarea, date, email, phone, URL, dropdown, multiple choice (with image cards), checkboxes, toggle, heading, HTML, formula, repeater, section/step divider
* Repeater — free: repeat a group of fields per row (per room, per window, per seat) with per-row math and a summed total your formulas can reference
* Per-option prices on choice fields; live sticky summary with line items and total
* Formula fields with `+ − × ÷`, `if()`, `min`, `max`, `round`, `ceil`, `floor`, `abs` — validated live as you type
* Conditional logic — free: show / hide / require, AND/OR, equality + range (≥, ≤) + empty/not-empty operators, conditions even on the running total
* Quote requests: name/email/phone/message form with optional file upload, entries stored in your dashboard, email notification, CSV export
* Import from Cost Calculator Builder: one click brings your existing calculators over, with a transparent per-calculator report of anything that could not be mapped
* Export and import calculators as JSON
* 11 ready templates: cleaning price, moving cost, construction cost, solar panel quote, landscaping quote, catering quote, flooring cost, print quote, agency estimate, salon pricing, rental cost
* Gutenberg block and `[alovio_calculator id="…"]` shortcode
* 6 ready themes, each a distinct design — Classic (studio card), Minimal (editorial), Midnight (dark glass), Soft (rounded pastel), Bold (neo-brutalist), Slate (compact dashboard) — plus a custom accent color
* Currency formatting you control (symbol, position, separators, decimals)
* GDPR-friendly: no external requests, no cookies, no IP storage; anonymous on-site view counters; personal-data export/erase integration
* Accessible front end: keyboard-friendly controls, screen-reader announced totals

= What it deliberately does NOT do =

No payment processing — this is a quoting tool, not a checkout. Estimates stay estimates, you stay in control of the sale, and your site avoids a whole class of payment headaches. Likewise, the date field is informational only: Alovio Calculator quotes jobs, it does not book appointments.

= Free vs Pro =

Everything above is free forever — including conditional logic and the repeater. **Alovio Calculator Pro** adds a multi-step wizard layout, branded PDF quotes with three layout presets, webhooks/Zapier, conditional email routing, and a quote analytics dashboard with a per-calculator views → interactions → quotes funnel. [Get Alovio Calculator Pro](https://alovio.org/store/calculator-pro).

= Also by Alovio =

* [Alovio Checkout Fields](https://wordpress.org/plugins/corelabs-checkout-fields/) — custom WooCommerce checkout fields with full conditional logic, field-driven fees and file uploads. 100% free.
* See [alovio.org](https://alovio.org) for the full family.

== Frequently Asked Questions ==

= Is conditional logic really free? =

Yes. Multi-rule show/hide/require logic with AND/OR matching is in the free plugin, on every field type — conditions can even watch the running total.

= Is the repeater really free? =

Yes. Add a repeater, drop number/choice fields inside it, optionally give it a per-row formula like `{area} * {rate}`, and visitors add as many rows as your limit allows. The summed total is available to your formulas like any other field.

= Can I migrate from Cost Calculator Builder? =

Yes — there is a built-in importer. If Cost Calculator Builder data is found on your site (the plugin does not even need to stay active), the Import menu lists your existing calculators; pick the ones you want and they are converted to Alovio calculators. You get a per-calculator report of everything imported, plus anything that had to be skipped and why. A formula the importer cannot translate arrives empty with a note, ready to rebuild in the Studio in minutes.

= Does it process payments? =

No, by design. Alovio Calculator generates quotes and collects leads. Connect the conversation, close the sale your way.

= Will my totals be accurate? =

Yes. Calculations use exact fixed-point decimal arithmetic (no floating-point drift), and the same engine runs in the browser and on the server — verified against a shared parity test suite on every release. The server independently recomputes every submitted quote, including repeater rows.

= What about GDPR? =

Quote entries live in your own database — no external services, no IP addresses stored. The plugin counts calculator views and interactions anonymously in your own database too: no cookies, no personal data, and the counts are simply approximate if you use full-page caching. It registers with WordPress's personal-data export and erase tools, and you can delete all plugin data on uninstall.

= Does it work with my theme / page builder? =

The calculator renders with namespaced, self-contained styles and plain JavaScript, embedded via shortcode or Gutenberg block — so it works in any theme and in any builder that renders shortcodes.

= Which PHP versions are supported? =

PHP 7.4 and newer, including PHP 8.4.

== Screenshots ==

1. The Builder Studio — the canvas is your live calculator, with undo/redo and drag-and-drop
2. A repeater on the front end: per-row totals feeding the sticky quote summary
3. Conditional logic editor — show, hide or require any field with sentence-style rules
4. The start screen: ready templates or a blank canvas
5. Quote entries dashboard with CSV export
6. Cost Calculator Builder importer with its per-calculator mapping report

== Changelog ==

= 2.0.0 =
* Builder Studio: the tabs are gone — you build inside a live canvas that IS the calculator (real engine, real themes, real totals), with undo/redo, drag-and-drop insertion, keyboard shortcuts and local draft recovery.
* Repeater field — free: repeat a group of fields per row (per room, per window, per guest); per-row formulas, capped rows, and a summed total usable in your formulas and conditions.
* 5 new informational field types: textarea, date, email, phone and URL — 18 field types in total.
* Quote form file upload (images/PDF) with strict validation, private storage and automatic cleanup.
* Import from Cost Calculator Builder: converts your existing calculators with a transparent per-calculator mapping report; formulas that cannot be translated import empty with a note.
* Slider polish: value bubble, min/max labels and an optional unit suffix.
* Lighter onboarding: a start screen with template cards, a 3-step Studio tour, and one-time welcome/what's-new notices (no redirects).
* Anonymous view/interaction counters (no cookies, no personal data) that power the Pro funnel analytics.
* Calculator JSON exports are now schemaVersion 2; version 1 files still import.

[… the existing changelog entries 1.4.1 → 1.0.0 follow here UNCHANGED — keep them verbatim from the current readme.txt, in the same order …]

== Upgrade Notice ==

= 2.0.0 =
Major update: new Builder Studio, free repeater, 18 field types, Cost Calculator Builder importer. Existing calculators keep working unchanged.
```

- [ ] **Step 3: Sanity checks.** `grep -n "Stable tag\|Version:\|ALOVIO_CALC_VERSION" readme.txt alovio-calculator.php` → all three say 2.0.0. `vendor/bin/phpunit && npm test && npm run build && vendor/bin/phpcs` → green/clean/0.
- [ ] **Step 4: Commit.**
  ```bash
  git add readme.txt alovio-calculator.php
  git commit -m "release: 2.0.0 — readme overhaul (Studio, free repeater, 18 types, CCB importer) + version bump"
  ```

### Task 9.7: QA checklist update + full e2e run in wp-env

**Files:**
- Modify: `docs/qa-checklist.md` (append the 2.0 section; keep the existing smoke/compat/a11y/gates sections and any budget lines earlier chunks added)

- [ ] **Step 1: Append to `docs/qa-checklist.md`:**

```markdown
## 2.0 additions (spec §7 — run in wp-env, all boxes required)

9.  [ ] **Studio flow**: create from the start screen's "Cleaning Price Calculator" card → Studio opens (no tabs). Drag "Number" from the palette between two fields → insertion line shows, field lands there. Edit its label → canvas updates ≤1 s. ⌘Z/Ctrl+Z reverts the label AND the insertion (two undos); ⌘⇧Z/Ctrl+Shift+Z re-applies. Reload WITHOUT saving → draft bar appears → Restore → edits intact → Save → status pill turns green → front-end page reflects everything.
10. [ ] **Repeater quote parity**: calculator with a repeater (children: number `area`, select `rate` with 2 priced options; rowExpression `{area} * {rate}`; maxRows 5) + top-level formula `{rooms} * 1.2`. Front end logged-out: add 3 rows with different values → on-screen total matches hand math. Submit a quote → entry detail shows one line per row and the SERVER total equals the on-screen total exactly (decimal-for-decimal).
11. [ ] **File upload round-trip**: enable quote-form upload (5 MB cap). Submit with a PNG → success; entry modal shows the filename as a working download link. A `.exe` (renamed `.png` too) is rejected client+server; a 6 MB file is rejected with the size message. `wp cron event run alovio_calc_file_gc 2>/dev/null` (or the GC hook name from Chunk 7) removes an orphaned upload older than 24 h.
12. [ ] **CCB import (real install)**: with `cost-calculator-builder` active and the 3 Task-8.1 samples present → Import → From Cost Calculator Builder → all 3 listed → import → report: 2 clean creates, 1 with skipped items + a formula warning → each imported calculator opens in the Studio and computes on the front end. Deactivate CCB → the menu entry STILL appears (storage detection).
13. [ ] **6 themes on canvas**: in the Studio, switch the theme quick-switcher through all 6 presets → canvas restyles each time, values survive, browser console stays clean.
14. [ ] **Wizard on canvas**: set layout=wizard (Pro sandbox or filter) → step navigation works INSIDE the canvas (Next/Back, per-step validation).
15. [ ] **Onboarding**: fresh sandbox (`npx wp-env clean all` + activate) → welcome notice on the dashboard, Dismiss removes it permanently; visiting the Calculator page also clears it. Simulate an update: `npx wp-env run cli wp option update alovio_calc_version 1.4.1` → reload wp-admin → "What's new in 2.0" notice appears once. Empty list shows the template start screen. First Studio open plays the 3-step tour; Done/Skip writes `alovio_calc_tour_done` and it never replays.
16. [ ] **Beacon**: logged-out view increments `_alovio_calc_views` (today bucket, `wp post meta get`); first input change increments `_alovio_calc_interactions`; more typing does NOT double-count; the Studio canvas records NOTHING; 25 rapid curl posts → 200×20 then 429s; bad event/calc → 400.
17. [ ] **v1 config regression**: a calculator created on 1.4.1 (import the JSON export fixture) renders IDENTICALLY on the front end (visual spot-check) and opens cleanly in the Studio.
```

- [ ] **Step 2: Run the ENTIRE checklist** (sections 1–17) top to bottom in wp-env. Fix + re-run anything red before proceeding; fixes get their own commits with explicit paths.
- [ ] **Step 3: Final gate block** (also re-run after any fix):
  ```bash
  vendor/bin/phpunit && npm test && vendor/bin/phpcs && npm run build
  gzip -c build/frontend.js | wc -c        # < 30720 (spec §6 budget incl. repeater)
  npx wp-env run cli wp plugin check alovio-calculator   # no ERRORS
  ```
  (Plus the builder-bundle budget line added to this file by the Chunk 2 planner — enforce whatever number is recorded there.)
- [ ] **Step 4: Commit.**
  ```bash
  git add docs/qa-checklist.md
  git commit -m "QA checklist: 2.0 e2e items (studio, repeater parity, upload, CCB import, onboarding, beacon)"
  ```

### Task 9.8: Re-shoot the 6 wp.org screenshots

**Files:**
- Modify: `.wporg/screenshot-1.png` … `.wporg/screenshot-6.png` (git)
- Copy to: `~/alovio-calculator-svn/assets/` (SVN, staged in Task 9.9)

All shots at **1280 px wide** (wp.org sizes verified: current set is 1280×620/720/460). Take them with the Playwright browser against wp-env at content-filling viewports — resize the browser to the exact target size so a viewport screenshot IS the final file (no cropping). Log in once (admin/password); shoot the empty-state shot FIRST, before demo data exists.

- [ ] **Step 1: Fresh canvas.** `npx wp-env clean all && npx wp-env start`, activate the plugin, `npm run build` beforehand so the bundle is current. Log in via Playwright.
- [ ] **Step 2: Shot 4 — start screen** (before creating anything): viewport 1280×620 → `wp-admin/admin.php?page=alovio-calculator` → template cards visible → save as `.wporg/screenshot-4.png`.
- [ ] **Step 3: Demo data.** Create "Cleaning Quote" from the cleaning template; add a repeater ("Rooms": area slider + rate select, rowExpression) to a second calculator "Renovation Estimate"; publish a page with each shortcode; submit 3–4 quotes with realistic names; install CCB + recreate one sample (or reuse Task 8.1 data if the sandbox wasn't cleaned) for shot 6.
- [ ] **Step 4: Shot 1 — Studio hero**: viewport 1280×620 → open "Cleaning Quote" in the Studio, select the Service field so the settings panel shows content, canvas showing live totals → `.wporg/screenshot-1.png`.
- [ ] **Step 5: Shot 2 — repeater front end**: viewport 1280×720, LOGGED-OUT tab → the "Renovation Estimate" page with 2–3 rows added and the sticky summary showing per-row lines → `.wporg/screenshot-2.png`.
- [ ] **Step 6: Shot 3 — logic editor**: viewport 1280×620 → Studio with a conditioned field selected, Logic tab open showing the sentence-token rules (and the IF pill visible on the canvas) → `.wporg/screenshot-3.png`.
- [ ] **Step 7: Shot 5 — entries**: viewport 1280×460 → Entries view with the seeded quotes → `.wporg/screenshot-5.png`.
- [ ] **Step 8: Shot 6 — CCB import report**: viewport 1280×620 → Import → From Cost Calculator Builder → run an import → capture the mapping REPORT state → `.wporg/screenshot-6.png`.
- [ ] **Step 9: Verify + stage.**
  ```bash
  sips -g pixelWidth -g pixelHeight /Users/tahir/alovio-calculator/.wporg/screenshot-*.png   # all 1280 wide
  cp /Users/tahir/alovio-calculator/.wporg/screenshot-*.png ~/alovio-calculator-svn/assets/
  git add .wporg/screenshot-1.png .wporg/screenshot-2.png .wporg/screenshot-3.png .wporg/screenshot-4.png .wporg/screenshot-5.png .wporg/screenshot-6.png
  git commit -m "release: re-shot all 6 wp.org screenshots for 2.0 (Studio hero)"
  ```
  (Icon + banner unchanged per spec §6.)

### Task 9.9: SVN release staging (user runs the final `svn ci`)

**Files:** `~/alovio-calculator-svn/{trunk,tags/2.0.0,assets}` (SVN working copy; layout verified: `trunk` currently holds `alovio-calculator.php assets build includes package.json readme.txt src uninstall.php webpack.config.js`)

- [ ] **Step 1: Fresh production build**: `cd /Users/tahir/alovio-calculator && npm run build` (build/ is gitignored but SHIPS via SVN).
- [ ] **Step 2: Mirror trunk** (exclusions mirror `.distignore` + repo-local extras; excluded receiver files are protected from `--delete` by default):
  ```bash
  rsync -av --delete \
    --exclude='.git' --exclude='.gitignore' --exclude='.DS_Store' --exclude='.distignore' \
    --exclude='.phpunit.result.cache' --exclude='.playwright-mcp' --exclude='.superpowers' \
    --exclude='.wp-env.json' --exclude='.wp-env.override.json' --exclude='.wporg' \
    --exclude='composer.json' --exclude='composer.lock' --exclude='docs' \
    --exclude='node_modules' --exclude='package-lock.json' --exclude='phpcs.xml.dist' \
    --exclude='phpunit.xml.dist' --exclude='tests' --exclude='vendor' \
    /Users/tahir/alovio-calculator/ ~/alovio-calculator-svn/trunk/
  ```
- [ ] **Step 3: Verify the trunk file set**: `ls ~/alovio-calculator-svn/trunk` → exactly the 9 entries listed above (plus anything new chunks legitimately added under `includes/`/`src/`); `grep -n "Stable tag" ~/alovio-calculator-svn/trunk/readme.txt` → 2.0.0.
- [ ] **Step 4: Stage adds/deletes**:
  ```bash
  cd ~/alovio-calculator-svn
  svn status
  svn add --force trunk assets
  svn status | awk '/^!/ { print $2 }' | xargs -r svn rm
  ```
- [ ] **Step 5: Tag**: `svn cp trunk tags/2.0.0` then `svn status | head -50` — review: tags/2.0.0 added, assets show the 6 modified screenshots, no unexpected paths.
- [ ] **Step 6: HAND OFF — the user commits** (never run this yourself; it needs their wp.org credentials):
  ```bash
  svn ci -m "Release 2.0.0: Builder Studio, free repeater, 18 field types, Cost Calculator Builder importer"
  ```
- [ ] **Step 7: Post-publish check** (~15 min later): wp.org plugin page shows 2.0.0, new screenshots render, "Tested up to" intact; install fresh on a scratch site via the directory and activate.

### Task 9.10: Git finalization + named follow-ups

- [ ] **Step 1: Merge** (branch has been kept releasable, all gates green as of 9.7):
  ```bash
  cd /Users/tahir/alovio-calculator
  git checkout main
  git merge --no-ff feat/v2-studio -m "Release 2.0.0 — Builder Studio, free repeater + 18 field types, CCB importer, onboarding, funnel beacon (tracks A-C + spec 5.3)"
  git tag -a v2.0.0 -m "Alovio Calculator 2.0.0"
  ```
- [ ] **Step 2: Push**: `git push origin main feat/v2-studio v2.0.0`.
- [ ] **Step 3: Record follow-ups** (separate repos/plans — explicitly NOT implemented here):
  - **alovio.org landing** (`~/alovio-landing`): update /calculator page + store copy for 2.0 (Studio, repeater-free, importer); deploy via its own `deploy.sh`.
  - **Demo site** (`~/alovio-demo`, `/wp` stack): deploy the 2.0.0 build, add a repeater showcase calculator, re-snapshot.
  - **Pro v1.1.0** (`~/alovio-calculator-pro`): Track D plan + same-day release on Code Heaven; its funnel UI reads `_alovio_calc_views` / `_alovio_calc_interactions` and the `alovio_calc_event_recorded` action shipped here.
  - **wp.org support**: watch the CCB-migration FAQ thread traffic for importer edge-case reports (unknown CCB versions get the graceful "unsupported" report by design).
