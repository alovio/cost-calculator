=== Alovio Calculator – Cost, Price & Quote Calculator Builder ===
Contributors: 74h1r
Tags: cost calculator, price calculator, quote calculator, calculator builder, estimation
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cost, price and quote calculators with live totals. Conditional logic free, decimal-safe math, lead capture — works on PHP 7.4+.

== Description ==

**Alovio Calculator** is a calculator-first form builder: give visitors an instant, accurate price while you collect the lead. Build a cost calculator, a price estimate or an instant quote for almost any service — cleaning, moving, construction and renovation, solar panels, landscaping, catering, flooring and tiling, print, agencies, salons or equipment rental — in minutes. Start from a ready template, tweak the prices, and paste a shortcode.

**Conditional logic is free.** Show or hide any field based on another field — sliders, dropdowns, toggles, quantities — with AND/OR rules. No upgrade wall in front of the feature a quote calculator actually needs.

**The math is exact.** Every calculation runs on a fixed-point decimal engine, mirrored in PHP and JavaScript and locked together by a shared test suite — so 0.1 + 0.2 is 0.3, totals never drift by a cent, and the price your visitor sees is the price your email says. Works on any host running PHP 7.4 or newer.

= Features (all free) =

* Drag-and-drop builder with 11 field types: number, slider, dropdown, multiple choice (with images), checkboxes, toggle, quantity, text, heading, HTML, formula
* Per-option prices on choice fields; live sticky summary with line items and total
* Formula fields with `+ − × ÷`, `if()`, `min`, `max`, `round`, `ceil`, `floor`, `abs` — validated live as you type
* Conditional logic (show/hide, AND/OR, all operators) — free
* Quote requests: name/email/phone/message form, entries stored in your dashboard, email notification, CSV export
* 11 ready templates: cleaning price, moving cost, construction cost, solar panel quote, landscaping quote, catering quote, flooring cost, print quote, agency estimate, salon pricing, rental cost
* Gutenberg block and `[alovio_calculator id="…"]` shortcode
* Currency formatting you control (symbol, position, separators, decimals)
* GDPR-friendly: no external requests, no IP storage, personal-data export/erase integration
* Accessible front end: keyboard-friendly controls, screen-reader announced totals

= What it deliberately does NOT do =

No payment processing — this is a quoting tool, not a checkout. Estimates stay estimates, you stay in control of the sale, and your site avoids a whole class of payment headaches.

= Free vs Pro =

Everything above is free forever — including conditional logic. **Alovio Calculator Pro** (coming soon) adds a multi-step wizard, PDF quotes, repeater fields, image option styles, webhooks/Zapier and quote analytics. [Learn more](https://alovio.org/calculator).

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

= 1.1.0 =
* Added 5 new starter templates: construction cost, solar panel quote, landscaping quote, catering quote, and flooring/tiling cost — 11 in total, each demonstrating conditional logic.
* Templates now showcase conditional pricing: optional priced fields appear based on earlier choices and add to the total only when shown.
* New default accent color for new calculators.
* Documentation and readme refinements.

= 1.0.0 =
* Initial release: drag-and-drop calculator builder, decimal-safe formula engine (PHP+JS parity), free conditional logic, quote entries with CSV export and privacy tools, 6 templates, block + shortcode.
