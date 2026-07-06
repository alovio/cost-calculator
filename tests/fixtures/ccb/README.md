# Cost Calculator Builder (CCB) storage format — recorded fixtures

**Source of truth for chunks 8–9.** Everything the importer claims about CCB's
format must point at these fixtures. Recorded from a live wp-env install.

- **Pinned CCB version: 4.0.14** (free, wp.org slug `cost-calculator-builder`).
- Supported-range claim: the importer is verified against 4.0.14; unknown
  formats are reported as unsupported, never guessed.
- Fixture envelope: `{ ccbVersion, id, title, postType, meta: { <meta_key>: <raw decoded value> } }`
  — values under `meta` are verbatim `get_post_meta( $id, $key, true )` output,
  JSON-encoded for the file. Only the envelope is hand-made.

## Storage kind: CPT + postmeta (no custom tables for definitions)

- Calculators are posts of type **`cost-calc`** (also seeded: `cost-calc-templates`,
  `cost-calc-categories`). The `wp_cc_*` custom tables hold ORDERS/payments only —
  never calculator definitions.
- On activation CCB seeds ~7 draft template calculators + 1 published
  "Sample Calculator".
- **Meta values are PHP-serialized arrays in the DB** (`a:1:{i:0;...`). The reader
  MUST go through `get_post_meta()` (which unserializes) — never raw SQL + json_decode.

| Meta key | Content |
|---|---|
| `stm-fields` | THE field list (array of field arrays) — everything lives here, incl. totals |
| `stm-formula` | Legacy mirror of total fields. `[]` on newly built calcs; populated on old/preset calcs. Reader ignores it (totals come from `stm-fields`). |
| `stm-conditions` | Conditional-links graph (out of scope for v2.0 import; recorded in fixtures where present) |
| `stm-name` | Mirror of the calculator title (post_title also set) |
| `stm-total-summary` | Summary-widget display settings (not imported) |
| `form_id`, `calc_saved` | Bookkeeping |

## Field-list hierarchy — NESTED, must be flattened

`stm-fields` is a tree, not a flat list:

```
[ { alias: "page_break_field_id_0", type: "page-break",
    groupElements: [ { alias: "section_field_id_0", type/section…,
        fields: [ …real fields… ] } ] } ]
```

Every observed calculator (4.0.14 demos + new-built) wraps fields in
`page-break → groupElements[] → section → fields[]`. The reader flattens by
walking `groupElements[].fields[]` recursively; a defensive fallback should also
accept fields at the top level (older CCB majors stored flat lists).

## Type token = alias prefix (NOT the `type` key)

The `type` key is unstable across CCB versions — the seeded demo calcs carry
display-style tokens (`"Quantity"`, `"Range Button"`, `"Radio Button"`) while
4.0.14-built calcs carry kebab tokens (`"range-button"`, `"drop-down"`,
`"text-area"`). The **alias prefix before `_field_id_`** is identical in both
generations and is the reliable token (`CcbReader::type_from_alias()`):

Observed alias prefixes: `page_break`, `section`, `range`, `dropDown`
(**camelCase!**), `checkbox`, `toggle`, `quantity`, `radio`, `text`, `total`,
`html`, `line`.

`_tag` (e.g. `cost-range`) is a render tag and also usable as a cross-check.

## Raw field keys (verified 4.0.14)

- Common: `alias`, `label`, `description`, `_id` (int OR string), `type`, `_tag`,
  `hidden`, `required`, `width`. New in 4.x (absent on old presets): `fieldName`
  (palette display name), `id`, `pageId`, `sectionId`, `index`.
- Numeric (`range`, `quantity`): `minValue`, `maxValue`, `step`, `default` —
  **all strings** (`"10"`, `"500"`, `"5"`, `"50"`). `unit`, `sign`, `unitSymbol`.
- Choice (`dropDown`, `radio`, `checkbox`, `toggle`): `options` =
  `[{ optionText, optionValue, optionHint? }]`.
  **`optionValue` is the PLAIN price as a decimal string** (`"2.5"`, `"30"`,
  `"0.001"`), possibly `""` for unpriced options (see sample-edge). The
  historically assumed `"<price>_<index>"` encoding does NOT occur anywhere in
  4.0.14 data (checked all seeded demos + new-built calcs).
- **Toggle is options-based**: a list of independent switches, each priced via
  `optionValue`. There is NO single on/off `checkedPrice` key in 4.0.14.
  Structurally it equals a checkbox group.
- Text: type token `text` (alias), rendered as textarea (`type: "text-area"`,
  `_tag: "cost-text"`). No price.
- Total: `costCalcFormula` (the live formula) + `legacyFormula` (mirror),
  `currency`, `totalSymbol`.

## Formula syntax (`costCalcFormula`)

- References fields by **bare alias** (no braces): `range_field_id_0 * dropDown_field_id_1`.
- The builder UI shows letters (A, B, C…) but STORES aliases.
- Conditionals are stored lowercase: `if( dropDown_field_id_0 > 2){ dropDown_field_id_0 * 3 + 25}`
  (sample-edge). Editor also offers `IF ELSE`, `AND`, `OR`, comparisons,
  `ABS/POW/ROUND/CEIL/FLOOR/MIN/MAX`, `^`, `√` — any of these that our engine
  cannot express must take the formula-fallback path (import the field, blank
  the expression, report it).

## Fixtures

| File | wp-env post ID | Exercises |
|---|---|---|
| `sample-basic.json` (237, "CCB Basic") | 237 | range 10–500 step 5 default 50; dropdown priced (Standard 2.5 / Deep 4); checkbox priced ×2; total `A*B` with real aliases |
| `sample-extras.json` (238, "CCB Extras") | 238 | toggle (2 priced switches), quantity, radio priced ×2, text(-area), total `A+B` |
| `sample-edge.json` (239, "CCB Edge") | 239 | dropdown with **unpriced** (`""`) options; `html` + `line` content/layout fields; total using `if(){}` — unsupported-formula fallback |

Free-palette types NOT captured because they are Pro-locked in 4.0.14:
Date picker, Time picker, File upload, Geolocation, Multi range, Validated form,
Repeater, Group, image choice variants. (The plan's "Date Picker if free offers
them" — it does not; documented here instead.)

The three sample calculators (+ seeded ID 236 "Sample Calculator") are left in
the wp-env DB for Task 8.6's manual check and the Chunk 9 e2e import item.

## Reconciliation against the plan's assumption table (Task 8.1 Step 9)

| Assumption (coded in 8.2–8.4) | Verdict | Action |
|---|---|---|
| CPT `cost-calc` | ✅ confirmed | none |
| Plugin basename `cost-calculator-builder/cost-calculator-builder.php` | ✅ confirmed | none |
| Field list in postmeta `stm-fields` | ✅ confirmed, but NESTED (page-break/section) and PHP-serialized | `CcbReader::read()` must flatten `groupElements[].fields[]` and use `get_post_meta` |
| Type = alias prefix before `_field_id_` | ✅ confirmed | keep; note `dropDown` camelCase + `page_break` underscore |
| Keys `alias/label/minValue/maxValue/step/defaultValue/options/checkedPrice/costCalcFormula` | ⚠️ partly | `default` (not `defaultValue`); `optionValue` = PLAIN price string, may be `""` (no `<price>_<index>` parsing!); toggle has NO `checkedPrice` — it is options-based like checkbox |
| Totals live inside the same field list | ✅ confirmed (`stm-formula` is a legacy mirror, `[]` on new calcs) | reader ignores `stm-formula` |
| Type tokens → our types table | 🔁 feed 8.4 | TYPE_MAP keys: `range`, `dropDown`, `checkbox`, `toggle`, `quantity`, `radio`, `text`, `total` (+ skip list: `page_break`, `section`, `html`, `line`) — final mapping decided in Task 8.4 |
