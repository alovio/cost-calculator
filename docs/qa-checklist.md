# Release QA Checklist (run before EVERY release)

Sandbox: `npx wp-env start` → http://localhost:8888 (admin / password).

## Functional smoke (spec §13 / plan Task 40)

1. [ ] Activate plugin → no notices/fatals; `npx wp-env run cli wp db query "SHOW TABLES LIKE '%alc_entries%'"` shows the table.
2. [ ] Admin → Calculator → create from **cleaning-price** template → builder shows the preset fields.
- [ ] Studio: open a calculator → real calculator renders on the canvas; type values → total updates instantly; add a field by click (inserts after selection) and by drag (insertion line); undo/redo via buttons and ⌘Z/⌘⇧Z (suppressed while typing in text inputs); IF pill and formula-error badge appear when applicable; theme quick-switch re-renders; Save → pill flashes green; reload → no draft bar; edit → reload without saving → draft bar restores.
3. [ ] Page with `[alovio_calculator id="N"]`, viewed logged-out: initial totals render without JS errors; inputs update the sticky summary live; the conditional heading appears only when its rule matches; numbers match hand math (50×4+50=250 …).
4. [ ] Second page with the block; pick the calculator → ServerSideRender preview; front end identical to shortcode.
5. [ ] Quote submit: valid → success message, entry in Entries view with correct snapshot/total; invalid email → inline error; 6 rapid submits → rate-limit message. `wp eval 'var_dump(get_option("alc_entry_count"));'` increments; review nudge appears at ≥3.
6. [ ] CSV export downloads with correct columns; privacy export/erase by the test email works (Tools → Export/Erase Personal Data).
7. [ ] Builder round-trip: change Express price 50→60, Save, reload builder AND front page → both reflect 60. Mark an entry read; delete it.
8. [ ] Uninstall toggle ON → delete plugin → table + CPT + options gone. Toggle OFF → data retained.

## Compatibility matrix

- [ ] Caching/optimizers: LiteSpeed Cache, Autoptimize (JS defer+minify ON), WP Rocket if licensed → calculator computes, quote submits (esp. logged-out on a CACHED page — the no-nonce design exists for this).
- [ ] Themes: Astra, Kadence, GeneratePress, Twenty Twenty-Five, Hello Elementor → layout + summary dock on mobile widths.
- [ ] `define('SCRIPT_DEBUG', true)` → browser console clean on admin + front end.

## Accessibility

- [ ] Keyboard-only walkthrough: tab order, slider arrows, toggle space, radio arrows, submit.
- [ ] Screen reader: total announces on change (`aria-live`), fieldset/legend on choice groups.

## Gates (all must pass)

```bash
vendor/bin/phpunit        # PHP suite green
npm test                  # JS suite green (incl. 28 parity fixtures)
npm run build             # clean build
vendor/bin/phpcs          # zero errors
gzip -c build/frontend.js | wc -c   # < 30720
gzip -c build/index.js | wc -c      # < 24576  (builder studio, admin-only; 17509 gz at chunk 4, re-baselined to 21165 at 2.0 after repeater panel + CCB importer + tour + start screen — same ×1.15 headroom)
npx wp-env run cli wp plugin install plugin-check --activate
npx wp-env run cli wp plugin check alovio-calculator   # no ERRORS
```

## 2.0 additions (spec §7 — run in wp-env, all boxes required)

9.  [ ] **Studio flow**: create from the start screen's "Cleaning Price Calculator" card → Studio opens (no tabs). Drag "Number" from the palette between two fields → insertion line shows, field lands there. Edit its label → canvas updates ≤1 s. ⌘Z/Ctrl+Z reverts the label AND the insertion (two undos); ⌘⇧Z/Ctrl+Shift+Z re-applies. Reload WITHOUT saving → draft bar appears → Restore → edits intact → Save → status pill turns green → front-end page reflects everything.
10. [ ] **Repeater quote parity**: calculator with a repeater (children: number `area`, select `rate` with 2 priced options; rowExpression `{area} * {rate}`; maxRows 5) + top-level formula `{rooms} * 1.2`. Front end logged-out: add 3 rows with different values → on-screen total matches hand math. Submit a quote → entry detail shows one line per row and the SERVER total equals the on-screen total exactly (decimal-for-decimal).
11. [ ] **File upload round-trip**: enable quote-form upload (5 MB cap). Submit with a PNG → success; entry modal shows the filename as a working download link. A `.exe` (renamed `.png` too) is rejected client+server; a 6 MB file is rejected with the size message. `wp cron event run alovio_calc_file_gc 2>/dev/null` (or the GC hook name from Chunk 7) removes an orphaned upload older than 24 h.
12. [ ] **CCB import (real install)**: with `cost-calculator-builder` active and the 3 Task-8.1 samples present → Import → From Cost Calculator Builder → all 3 listed → import → report: 2 clean creates, 1 with skipped items + a formula warning → each imported calculator opens in the Studio and computes on the front end. Deactivate CCB → the menu entry STILL appears (storage detection).
13. [ ] **6 themes on canvas**: in the Studio, switch the theme quick-switcher through all 6 presets → canvas restyles each time, values survive, browser console stays clean.
14. [ ] **Wizard on canvas**: set layout=wizard (Pro sandbox or filter) → step navigation works INSIDE the canvas (Next/Back, per-step validation).
15. [ ] **Onboarding**: fresh sandbox (`npx wp-env clean all` + activate) → welcome notice on the dashboard, Dismiss removes it permanently; visiting the Calculator page also clears it. Simulate an update: `npx wp-env run cli wp option update alovio_calc_version 1.4.1` → reload wp-admin → "What's new in 2.0" notice appears once. Empty list shows the template start screen. First Studio open plays the 3-step tour; Done/Skip writes `alovio_calc_tour_done` and it never replays. The `wp-env clean` wiped all content — re-create and publish a calculator (template card is fine, put its shortcode on a page) before items 16–17.
16. [ ] **Beacon**: logged-out view increments `_alovio_calc_views` (today bucket, `wp post meta get`); first input change increments `_alovio_calc_interactions`; more typing does NOT double-count; the Studio canvas records NOTHING; 25 rapid curl posts → 200×20 then 429s; bad event/calc → 400.
17. [ ] **v1 config regression**: on `main` (1.4.1 — `git stash` if needed, `npm run build`) create a calculator from a template and export it as JSON; switch back to the branch build and import that file; verify fields/settings arrive intact, the calculator renders IDENTICALLY on the front end (visual spot-check) and opens cleanly in the Studio.
