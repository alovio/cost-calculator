# Alovio Calculator — MVP Design Spec

**Date:** 2026-06-12 · **Status:** Approved by owner (brainstorming session 2026-06-12)
**Product:** Alovio Calculator – Cost, Price & Quote Calculator Builder
**Basis:** Market research report `~/wp-plugin-market-research-2026-06.md` (unanimous niche pick, 2026-06-11)

## 1. Overview & Goals

A free wordpress.org plugin: a **calculator-first form builder** that lets service businesses (cleaners, movers, printers, agencies, salons) publish cost/price/quote calculators with live totals and lead capture. First standalone (non-WooCommerce) product for the Alovio brand.

**Positioning wedge (drives every scope decision):**
1. **Conditional logic 100% free** — the niche leader (Stylemix Cost Calculator Builder) Pro-gates it.
2. **Reliability** — decimal-safe math (the incumbent's floating-point/locale bugs drive its 1-star reviews); **PHP 7.4+ floor** (incumbent requires PHP 8.3).
3. **Modern builder UX** (the other incumbent, Calculated Fields Form, is visually 2013-era).
4. **Lead capture with stored entries** = accumulating switching cost from day 1.

**Success criteria for MVP:** approved on wordpress.org; a non-technical user can build a working quote calculator from a template in under 10 minutes; calculations match between JS preview and PHP authority to the cent; Featured-Plugins-experiment eligible (polished, <10K installs, no vulnerabilities).

## 2. Non-Goals (explicitly out of MVP)

- **Payments — permanently out** (liability + the incumbent's worst ticket class).
- Pro-tier features (post-MVP, separate add-on plugin): multi-step wizard, PDF quotes, repeater/group fields, image option *styles*, webhooks/Zapier, analytics, AI template generator, Elementor widget.
- Visitor-facing quote emails (SMTP deliverability support burden) — admin notification only.
- WooCommerce integration of any kind. Booking/appointment features of any kind (permanent — employer conflict hygiene).
- Multi-currency, user accounts, frontend calculator editing.

## 3. Identity & Conventions

| Item | Value |
|---|---|
| Display name | Alovio Calculator – Cost, Price & Quote Calculator Builder |
| Slug / textdomain | `alovio-calculator` |
| PHP namespace | `Alovio\Calculator` (PSR-4 from `includes/`) |
| Prefix | `alc_` functions/hooks/meta, `ALC_` constants, `.alc-` CSS |
| REST namespace | `alc/v1` |
| CPT | `alc_calculator` (storage only, `show_ui => false`) |
| DB table | `{$wpdb->prefix}alc_entries` |
| Requirements | PHP 7.4+, WordPress 6.2+ |
| License | GPL-2.0-or-later |
| Repo | `~/alovio-calculator`, private GitHub `74h1r/alovio-calculator` |

Naming complies with wp.org Guideline 17: slug leads with own brand; incumbent names appear nowhere in name/slug.

## 4. Architecture

Clone-and-adapt from the two shipped plugins (`~/woo-checkout-fields`, `~/woo-product-options`): same layout, build tooling (`@wordpress/scripts` + webpack), PSR-4 autoloading, PHPUnit + Brain Monkey tests.

```
alovio-calculator.php            bootstrap → Alovio\Calculator\Plugin::instance()->boot()
includes/
  Plugin.php                     service wiring (copy pattern)
  Admin/   RestController.php    REST CRUD for calculators + entries (adapt, 90% reuse)
           AdminPage.php         top-level menu "Alovio Calculator" → React app mount
           BuilderAssets.php     enqueue + wp_localize (95% reuse)
  Fields/  FieldSchema.php       normalize/validate config JSON (80% reuse)
           FieldRepository.php   CPT-backed load/save of _alc_config (adapt)
           FieldTypes.php        registry + alc_field_types filter (pattern reuse)
  Logic/   ConditionalLogic.php  copied verbatim (modulo namespace) from woo-checkout-fields
  Formula/ Lexer.php, Parser.php, Evaluator.php, DecimalMath.php   ★ NET-NEW
  Frontend/CalculatorRenderer.php  server render (adapted from ProductFormRenderer, 60-80%)
           FrontendAssets.php    conditional enqueue (95% reuse)
           Shortcode.php, Block/ (block.json, render callback → same renderer)
  Entries/ EntriesTable.php (dbDelta), EntriesRepository.php,
           EntryMailer.php (wp_mail admin notification), CsvExporter.php,
           Privacy.php (WP personal-data exporter/eraser by email)
  Templates/Presets.php          6 vertical JSON presets
  Pro/     ProModule.php         filter-based gating stub (alc_is_pro etc.)
src/
  builder/  store.js, reducer.js, Canvas.jsx, FieldPalette.jsx (≈100% reuse)
            FieldSettings.jsx (75% — add per-option price, formula panel)
            ConditionEditor.jsx (75% — sibling-field refs only, no context tokens)
            SettingsTab.jsx, TemplatePicker.jsx, EntriesList.jsx   ★ NEW
  shared/formula/  lexer.js, parser.js, evaluator.js, decimal.js   ★ NET-NEW (PHP mirror)
  frontend/ conditional-logic.js (COPIED), calculator.js, summary-panel.js  ★ NEW-ish
tests/    PHPUnit + fixtures (formula-cases.json ★, conditional-cases.json copied)
docs/superpowers/specs/
```

**Admin app:** one top-level menu page mounting a React app with internal views: calculator list (with duplicate + copy-shortcode actions), builder, entries, settings.

**Full REST contract (`alc/v1`):**

| Route | Auth | Purpose |
|---|---|---|
| `GET/POST /calculators` | `manage_options` + REST nonce | list / create |
| `GET/PUT/DELETE /calculators/{id}` | `manage_options` + REST nonce | load / save / delete config |
| `GET /entries`, `DELETE /entries/{id}`, `PUT /entries/{id}` | `manage_options` + REST nonce | paginated list / delete / mark read |
| `POST /quote` | **public, no nonce** (see §10) | quote submission |

CSV export via `admin-post.php` (nonce + capability).

## 5. Data Model

**Calculator** = CPT post; `post_title` = name; config in post meta `_alc_config` (JSON):

```json
{ "schemaVersion": 1,
  "fields": [
    { "id": "area", "type": "slider", "label": "Area (m²)", "min": 10, "max": 500, "default": 50 },
    { "id": "service", "type": "radio", "label": "Service", "options": [
        { "value": "basic", "label": "Standard", "price": 2.5 },
        { "value": "deep", "label": "Deep clean", "price": 4, "image": 123 } ] },
    { "id": "express", "type": "toggle", "label": "Express", "price": 50 },
    { "id": "total", "type": "formula", "label": "Estimated price",
      "expression": "{area} * {service} + {express}", "showInSummary": true,
      "conditions": [], "conditionMatch": "all", "conditionAction": "show" } ],
  "settings": {
    "currency": { "symbol": "$", "position": "before", "decimals": 2,
                  "thousandSep": ",", "decimalSep": "." },
    "theme": { "accent": "#0a66ff" },
    "quoteForm": { "enabled": true, "fields": ["name", "email", "phone", "message"],
                   "notifyEmail": "" } } }
```

Condition shape is identical to the shipped plugins (single `condition` or multi `conditions[]` + `conditionMatch` + `conditionAction`) so `ConditionalLogic.php` / `conditional-logic.js` work unchanged. `schemaVersion` + stored plugin-version option reserve a migration path.

**Entries table** (created via `dbDelta` on activation):

```sql
CREATE TABLE {$prefix}alc_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  calculator_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(64) NOT NULL DEFAULT '',
  message TEXT NULL,
  snapshot LONGTEXT NOT NULL,        -- JSON: field values, active line items, total, currency
  total DECIMAL(18,4) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'new',   -- new | read
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id), KEY calculator_id (calculator_id), KEY created_at (created_at)
);
```

No IP addresses stored (GDPR-lean; the rate limiter hashes IPs into transient keys and never persists them). Privacy module registers WP personal-data exporter + eraser keyed on email. `uninstall.php` deletes data only when the "delete data on uninstall" setting is opted in.

**Multisite:** table creation runs per-site on activation; network activation iterates sites; `wp_initialize_site` hook creates the table for new subsites; opted-in uninstall iterates sites on network uninstall.

## 6. Field Types (all FREE — this is the wedge)

`number`, `slider`, `select`, `radio` (incl. basic image choice via media-library `image` per option), `checkbox-group`, `toggle`, `quantity`, `text` (excluded from math), `heading`/`divider`, `html`, **`formula`** (computed line).

- Choice fields carry **per-option `price`**. This is *new* builder UI (the shipped FieldSettings has per-field price and string-array options only — §4's "75% reuse, add per-option price" framing is the accurate one). Option `value`s are auto-generated unique slugs (`opt_xxxx`); the UI shows labels, rules and submissions store slugs — this also makes substring `contains` matching collision-safe.
- **Formula value map** (what a field contributes to `{field_id}` references): number/slider/quantity → value; radio/select → selected option price; checkbox-group → sum of checked option prices; toggle → its price when on, else 0; text/heading/html → not referenceable.
- **Condition value map** (what the copied string-map comparator sees per field type — the new value-collector contract):

| Field type | Exposed condition value |
|---|---|
| number / slider / quantity | numeric string of current value |
| select / radio | selected option **slug** (not price) |
| checkbox-group | comma-joined selected option slugs (`contains` = membership; safe because slugs are unique) |
| toggle | `"1"` when on, `""` when off |
| text | trimmed raw string |
| formula / heading / html | **not available as condition controllers** (see §7) |

- Condition actions exposed in the UI: **`show` / `hide` only**. The copied engine's `require` path exists but is unused in MVP (no dead UI wired to it).
- Every field: `showInSummary` flag and conditional rules.
- **Conditional logic fully free:** multi-conditions (`all`/`any`) + all five operators (`is, is_not, contains, gt, lt`). Hidden-by-condition fields contribute 0 to all formulas (enforced identically in PHP and JS via `active_map`).

## 7. Formula Engine (the only large net-new component)

- **Syntax:** `{field_id}` references; `+ - * / ( )`; functions `if(cond, a, b)`, `min`, `max`, `round(x, n=0)`, `ceil`, `floor`, `abs`; comparison operators inside `if`: `> < >= <= == !=`; numeric literals with `.` decimal separator (locale formatting is display-only, never parsed).
- **Implementation:** Pratt parser → AST → evaluator. Same grammar implemented twice: PHP (`includes/Formula/`) and JS (`src/shared/formula/`).
- **Decimal safety:** all numbers converted to scale-4 integers (×10⁴); arithmetic on integers; division re-scales; rounding = half-away-from-zero in both languages; overflow guard at ±9×10¹³ (beyond any real quote). Division by zero → result 0 + builder warning badge.
- **Dependency graph:** formula→formula references topologically sorted; cycles detected → builder error badge; at runtime a cyclic/broken formula evaluates to 0 and (for admins only) renders a notice.
- **Formula fields cannot be condition controllers** (ConditionEditor filters them out of the controller dropdown; FieldSchema rejects such rules on save). This keeps the two engines acyclic by construction: conditions read only raw inputs, then formulas evaluate over the resulting active set — exactly the runtime order in §8.
- **Parity testing:** `tests/fixtures/formula-cases.json` (expression + inputs + expected) consumed by both PHPUnit and Jest — same pattern as the existing `conditional-cases.json`.
- **Builder UX:** expression input with live syntax/reference validation and an insert-field-token dropdown. Errors never block saving — badge in builder, safe-0 on the front end.

## 8. Front-End Rendering & Live Calculation

- **PHP server render** (CalculatorRenderer): form HTML + `<script type="application/json" class="alc-config">` payload; initial totals computed server-side from defaults (no FOUC; meaningful static output even without JS).
- **Vanilla JS** (no jQuery): on input/change → `activeMap()` → ordered formula re-evaluation → update **sticky summary panel** (active `showInSummary` line items + grand total, `aria-live="polite"`). On mobile the panel docks to the bottom.
- **Theme:** single modern theme on CSS custom properties (accent, radius, font inherits site); RTL-safe; namespaced `.alc-` selectors to resist theme conflicts.
- Assets enqueued only when the shortcode/block is present on the page. Front-end JS budget: **≤ 30 KB gzipped total**.

## 9. Embedding

`[alovio_calculator id="123"]` shortcode + dynamic Gutenberg block (`block.json`; InspectorControls calculator picker; server `render_callback` → the same CalculatorRenderer). Single rendering path for both.

## 10. Quote / Lead Capture

- Per-calculator toggle. Form fields fixed set, configurable subset: name (required), email (required), phone, message.
- Submission: REST `POST /alc/v1/quote` — **deliberately no WP nonce** (REST nonces expire and get baked into cached HTML, guaranteeing 403s on LiteSpeed/WP Rocket/Cloudflare pages; this is a contact-form-class endpoint with no auth context or privileged side effect, so CSRF protection adds risk, not safety). Abuse controls instead: honeypot field + per-IP transient rate limit (5/min) + strict type-aware sanitization + payload size cap. Server recomputes the total from submitted values (PHP engine is authoritative — client total is ignored); stores entry (snapshot + total); sends `wp_mail` admin notification (to `quoteForm.notifyEmail`, default site admin email).
- **Response contract:** `201 {ok:true}` on success; `400 {ok:false, code, message, fieldErrors:{field:msg}}` on validation failure; `429` on rate limit. Front end: inline success message (per-calculator configurable text, default "Thanks! We'll be in touch shortly."), quote form resets, calculator selections persist; field errors render inline; network failure shows a generic retry message.
- Admin: Entries view (filter by calculator, paginate, view snapshot, mark read, delete, CSV export).
- The **3rd** collected quote fires the review-prompt nudge (dismissible, shown once — Guideline 11 compliant).

## 11. Templates

Six vertical JSON presets shipped in `Templates/Presets.php`: `cleaning-price`, `moving-cost`, `print-quote`, `agency-estimate`, `salon-pricing`, `rental-cost`. New-calculator flow offers "Blank | Template" picker. Template names double as readme long-tail keywords.

## 12. Security & Error Handling

- Capabilities: all admin REST routes + pages require `manage_options`; quote endpoint is public but rate-limited, nonce-protected, and fully sanitized.
- FieldSchema normalizes config on save **and** load (ids unique + `sanitize_key`, types whitelisted, expressions length-capped, prices cast to float, option images cast to int attachment IDs).
- Type-aware input sanitizer for quote submissions (adapted from existing Sanitizer.php); all output escaped; config embedded via `wp_json_encode`.
- `wp_mail` failures logged silently (entry is already stored — email is best-effort).
- No external HTTP requests anywhere. Freemius/telemetry: none in MVP.

## 13. Testing & Quality Gates

- **PHPUnit + Brain Monkey:** formula engine (lexer/parser/evaluator/decimal), ConditionalLogic regression (copied fixtures), FieldSchema normalization, sanitizer, renderer output, entries repository, quote endpoint validation.
- **Jest** (`@wordpress/scripts test-unit-js`): JS formula engine + conditional logic against the same shared fixtures (parity guarantee).
- **Pre-submission gates:** PHPCS + WPCS clean; **Plugin Check** clean (wp.org now auto-approves us — a guideline violation means plugin closure, so these gates are mandatory, not optional).
- **Manual QA matrix:** LiteSpeed Cache, WP Rocket, Autoptimize + 5 popular themes (Astra, Kadence, GeneratePress, Twenty Twenty-Five, Hello) — caching/optimizer JS conflicts are this niche's #1 ticket class.

## 14. i18n / a11y

Textdomain `alovio-calculator`, all strings translatable, `wp_set_script_translations` for the builder. Front end: labels bound to inputs, fieldset/legend for choice groups, `aria-live` total, keyboard-operable controls, visible focus states.

## 15. Pro Readiness (gates only, no Pro code)

`ProModule.php` stub + filters: `alc_is_pro`, `alc_field_types`, `alc_formula_functions`, `alc_price_modes`. Pro ships later as a **separate Freemius add-on plugin** (Guideline 5 trialware compliance); free plugin contains a single contextual "Pro" tab in the builder as the only upsell surface (Guideline 11).

## 16. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Formula correctness bugs (category's reputation killer) | scale-4 integer math, parity fixtures, half-away-from-zero spec'd, overflow guard |
| Caching/optimizer JS conflicts | vanilla JS, no inline-script dependencies beyond JSON payload, QA matrix, docs page |
| Builder polish slips the 4–6-week window | builder framework is ~100% reused; only FieldSettings/Formula panel/SettingsTab are new UI |
| Free-tier ceiling (30–40K niche cap) | accepted in research; revenue path = funnel conversion + Featured Plugins eligibility |
| wp.org guideline misstep post-auto-approve | PHPCS/WPCS + Plugin Check gates; no telemetry; single upsell surface |

**Build-order note for planning:** implement the formula engine + parity fixtures first — it is the only large net-new unit, everything else consumes it, and its correctness is the product's reputation backbone.
