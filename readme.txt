=== Alovio Calculator – Cost, Price & Quote Calculator Builder ===
Contributors: 74h1r
Tags: cost calculator, price calculator, quote calculator, calculator builder, estimation
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cost, price and quote calculators with live totals. Conditional logic free, decimal-safe math, lead capture — works on PHP 7.4+.

== Description ==

**Alovio Calculator** is a calculator-first form builder: give visitors an instant, accurate price while you collect the lead. Build a cost calculator, a price estimate or an instant quote for almost any service — cleaning, moving, construction and renovation, solar panels, landscaping, catering, flooring and tiling, print, agencies, salons or equipment rental — in minutes. Start from a ready template, tweak the prices, and paste a shortcode.

**Conditional logic is free.** Show, hide or require any field based on another field — or even on the running total — with AND/OR rules and operators for equality, ranges (≥, ≤) and presence (is empty / is not empty). No upgrade wall in front of the feature a quote calculator actually needs.

**The math is exact.** Every calculation runs on a fixed-point decimal engine, mirrored in PHP and JavaScript and locked together by a shared test suite — so 0.1 + 0.2 is 0.3, totals never drift by a cent, and the price your visitor sees is the price your email says. Works on any host running PHP 7.4 or newer.

= Features (all free) =

* Drag-and-drop builder with 12 field types: number, slider, dropdown, multiple choice (with images), checkboxes, toggle, quantity, text, heading, HTML, formula, section/step divider
* Per-option prices on choice fields; live sticky summary with line items and total
* Formula fields with `+ − × ÷`, `if()`, `min`, `max`, `round`, `ceil`, `floor`, `abs` — validated live as you type
* Conditional logic — free: show / hide / require, AND/OR, equality + range (≥, ≤) + empty/not-empty operators, conditions even on the running total
* Quote requests: name/email/phone/message form, entries stored in your dashboard, email notification, CSV export
* 11 ready templates: cleaning price, moving cost, construction cost, solar panel quote, landscaping quote, catering quote, flooring cost, print quote, agency estimate, salon pricing, rental cost
* Gutenberg block and `[alovio_calculator id="…"]` shortcode
* 6 ready themes, each a distinct design — Classic (studio card), Minimal (editorial), Midnight (dark glass), Soft (rounded pastel), Bold (neo-brutalist), Slate (compact dashboard) — pick one, no CSS needed, plus a custom accent color
* Currency formatting you control (symbol, position, separators, decimals)
* GDPR-friendly: no external requests, no IP storage, personal-data export/erase integration
* Accessible front end: keyboard-friendly controls, screen-reader announced totals

= What it deliberately does NOT do =

No payment processing — this is a quoting tool, not a checkout. Estimates stay estimates, you stay in control of the sale, and your site avoids a whole class of payment headaches.

= Free vs Pro =

Everything above is free forever — including conditional logic. **Alovio Calculator Pro** adds a multi-step wizard layout, branded PDF quotes (logo, tax/VAT), webhooks/Zapier and a quote analytics dashboard. [Get Alovio Calculator Pro](https://alovio.org/store/calculator-pro).

= Also by Alovio =

* [Alovio Checkout Fields](https://wordpress.org/plugins/corelabs-checkout-fields/) — custom WooCommerce checkout fields with full conditional logic, field-driven fees and file uploads. 100% free.
* See [alovio.org](https://alovio.org) for the full family.

== Frequently Asked Questions ==

= Is conditional logic really free? =

Yes. Multi-rule show/hide logic with AND/OR matching is in the free plugin, on every field type.

= Does it process payments? =

No, by design. Alovio Calculator generates quotes and collects leads. Connect the conversation, close the sale your way.

= Will my totals be accurate? =

Yes. Calculations use exact fixed-point decimal arithmetic (no floating-point drift), and the same engine runs in the browser preview and on the server — verified against a shared parity test suite on every release. The server independently recomputes every submitted quote.

= Is this a free alternative to Cost Calculator Builder? =

Yes — and the feature most calculators paywall, conditional logic, is free here. If you are comparing against Cost Calculator Builder, Calculated Fields Form or a similar plugin, Alovio Calculator gives you show/hide rules with AND/OR matching, a decimal-safe formula engine and lead capture in the free version. Start from one of the 11 templates and you will usually have a working calculator in about ten minutes.

= Can I migrate from another calculator plugin? =

There is no automated importer yet, but the template gallery plus drag-and-drop builder makes rebuilding a typical calculator a 10-minute job. An importer is on the roadmap.

= What about GDPR? =

Quote entries live in your own database — no external services, no IP addresses stored. The plugin registers with WordPress's personal-data export and erase tools, and you can delete all plugin data on uninstall.

= Does it work with my theme / page builder? =

The calculator renders with namespaced, self-contained styles and plain JavaScript, embedded via shortcode or Gutenberg block — so it works in any theme and in any builder that renders shortcodes.

= Which PHP versions are supported? =

PHP 7.4 and newer, including PHP 8.4.

== Screenshots ==

1. Drag-and-drop builder with live formula validation
2. Cleaning price calculator on the front end with sticky quote summary
3. Conditional logic editor — show/hide any field with AND/OR rules
4. Template gallery: eleven ready-made calculators
5. Quote entries dashboard with CSV export
6. Per-calculator settings: currency, accent color, quote form

== Changelog ==

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
