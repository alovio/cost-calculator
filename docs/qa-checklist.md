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
gzip -c build/index.js | wc -c      # < 20480  (builder studio; measured 17509 at v2 chunk 4)
npx wp-env run cli wp plugin install plugin-check --activate
npx wp-env run cli wp plugin check alovio-calculator   # no ERRORS
```
