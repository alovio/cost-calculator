=== Alovio Calculator – Cost, Price & Quote Calculator Builder ===
Contributors: 74h1r
Tags: cost calculator, price calculator, quote calculator, calculator builder, estimation
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.1
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

= 2.0.1 =
* The admin app is now fully responsive: on phones and small tablets the Studio shows the live canvas full-width, with the field palette as a slide-in drawer and field settings as a bottom sheet (opens automatically when you tap a field).
* Calculator and entry lists turn into readable cards on small screens; on desktop the tables sit in a proper panel with consistent action buttons.
* Fixed the slider's min/max scale being squeezed next to the rail on every theme (the value bubble also stays inside the rail at the extremes).

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

= 1.4.1 =
* Alovio Calculator Pro is now available (multi-step wizard, branded PDF quotes, webhooks/Zapier, quote analytics) — links and the Pro tab updated; image choice cards were already free since 1.4.0.
* "Also by Alovio" section: Alovio Checkout Fields.

= 1.4.0 =
* Builder Preview gained Desktop / Tablet / Mobile width buttons and an "Open full preview" link, so you can check the responsive layout without leaving the editor.
* Export and import calculators as JSON — handy for backups or moving a calculator between sites.
* Multiple-choice options that have images now display as a grid of image cards.
* Each field can show optional help text underneath it.

= 1.3.0 =
* New "Preview" tab in the builder — see your calculator exactly as visitors do, updating live as you edit, without leaving the editor.

= 1.2.0 =
* New "Section / Step divider" field — group the form into labelled sections in the builder.
* Added support for the Alovio Calculator Pro add-on: the plugin now exposes the extension points the add-on uses for PDF quotes, the multi-step wizard layout, webhooks and more.
* The quote success message can now show a "Download PDF" link when the Pro add-on is active.

= 1.1.2 =
* Added a "Check for updates" link on the Plugins screen that asks WordPress to re-check WordPress.org for a new version right away.
* The plugin's row on the Plugins screen now links to the Pro upgrade.

= 1.1.1 =
* The 6 themes are now full, distinct designs rather than colour variants: Classic (studio card), Minimal (editorial ledger), Midnight (dark glass), Soft (rounded pastel), Bold (neo-brutalist), Slate (compact dashboard) — each with its own layout, typography and components.
* Fixed the calculator container to honour its width on every theme (no horizontal overflow), and the toggle switch now renders consistently across themes.

= 1.1.0 =
* Added 5 new starter templates: construction cost, solar panel quote, landscaping quote, catering quote, and flooring/tiling cost — 11 in total, each demonstrating conditional logic.
* Templates now showcase conditional pricing: optional priced fields appear based on earlier choices and add to the total only when shown.
* Conditional logic engine: new operators "is at least" (≥), "is at most" (≤), "is empty" and "is not empty".
* Conditions can now be driven by the running total or any formula result (e.g. show a bulk-discount note when the total passes a threshold).
* New "require this field" conditional action — a field stays visible and must be filled before a quote is requested (validated on the server).
* 6 ready themes (Classic, Minimal, Midnight, Soft, Bold, Slate) — choose a look from the builder with no custom CSS.
* New default accent color for new calculators.
* Documentation and readme refinements.

= 1.0.0 =
* Initial release: drag-and-drop calculator builder, decimal-safe formula engine (PHP+JS parity), free conditional logic, quote entries with CSV export and privacy tools, 6 templates, block + shortcode.

== Upgrade Notice ==

= 2.0.0 =
Major update: new Builder Studio, free repeater, 18 field types, Cost Calculator Builder importer. Existing calculators keep working unchanged.
