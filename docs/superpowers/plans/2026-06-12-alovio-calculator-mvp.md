# Alovio Calculator MVP Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the free MVP of Alovio Calculator — a WordPress cost/price/quote calculator builder with a React admin builder, a decimal-safe formula engine mirrored in PHP and JS, free conditional logic, lead-capture entries, six templates, and shortcode + block embedding.

**Architecture:** Clone-and-adapt from two shipped sibling plugins (`/Users/tahir/woo-checkout-fields`, `/Users/tahir/woo-product-options`): same PSR-4 `includes/` layout, `@wordpress/scripts` build, React builder over REST, PHP server rendering with a JSON config payload, vanilla-JS front end. The only large net-new unit is the formula engine (lexer → Pratt parser → evaluator over scale-4 integer math), built first with shared parity fixtures consumed by both PHPUnit and Jest.

**Tech Stack:** PHP 7.4+, WordPress 6.2+, `@wordpress/scripts` (webpack/Babel/Jest), React (`@wordpress/element`, `@wordpress/data`, `@wordpress/components`), PHPUnit 9 + Brain Monkey, dbDelta custom table.

**Spec:** `docs/superpowers/specs/2026-06-12-alovio-calculator-mvp-design.md` — read it before starting any chunk. Section references (§N) below point there.

---

## Conventions (read first, applies to every task)

- **Git:** stage files explicitly by path — `git add <file> <file>`. NEVER `git add -A` or `git add .`. NEVER add `Co-Authored-By` lines to commits. Conventional-commit prefixes (`feat:`, `test:`, `chore:`, `fix:`, `docs:`).
- **Run PHP tests:** `cd /Users/tahir/alovio-calculator && vendor/bin/phpunit` (filter: `vendor/bin/phpunit --filter TestName`).
- **Run JS tests:** `npm test` (wp-scripts → Jest). **Build:** `npm run build`. **Lint PHP syntax:** `php -l <file>`.
- **Source repos for copied code** (read-only — never modify them): `/Users/tahir/woo-checkout-fields` (referred to as **CF**), `/Users/tahir/woo-product-options` (**PO**).
- **Namespaces:** copied PHP files change `CoreLabs\CheckoutFields\…` / `CoreLabs\ProductOptions\…` → `Alovio\Calculator\…`. Prefixes: `alc_` (hooks/meta/options), `ALC_` (constants), `.alc-` (CSS), `alc/v1` (REST).
- **Formula engine classes must stay WP-free** (no WordPress functions) so they unit-test without Brain Monkey and mirror cleanly to JS.
- All user-visible strings: `__( 'Text', 'alovio-calculator' )` in PHP, `__( 'Text', 'alovio-calculator' )` from `@wordpress/i18n` in JS.

---

## Chunk 1: Scaffolding & Toolchain

### Task 1: Repository skeleton + main plugin file

**Files:**
- Create: `alovio-calculator.php`, `.gitignore`, `readme.txt`

- [ ] **Step 1: Create `.gitignore`**

```gitignore
node_modules/
vendor/
build/
*.log
.DS_Store
.phpunit.result.cache
```

(Lockfiles are deliberately NOT ignored — CF commits both `composer.lock` and `package-lock.json` (verified via `git ls-files` there), so this repo does too; they get committed in Tasks 3 and 4.)

- [ ] **Step 2: Create `alovio-calculator.php`**

```php
<?php
/**
 * Plugin Name: Alovio Calculator – Cost, Price & Quote Calculator Builder
 * Plugin URI: https://alovio.org/calculator
 * Description: Build cost, price and quote calculators with live totals, free conditional logic, and lead capture.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Alovio
 * Author URI: https://alovio.org
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: alovio-calculator
 */

defined( 'ABSPATH' ) || exit;

define( 'ALC_VERSION', '0.1.0' );
define( 'ALC_FILE', __FILE__ );
define( 'ALC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALC_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'Alovio\\Calculator\\' ) ) {
			return;
		}
		$relative = substr( $class, strlen( 'Alovio\\Calculator\\' ) );
		$path     = ALC_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

Alovio\Calculator\Plugin::instance()->boot();
```

- [ ] **Step 3: Create `readme.txt` stub** (full SEO readme is Task 41; this keeps the repo valid meanwhile)

```text
=== Alovio Calculator – Cost, Price & Quote Calculator Builder ===
Contributors: alovio
Tags: cost calculator, price calculator, quote calculator, calculator builder, estimation
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build cost, price and quote calculators with live totals, free conditional logic, and lead capture.
```

- [ ] **Step 4: Commit**

```bash
git add .gitignore alovio-calculator.php readme.txt
git commit -m "chore: plugin skeleton (header, constants, PSR-4 autoloader)"
```

### Task 2: Plugin bootstrap class

**Files:**
- Create: `includes/Plugin.php`
- Test: `php -l` only (behavior is wiring; covered when services land)

- [ ] **Step 1: Create `includes/Plugin.php`**

```php
<?php
namespace Alovio\Calculator;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		register_activation_hook( ALC_FILE, [ $this, 'activate' ] );
		add_action( 'init', [ $this, 'init' ] );
		// Services register themselves here as later tasks add them.
	}

	public function activate(): void {
		update_option( 'alc_version', ALC_VERSION );
	}

	public function init(): void {
		load_plugin_textdomain( 'alovio-calculator' );
	}
}
```

(Later tasks append exact lines to `boot()`/`init()`/`activate()` — each such task quotes the precise line to add.)

- [ ] **Step 2: Verify syntax**

Run: `php -l includes/Plugin.php && php -l alovio-calculator.php`
Expected: `No syntax errors detected` twice.

- [ ] **Step 3: Commit**

```bash
git add includes/Plugin.php
git commit -m "feat: Plugin bootstrap singleton with activation/init hooks"
```

### Task 3: PHP test harness (PHPUnit + Brain Monkey)

**Files:**
- Create: `composer.json`, `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/TestCase.php`, `tests/Unit/SmokeTest.php`

- [ ] **Step 1: Create `composer.json`**

```json
{
    "name": "alovio/alovio-calculator",
    "description": "Alovio Calculator WordPress plugin",
    "license": "GPL-2.0-or-later",
    "require": { "php": ">=7.4" },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "brain/monkey": "^2.6"
    },
    "autoload": {
        "psr-4": { "Alovio\\Calculator\\": "includes/" }
    },
    "autoload-dev": {
        "psr-4": { "Alovio\\Calculator\\Tests\\": "tests/" }
    }
}
```

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="tests/bootstrap.php"
         colors="true"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create `tests/bootstrap.php`**

```php
<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' ); // Satisfies the guard in class files; WP itself is never loaded in unit tests.
}
```

- [ ] **Step 4: Create `tests/TestCase.php`** (Brain Monkey base). NOTE: CF differs in two known ways — its layout is `tests/php/Unit` and its TestCase pre-stubs common WP functions in `setUp()`. **The inlined files below are authoritative**: we use `tests/Unit` (matching this plan's composer.json + phpunit.xml.dist) and a bare Brain Monkey base; each test stubs exactly the WP functions it needs (later chunks' tests all do this explicitly).

```php
<?php
namespace Alovio\Calculator\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
```

- [ ] **Step 5: Create `tests/Unit/SmokeTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit;

use Alovio\Calculator\Tests\TestCase;

class SmokeTest extends TestCase {

	public function test_harness_boots(): void {
		$this->assertTrue( true );
	}
}
```

- [ ] **Step 6: Install and run**

Run: `composer install && vendor/bin/phpunit`
Expected: `OK (1 test, 1 assertion)`

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/bootstrap.php tests/TestCase.php tests/Unit/SmokeTest.php
git commit -m "test: PHPUnit + Brain Monkey harness with smoke test"
```

### Task 4: JS toolchain (wp-scripts: build + Jest)

**Files:**
- Create: `package.json`, `webpack.config.js`, `src/index.js`, `src/frontend.js`, `src/shared/formula/__tests__/smoke.test.js`

- [ ] **Step 1: Inspect the known-working sibling toolchain**

Run: `cat /Users/tahir/woo-checkout-fields/package.json && cat /Users/tahir/woo-checkout-fields/webpack.config.js`
Note the exact `@wordpress/scripts` version and webpack entry structure — reuse both verbatim below (only names change).

- [ ] **Step 2: Create `package.json`** (pin `@wordpress/scripts` to the same version CF uses; the snippet below shows the required scripts/shape)

```json
{
    "name": "alovio-calculator",
    "version": "0.1.0",
    "private": true,
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start",
        "test": "wp-scripts test-unit-js"
    },
    "devDependencies": {
        "@wordpress/scripts": "<SAME-MAJOR-AS-CF>"
    }
}
```

Replace `<SAME-MAJOR-AS-CF>` with the literal version string read in Step 1 — do not guess a version.

- [ ] **Step 3: Create `webpack.config.js`** (same pattern as CF: extend default config with two entries)

```js
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/index.js',
		frontend: './src/frontend.js',
	},
};
```

- [ ] **Step 4: Create placeholder entries** — `src/index.js` and `src/frontend.js`, each containing only:

```js
// Populated in later chunks.
export {};
```

- [ ] **Step 5: Create `src/shared/formula/__tests__/smoke.test.js`**

```js
describe( 'jest harness', () => {
	it( 'runs', () => {
		expect( 1 + 1 ).toBe( 2 );
	} );
} );
```

- [ ] **Step 6: Install, build, test**

Run: `npm install && npm run build && npm test`
Expected: build emits `build/index.js` + `build/frontend.js` (with `.asset.php` files); Jest reports `1 passed`.

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json webpack.config.js src/index.js src/frontend.js src/shared/formula/__tests__/smoke.test.js
git commit -m "chore: wp-scripts toolchain (webpack entries + jest)"
```

---

## Chunk 2: Formula Engine — PHP

Implements §7 of the spec. All classes live in `includes/Formula/`, namespace `Alovio\Calculator\Formula`, **zero WordPress dependencies**. Numbers are scale-4 integers (`12.3` → `123000`). One amendment to the spec is recorded in Task 5.

**Public API (consumed by every later chunk):**

```php
DecimalMath::toScaled( $v ): int;  DecimalMath::fromScaled( int $x ): string;
DecimalMath::add|sub|mul|div( int $a, int $b ): int;
DecimalMath::roundToDecimals( int $x, int $n ): int;  ceilToInt|floorToInt( int $x ): int;
Formula::compile( string $expr ): array /* AST */;     // throws FormulaError
Formula::evaluate( array $ast, array $scaledValues ): int; // throws FormulaError
Formula::references( array $ast ): string[];           // field ids used
FormulaGraph::order( array $idToRefs ): array{order: string[], cycles: string[]};
```

### Task 5: Amend spec overflow guard (±10⁹), commit

**Files:**
- Modify: `docs/superpowers/specs/2026-06-12-alovio-calculator-mvp-design.md` (§7 decimal-safety bullet)

- [ ] **Step 1: Edit the spec** — in §7, replace `overflow guard at ±9×10¹³ (beyond any real quote)` with:

```
overflow guard at ±999,999,999.9999 (±10⁹ — covers even IDR/VND-denominated quotes; chosen so every intermediate fits both PHP int64 and JS safe-integer arithmetic given the scale-4 representation and the mul/div decomposition below)
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-06-12-alovio-calculator-mvp-design.md
git commit -m "docs: tighten formula overflow guard to ±1e9 for int64/JS-safe arithmetic"
```

### Task 6: `FormulaError` + `DecimalMath`

**Files:**
- Create: `includes/Formula/FormulaError.php`, `includes/Formula/DecimalMath.php`
- Test: `tests/Unit/Formula/DecimalMathTest.php`

- [ ] **Step 1: Create `includes/Formula/FormulaError.php`**

```php
<?php
namespace Alovio\Calculator\Formula;

class FormulaError extends \RuntimeException {

	/** @var string One of: syntax, unknown_function, arity, unknown_field, div_zero, overflow, bad_number */
	private $errorCode;

	/** @var int Character position in the expression, -1 when not applicable. */
	private $position;

	public function __construct( string $errorCode, string $message, int $position = -1 ) {
		parent::__construct( $message );
		$this->errorCode = $errorCode;
		$this->position  = $position;
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	public function getPosition(): int {
		return $this->position;
	}
}
```

- [ ] **Step 2: Write the failing test `tests/Unit/Formula/DecimalMathTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\FormulaError;
use PHPUnit\Framework\TestCase;

class DecimalMathTest extends TestCase {

	public function test_to_scaled_rounds_at_conversion_boundary(): void {
		// Naive (int) (4.1 * 10000) yields 40999 — the artifact class the engine kills.
		$this->assertSame( 41000, DecimalMath::toScaled( 4.1 ) );
		$this->assertSame( 41000, DecimalMath::toScaled( '4.1' ) );
		$this->assertSame( -41000, DecimalMath::toScaled( -4.1 ) );
		$this->assertSame( 0, DecimalMath::toScaled( 0 ) );
	}

	public function test_to_scaled_rejects_non_numeric_and_non_finite(): void {
		$this->expectException( FormulaError::class );
		DecimalMath::toScaled( 'abc' );
	}

	public function test_add_sub_are_exact(): void {
		// 0.1 + 0.2 === 0.3 exactly — the marquee case.
		$a = DecimalMath::toScaled( '0.1' );
		$b = DecimalMath::toScaled( '0.2' );
		$this->assertSame( '0.3', DecimalMath::fromScaled( DecimalMath::add( $a, $b ) ) );
		$this->assertSame( '-0.1', DecimalMath::fromScaled( DecimalMath::sub( $a, $b ) ) );
	}

	public function test_mul_rescales_with_half_away_rounding(): void {
		$this->assertSame( '0.02', DecimalMath::fromScaled( DecimalMath::mul( 1000, 2000 ) ) );      // 0.1*0.2
		$this->assertSame( '12.3', DecimalMath::fromScaled( DecimalMath::mul( 41000, 30000 ) ) );    // 4.1*3
		$this->assertSame( '-12.3', DecimalMath::fromScaled( DecimalMath::mul( -41000, 30000 ) ) );
		// Half-away at the 4th decimal: 0.0001 * 0.5 = 0.00005 → rounds to 0.0001 (away from zero).
		$this->assertSame( 1, DecimalMath::mul( 1, 5000 ) );
		$this->assertSame( -1, DecimalMath::mul( -1, 5000 ) );
	}

	public function test_div_rescales_with_half_away_rounding(): void {
		$this->assertSame( '3.3333', DecimalMath::fromScaled( DecimalMath::div( 100000, 30000 ) ) ); // 10/3
		$this->assertSame( '-3.3333', DecimalMath::fromScaled( DecimalMath::div( -100000, 30000 ) ) );
		$this->assertSame( '0.5', DecimalMath::fromScaled( DecimalMath::div( 10000, 20000 ) ) );
	}

	public function test_div_by_zero_throws(): void {
		$this->expectException( FormulaError::class );
		try {
			DecimalMath::div( 10000, 0 );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'div_zero', $e->getErrorCode() );
			throw $e;
		}
	}

	public function test_overflow_guard(): void {
		$big = DecimalMath::toScaled( '999999999' );
		$this->expectException( FormulaError::class );
		try {
			DecimalMath::mul( $big, $big );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'overflow', $e->getErrorCode() );
			throw $e;
		}
	}

	public function test_round_to_decimals_half_away_from_zero(): void {
		$this->assertSame( 30000, DecimalMath::roundToDecimals( 25000, 0 ) );   // 2.5  → 3
		$this->assertSame( -30000, DecimalMath::roundToDecimals( -25000, 0 ) ); // -2.5 → -3 (NOT -2)
		$this->assertSame( 12350, DecimalMath::roundToDecimals( 12345, 2 ) );   // 1.2345 → 1.23|45 → 1.235? No: n=2 → 1.23
	}

	public function test_round_examples_pinned(): void {
		$this->assertSame( '1.23', DecimalMath::fromScaled( DecimalMath::roundToDecimals( DecimalMath::toScaled( '1.2345' ), 2 ) ) );
		$this->assertSame( '1.24', DecimalMath::fromScaled( DecimalMath::roundToDecimals( DecimalMath::toScaled( '1.235' ), 2 ) ) );
	}

	public function test_ceil_floor_to_int(): void {
		$this->assertSame( '3', DecimalMath::fromScaled( DecimalMath::ceilToInt( DecimalMath::toScaled( '2.1' ) ) ) );
		$this->assertSame( '2', DecimalMath::fromScaled( DecimalMath::floorToInt( DecimalMath::toScaled( '2.9' ) ) ) );
		$this->assertSame( '-2', DecimalMath::fromScaled( DecimalMath::ceilToInt( DecimalMath::toScaled( '-2.5' ) ) ) );
		$this->assertSame( '-3', DecimalMath::fromScaled( DecimalMath::floorToInt( DecimalMath::toScaled( '-2.5' ) ) ) );
	}

	public function test_from_scaled_trims_trailing_zeros(): void {
		$this->assertSame( '12', DecimalMath::fromScaled( 120000 ) );
		$this->assertSame( '12.5', DecimalMath::fromScaled( 125000 ) );
		$this->assertSame( '0.0001', DecimalMath::fromScaled( 1 ) );
		$this->assertSame( '-12.5', DecimalMath::fromScaled( -125000 ) );
	}
}
```

NOTE: in `test_round_to_decimals_half_away_from_zero`, fix the third assertion before running — `roundToDecimals(12345, 2)` must equal `12300` (1.2345 → 1.23; the half-case is covered by `test_round_examples_pinned`). The deliberate first draft above contains that wrong expectation so you exercise reading the failure; correct it to `12300`.

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/phpunit --filter DecimalMathTest`
Expected: FAIL — `Class "Alovio\Calculator\Formula\DecimalMath" not found`.

- [ ] **Step 4: Create `includes/Formula/DecimalMath.php`**

```php
<?php
namespace Alovio\Calculator\Formula;

/**
 * Exact scale-4 fixed-point arithmetic on integers.
 * 12.3 is represented as 123000. Range guard: ±999,999,999.9999 (±10⁹),
 * chosen so every intermediate below fits PHP int64 AND mirrors safely
 * into JS (the JS twin uses BigInt for the same decompositions).
 * Rounding everywhere: half away from zero.
 */
final class DecimalMath {

	public const SCALE = 10000;

	/** 999,999,999.9999 scaled. */
	public const MAX_SCALED = 9999999999999;

	/** @param int|float|string $v */
	public static function toScaled( $v ): int {
		if ( ! is_numeric( $v ) ) {
			throw new FormulaError( 'bad_number', 'Not a number: ' . (string) $v );
		}
		$f = (float) $v;
		if ( ! is_finite( $f ) ) {
			throw new FormulaError( 'bad_number', 'Not a finite number' );
		}
		$sign   = $f < 0 ? -1 : 1;
		$scaled = (int) round( abs( $f ) * self::SCALE ); // Round AT the boundary (§7).
		self::guard( $scaled );
		return $sign * $scaled;
	}

	public static function fromScaled( int $x ): string {
		$sign = $x < 0 ? '-' : '';
		$x    = abs( $x );
		$int  = intdiv( $x, self::SCALE );
		$frac = rtrim( str_pad( (string) ( $x % self::SCALE ), 4, '0', STR_PAD_LEFT ), '0' );
		return $sign . $int . ( '' === $frac ? '' : '.' . $frac );
	}

	public static function add( int $a, int $b ): int {
		$r = $a + $b;
		self::guard( abs( $r ) );
		return $r;
	}

	public static function sub( int $a, int $b ): int {
		return self::add( $a, -$b );
	}

	public static function mul( int $a, int $b ): int {
		// Magnitude pre-check in float space (floats are reliable for order-of-magnitude checks).
		$approx = ( $a / self::SCALE ) * ( $b / self::SCALE );
		if ( abs( $approx ) > ( self::MAX_SCALED / self::SCALE ) + 1 ) {
			throw new FormulaError( 'overflow', 'Multiplication overflow' );
		}
		$sign = ( ( $a < 0 ) xor ( $b < 0 ) ) ? -1 : 1;
		$a    = abs( $a );
		$b    = abs( $b );
		// a*b/SCALE decomposed so no intermediate exceeds ~1e17 (< PHP_INT_MAX 9.2e18):
		// b = q*SCALE + r  ⇒  a*b/SCALE = a*q + a*r/SCALE.
		$q = intdiv( $b, self::SCALE );
		$r = $b % self::SCALE;
		$result = $a * $q + self::divRound( $a * $r, self::SCALE );
		self::guard( $result );
		return $sign * $result;
	}

	public static function div( int $a, int $b ): int {
		if ( 0 === $b ) {
			throw new FormulaError( 'div_zero', 'Division by zero' );
		}
		$approx = $a / $b; // Unscaled ratio == scaled-result/SCALE ratio.
		if ( abs( $approx ) > ( self::MAX_SCALED / self::SCALE ) + 1 ) {
			throw new FormulaError( 'overflow', 'Division overflow' );
		}
		$sign = ( ( $a < 0 ) xor ( $b < 0 ) ) ? -1 : 1;
		// a*SCALE ≤ 1e13*1e4 = 1e17 — safe in int64.
		$result = self::divRound( abs( $a ) * self::SCALE, abs( $b ) );
		self::guard( $result );
		return $sign * $result;
	}

	/** @param int $n decimal places 0..4 (clamped). */
	public static function roundToDecimals( int $x, int $n ): int {
		$n = max( 0, min( 4, $n ) );
		$f = (int) ( 10 ** ( 4 - $n ) );
		$sign = $x < 0 ? -1 : 1;
		return $sign * self::divRound( abs( $x ), $f ) * $f;
	}

	public static function ceilToInt( int $x ): int {
		$q = intdiv( $x, self::SCALE );
		$r = $x - $q * self::SCALE;
		if ( $r > 0 ) {
			$q++;
		}
		$result = $q * self::SCALE;
		self::guard( abs( $result ) );
		return $result;
	}

	public static function floorToInt( int $x ): int {
		$q = intdiv( $x, self::SCALE );
		$r = $x - $q * self::SCALE;
		if ( $r < 0 ) {
			$q--;
		}
		$result = $q * self::SCALE;
		self::guard( abs( $result ) );
		return $result;
	}

	/** Integer division n/d (n ≥ 0, d > 0), half away from zero. */
	private static function divRound( int $n, int $d ): int {
		$q = intdiv( $n, $d );
		$r = $n - $q * $d;
		if ( 2 * $r >= $d ) {
			$q++;
		}
		return $q;
	}

	private static function guard( int $absScaled ): void {
		if ( $absScaled > self::MAX_SCALED ) {
			throw new FormulaError( 'overflow', 'Value exceeds supported range (±999,999,999.9999)' );
		}
	}
}
```

- [ ] **Step 5: Run tests to verify pass**

Run: `vendor/bin/phpunit --filter DecimalMathTest`
Expected: PASS (all assertions).

- [ ] **Step 6: Commit**

```bash
git add includes/Formula/FormulaError.php includes/Formula/DecimalMath.php tests/Unit/Formula/DecimalMathTest.php
git commit -m "feat: decimal-safe scale-4 math core with overflow guard"
```

### Task 7: `Lexer`

**Files:**
- Create: `includes/Formula/Lexer.php`
- Test: `tests/Unit/Formula/LexerTest.php`

Token = `[ 'type' => <num|field|ident|op|cmp|lparen|rparen|comma>, 'value' => string, 'pos' => int ]`.

- [ ] **Step 1: Write the failing test `tests/Unit/Formula/LexerTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\Lexer;
use PHPUnit\Framework\TestCase;

class LexerTest extends TestCase {

	private function types( string $expr ): array {
		return array_map( static fn( $t ) => $t['type'], Lexer::tokenize( $expr ) );
	}

	public function test_numbers_fields_ops(): void {
		$tokens = Lexer::tokenize( '{area} * 2.5 + 10' );
		$this->assertSame(
			[ 'field', 'op', 'num', 'op', 'num' ],
			array_map( static fn( $t ) => $t['type'], $tokens )
		);
		$this->assertSame( 'area', $tokens[0]['value'] );
		$this->assertSame( '2.5', $tokens[2]['value'] );
		$this->assertSame( 0, $tokens[0]['pos'] );
	}

	public function test_function_call_tokens(): void {
		$this->assertSame(
			[ 'ident', 'lparen', 'field', 'cmp', 'num', 'comma', 'num', 'comma', 'num', 'rparen' ],
			$this->types( 'if({qty} >= 10, 5, 0)' )
		);
	}

	public function test_all_comparison_operators(): void {
		foreach ( [ '>', '<', '>=', '<=', '==', '!=' ] as $cmp ) {
			$tokens = Lexer::tokenize( "1 {$cmp} 2" );
			$this->assertSame( 'cmp', $tokens[1]['type'] );
			$this->assertSame( $cmp, $tokens[1]['value'] );
		}
	}

	public function test_field_id_charset(): void {
		$tokens = Lexer::tokenize( '{opt_7f3a}' );
		$this->assertSame( 'opt_7f3a', $tokens[0]['value'] );
	}

	public function test_unknown_char_throws_with_position(): void {
		try {
			Lexer::tokenize( '1 + $x' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'syntax', $e->getErrorCode() );
			$this->assertSame( 4, $e->getPosition() );
		}
	}

	public function test_unterminated_field_throws(): void {
		$this->expectException( FormulaError::class );
		Lexer::tokenize( '{area' );
	}

	public function test_malformed_number_throws(): void {
		$this->expectException( FormulaError::class );
		Lexer::tokenize( '1.2.3' );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter LexerTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `includes/Formula/Lexer.php`**

```php
<?php
namespace Alovio\Calculator\Formula;

final class Lexer {

	/** @return array<int, array{type: string, value: string, pos: int}> */
	public static function tokenize( string $expr ): array {
		$tokens = [];
		$len    = strlen( $expr );
		$i      = 0;

		while ( $i < $len ) {
			$c = $expr[ $i ];

			if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c ) {
				$i++;
				continue;
			}

			if ( '{' === $c ) {
				if ( ! preg_match( '/\{([a-z0-9_]+)\}/A', $expr, $m, 0, $i ) ) {
					throw new FormulaError( 'syntax', 'Malformed field reference', $i );
				}
				$tokens[] = [ 'type' => 'field', 'value' => $m[1], 'pos' => $i ];
				$i       += strlen( $m[0] );
				continue;
			}

			if ( preg_match( '/[0-9]/', $c ) ) {
				preg_match( '/[0-9]+(\.[0-9]+)?/A', $expr, $m, 0, $i );
				$end = $i + strlen( $m[0] );
				if ( $end < $len && ( '.' === $expr[ $end ] || preg_match( '/[0-9a-z_]/i', $expr[ $end ] ) ) ) {
					throw new FormulaError( 'syntax', 'Malformed number', $i );
				}
				$tokens[] = [ 'type' => 'num', 'value' => $m[0], 'pos' => $i ];
				$i        = $end;
				continue;
			}

			if ( preg_match( '/[a-z_]/i', $c ) ) {
				preg_match( '/[a-z_][a-z0-9_]*/Ai', $expr, $m, 0, $i );
				$tokens[] = [ 'type' => 'ident', 'value' => strtolower( $m[0] ), 'pos' => $i ];
				$i       += strlen( $m[0] );
				continue;
			}

			$two = substr( $expr, $i, 2 );
			if ( in_array( $two, [ '>=', '<=', '==', '!=' ], true ) ) {
				$tokens[] = [ 'type' => 'cmp', 'value' => $two, 'pos' => $i ];
				$i       += 2;
				continue;
			}
			if ( '>' === $c || '<' === $c ) {
				$tokens[] = [ 'type' => 'cmp', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( '+' === $c || '-' === $c || '*' === $c || '/' === $c ) {
				$tokens[] = [ 'type' => 'op', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( '(' === $c ) {
				$tokens[] = [ 'type' => 'lparen', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( ')' === $c ) {
				$tokens[] = [ 'type' => 'rparen', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( ',' === $c ) {
				$tokens[] = [ 'type' => 'comma', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}

			throw new FormulaError( 'syntax', 'Unexpected character: ' . $c, $i );
		}

		return $tokens;
	}
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `vendor/bin/phpunit --filter LexerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/Formula/Lexer.php tests/Unit/Formula/LexerTest.php
git commit -m "feat: formula lexer with positioned syntax errors"
```

### Task 8: `Parser` (Pratt)

**Files:**
- Create: `includes/Formula/Parser.php`
- Test: `tests/Unit/Formula/ParserTest.php`

AST node shapes (plain arrays so they JSON-mirror to JS): `{type:'num', value:int-scaled}`, `{type:'field', id:string}`, `{type:'bin', op:'+|-|*|/', left, right}`, `{type:'cmp', op:'>|<|>=|<=|==|!=', left, right}`, `{type:'neg', operand}`, `{type:'call', name:string, args:[]}`.

Function arity table (the parser receives it; default lives in `Functions::SPECS`, Task 9): `if`:[3,3], `min`:[2,8], `max`:[2,8], `round`:[1,2], `ceil`:[1,1], `floor`:[1,1], `abs`:[1,1].

- [ ] **Step 1: Write the failing test `tests/Unit/Formula/ParserTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\Functions;
use Alovio\Calculator\Formula\Lexer;
use Alovio\Calculator\Formula\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {

	private function parse( string $expr ): array {
		return ( new Parser( Functions::SPECS ) )->parse( Lexer::tokenize( $expr ) );
	}

	public function test_precedence_mul_over_add(): void {
		$ast = $this->parse( '1 + 2 * 3' );
		$this->assertSame( 'bin', $ast['type'] );
		$this->assertSame( '+', $ast['op'] );
		$this->assertSame( '*', $ast['right']['op'] );
	}

	public function test_parens_override_precedence(): void {
		$ast = $this->parse( '(1 + 2) * 3' );
		$this->assertSame( '*', $ast['op'] );
		$this->assertSame( '+', $ast['left']['op'] );
	}

	public function test_unary_minus_binds_tighter_than_mul(): void {
		$ast = $this->parse( '-2 * 3' );
		$this->assertSame( '*', $ast['op'] );
		$this->assertSame( 'neg', $ast['left']['type'] );
	}

	public function test_numbers_are_scaled_at_parse_time(): void {
		$ast = $this->parse( '4.1' );
		$this->assertSame( [ 'type' => 'num', 'value' => 41000 ], $ast );
	}

	public function test_call_with_comparison_arg(): void {
		$ast = $this->parse( 'if({qty} >= 10, 5, 0)' );
		$this->assertSame( 'call', $ast['type'] );
		$this->assertSame( 'if', $ast['name'] );
		$this->assertCount( 3, $ast['args'] );
		$this->assertSame( 'cmp', $ast['args'][0]['type'] );
	}

	public function test_unknown_function_throws(): void {
		try {
			$this->parse( 'sqrt(4)' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'unknown_function', $e->getErrorCode() );
		}
	}

	public function test_arity_violation_throws(): void {
		try {
			$this->parse( 'if(1, 2)' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'arity', $e->getErrorCode() );
		}
	}

	public function test_trailing_garbage_throws(): void {
		$this->expectException( FormulaError::class );
		$this->parse( '1 2' );
	}

	public function test_empty_expression_throws(): void {
		$this->expectException( FormulaError::class );
		$this->parse( '   ' );
	}

	public function test_bare_ident_without_call_throws(): void {
		$this->expectException( FormulaError::class );
		$this->parse( 'min' );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter ParserTest`
Expected: FAIL — `Functions` / `Parser` not found.

- [ ] **Step 3: Create `includes/Formula/Functions.php`** (specs only; evaluation behavior is Task 9)

```php
<?php
namespace Alovio\Calculator\Formula;

final class Functions {

	/** name => [minArity, maxArity]. Filterable copy is exposed via Formula::functions() (Task 10). */
	public const SPECS = [
		'if'    => [ 3, 3 ],
		'min'   => [ 2, 8 ],
		'max'   => [ 2, 8 ],
		'round' => [ 1, 2 ],
		'ceil'  => [ 1, 1 ],
		'floor' => [ 1, 1 ],
		'abs'   => [ 1, 1 ],
	];
}
```

- [ ] **Step 4: Create `includes/Formula/Parser.php`**

```php
<?php
namespace Alovio\Calculator\Formula;

final class Parser {

	private const BP_CMP = 10;
	private const BP_ADD = 20;
	private const BP_MUL = 30;

	/** @var array<string, array{0:int,1:int}> */
	private $functions;

	/** @var array<int, array{type: string, value: string, pos: int}> */
	private $tokens = [];

	/** @var int */
	private $i = 0;

	public function __construct( array $functions ) {
		$this->functions = $functions;
	}

	public function parse( array $tokens ): array {
		$this->tokens = $tokens;
		$this->i      = 0;
		if ( empty( $tokens ) ) {
			throw new FormulaError( 'syntax', 'Empty expression' );
		}
		$ast = $this->expression( 0 );
		if ( null !== $this->peek() ) {
			throw new FormulaError( 'syntax', 'Unexpected token', $this->peek()['pos'] );
		}
		return $ast;
	}

	private function expression( int $minBp ): array {
		$left = $this->primary();

		while ( true ) {
			$t = $this->peek();
			if ( null === $t ) {
				break;
			}
			if ( 'op' === $t['type'] && ( '+' === $t['value'] || '-' === $t['value'] ) ) {
				$bp = self::BP_ADD;
				$node = 'bin';
			} elseif ( 'op' === $t['type'] ) {
				$bp = self::BP_MUL;
				$node = 'bin';
			} elseif ( 'cmp' === $t['type'] ) {
				$bp = self::BP_CMP;
				$node = 'cmp';
			} else {
				break;
			}
			if ( $bp < $minBp ) {
				break;
			}
			$this->next();
			$right = $this->expression( $bp + 1 ); // Left-associative.
			$left  = [ 'type' => $node, 'op' => $t['value'], 'left' => $left, 'right' => $right ];
		}

		return $left;
	}

	private function primary(): array {
		$t = $this->next();
		if ( null === $t ) {
			throw new FormulaError( 'syntax', 'Unexpected end of expression' );
		}

		switch ( $t['type'] ) {
			case 'num':
				return [ 'type' => 'num', 'value' => DecimalMath::toScaled( $t['value'] ) ];

			case 'field':
				return [ 'type' => 'field', 'id' => $t['value'] ];

			case 'op':
				if ( '-' === $t['value'] ) {
					return [ 'type' => 'neg', 'operand' => $this->expression( self::BP_MUL + 1 ) ];
				}
				break;

			case 'lparen':
				$inner = $this->expression( 0 );
				$this->expect( 'rparen', $t['pos'] );
				return $inner;

			case 'ident':
				if ( ! isset( $this->functions[ $t['value'] ] ) ) {
					throw new FormulaError( 'unknown_function', 'Unknown function: ' . $t['value'], $t['pos'] );
				}
				$this->expect( 'lparen', $t['pos'] );
				$args = [ $this->expression( 0 ) ];
				while ( null !== $this->peek() && 'comma' === $this->peek()['type'] ) {
					$this->next();
					$args[] = $this->expression( 0 );
				}
				$this->expect( 'rparen', $t['pos'] );
				[ $min, $max ] = $this->functions[ $t['value'] ];
				if ( count( $args ) < $min || count( $args ) > $max ) {
					throw new FormulaError( 'arity', sprintf( '%s() expects %d-%d arguments', $t['value'], $min, $max ), $t['pos'] );
				}
				return [ 'type' => 'call', 'name' => $t['value'], 'args' => $args ];
		}

		throw new FormulaError( 'syntax', 'Unexpected token', $t['pos'] );
	}

	private function peek(): ?array {
		return $this->tokens[ $this->i ] ?? null;
	}

	private function next(): ?array {
		$t = $this->peek();
		if ( null !== $t ) {
			$this->i++;
		}
		return $t;
	}

	private function expect( string $type, int $contextPos ): void {
		$t = $this->next();
		if ( null === $t || $type !== $t['type'] ) {
			throw new FormulaError( 'syntax', 'Expected ' . $type, null === $t ? $contextPos : $t['pos'] );
		}
	}
}
```

- [ ] **Step 5: Run tests to verify pass**

Run: `vendor/bin/phpunit --filter ParserTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Formula/Functions.php includes/Formula/Parser.php tests/Unit/Formula/ParserTest.php
git commit -m "feat: Pratt parser producing JSON-mirrorable AST"
```

---

## Chunk 2b: Formula Engine — PHP (Evaluation, Facade, Fixtures)

(Continuation of Chunk 2 — split only to keep review chunks under 1000 lines; same rules apply.)

### Task 9: `Evaluator`

**Files:**
- Create: `includes/Formula/Evaluator.php`
- Test: `tests/Unit/Formula/EvaluatorTest.php`

- [ ] **Step 1: Write the failing test `tests/Unit/Formula/EvaluatorTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\Evaluator;
use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\Functions;
use Alovio\Calculator\Formula\Lexer;
use Alovio\Calculator\Formula\Parser;
use PHPUnit\Framework\TestCase;

class EvaluatorTest extends TestCase {

	private function evaluate( string $expr, array $values = [] ): string {
		$scaled = [];
		foreach ( $values as $id => $v ) {
			$scaled[ $id ] = DecimalMath::toScaled( $v );
		}
		$ast = ( new Parser( Functions::SPECS ) )->parse( Lexer::tokenize( $expr ) );
		return DecimalMath::fromScaled( ( new Evaluator( Functions::SPECS ) )->evaluate( $ast, $scaled ) );
	}

	public function test_arithmetic_with_fields(): void {
		$this->assertSame( '12.3', $this->evaluate( '{a} * {b}', [ 'a' => '4.1', 'b' => '3' ] ) );
		$this->assertSame( '0.3', $this->evaluate( '{a} + {b}', [ 'a' => '0.1', 'b' => '0.2' ] ) );
	}

	public function test_if_with_comparisons_both_branches(): void {
		$this->assertSame( '5', $this->evaluate( 'if({qty} >= 10, 5, 0)', [ 'qty' => '10' ] ) );
		$this->assertSame( '0', $this->evaluate( 'if({qty} >= 10, 5, 0)', [ 'qty' => '9.9999' ] ) );
		$this->assertSame( '1', $this->evaluate( 'if({a} != {b}, 1, 2)', [ 'a' => '1', 'b' => '2' ] ) );
	}

	public function test_if_is_lazy_untaken_branch_not_evaluated(): void {
		// Untaken branch divides by zero — must NOT throw.
		$this->assertSame( '7', $this->evaluate( 'if(1 == 1, 7, 1 / 0)' ) );
	}

	public function test_functions(): void {
		$this->assertSame( '2', $this->evaluate( 'min(5, 2, 8)' ) );
		$this->assertSame( '8', $this->evaluate( 'max(5, 2, 8)' ) );
		$this->assertSame( '3', $this->evaluate( 'round(2.5)' ) );
		$this->assertSame( '-3', $this->evaluate( 'round(-2.5)' ) );
		$this->assertSame( '1.24', $this->evaluate( 'round(1.235, 2)' ) );
		$this->assertSame( '3', $this->evaluate( 'ceil(2.1)' ) );
		$this->assertSame( '2', $this->evaluate( 'floor(2.9)' ) );
		$this->assertSame( '2.5', $this->evaluate( 'abs(-2.5)' ) );
	}

	public function test_unknown_field_throws(): void {
		try {
			$this->evaluate( '{ghost} + 1' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'unknown_field', $e->getErrorCode() );
		}
	}

	public function test_division_by_zero_propagates(): void {
		$this->expectException( FormulaError::class );
		$this->evaluate( '1 / {z}', [ 'z' => '0' ] );
	}

	public function test_comparison_result_is_numeric_one_or_zero(): void {
		$this->assertSame( '1', $this->evaluate( '2 > 1' ) );
		$this->assertSame( '0', $this->evaluate( '2 < 1' ) );
	}

	public function test_round_second_arg_is_truncated_to_int_decimals(): void {
		$this->assertSame( '1.24', $this->evaluate( 'round(1.235, 2.9)' ) ); // n = 2
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter EvaluatorTest`
Expected: FAIL — `Evaluator` not found.

- [ ] **Step 3: Create `includes/Formula/Evaluator.php`**

```php
<?php
namespace Alovio\Calculator\Formula;

final class Evaluator {

	/** @var array<string, array{0:int,1:int}> */
	private $functions;

	public function __construct( array $functions ) {
		$this->functions = $functions;
	}

	/**
	 * @param array $ast    Node from Parser.
	 * @param array $values Map field-id => scaled int. Callers must pre-resolve
	 *                      inactive fields to 0 (spec §6/§8).
	 */
	public function evaluate( array $ast, array $values ): int {
		switch ( $ast['type'] ) {
			case 'num':
				return $ast['value'];

			case 'field':
				if ( ! array_key_exists( $ast['id'], $values ) ) {
					throw new FormulaError( 'unknown_field', 'Unknown field: ' . $ast['id'] );
				}
				return $values[ $ast['id'] ];

			case 'neg':
				return -$this->evaluate( $ast['operand'], $values );

			case 'bin':
				$l = $this->evaluate( $ast['left'], $values );
				$r = $this->evaluate( $ast['right'], $values );
				switch ( $ast['op'] ) {
					case '+':
						return DecimalMath::add( $l, $r );
					case '-':
						return DecimalMath::sub( $l, $r );
					case '*':
						return DecimalMath::mul( $l, $r );
					case '/':
						return DecimalMath::div( $l, $r );
				}
				break;

			case 'cmp':
				$l = $this->evaluate( $ast['left'], $values );
				$r = $this->evaluate( $ast['right'], $values );
				$bool = false;
				switch ( $ast['op'] ) {
					case '>':
						$bool = $l > $r;
						break;
					case '<':
						$bool = $l < $r;
						break;
					case '>=':
						$bool = $l >= $r;
						break;
					case '<=':
						$bool = $l <= $r;
						break;
					case '==':
						$bool = $l === $r;
						break;
					case '!=':
						$bool = $l !== $r;
						break;
				}
				return $bool ? DecimalMath::SCALE : 0;

			case 'call':
				return $this->call( $ast['name'], $ast['args'], $values );
		}

		throw new FormulaError( 'syntax', 'Malformed AST node' );
	}

	private function call( string $name, array $args, array $values ): int {
		if ( ! isset( $this->functions[ $name ] ) ) {
			throw new FormulaError( 'unknown_function', 'Unknown function: ' . $name );
		}

		if ( 'if' === $name ) { // Lazy: only the taken branch is evaluated.
			$cond = $this->evaluate( $args[0], $values );
			return $this->evaluate( 0 !== $cond ? $args[1] : $args[2], $values );
		}

		$vals = array_map( fn( $a ) => $this->evaluate( $a, $values ), $args );

		switch ( $name ) {
			case 'min':
				return min( $vals );
			case 'max':
				return max( $vals );
			case 'round':
				$n = isset( $vals[1] ) ? intdiv( $vals[1], DecimalMath::SCALE ) : 0;
				return DecimalMath::roundToDecimals( $vals[0], $n );
			case 'ceil':
				return DecimalMath::ceilToInt( $vals[0] );
			case 'floor':
				return DecimalMath::floorToInt( $vals[0] );
			case 'abs':
				return abs( $vals[0] );
		}

		throw new FormulaError( 'unknown_function', 'No evaluator for function: ' . $name );
	}
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `vendor/bin/phpunit --filter EvaluatorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/Formula/Evaluator.php tests/Unit/Formula/EvaluatorTest.php
git commit -m "feat: AST evaluator with lazy if() and decimal-safe ops"
```

### Task 10: `Formula` facade + `FormulaGraph` (references, topo order)

**Files:**
- Create: `includes/Formula/Formula.php`, `includes/Formula/FormulaGraph.php`
- Test: `tests/Unit/Formula/FormulaGraphTest.php`, `tests/Unit/Formula/FormulaFacadeTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Formula/FormulaFacadeTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\Formula;
use PHPUnit\Framework\TestCase;

class FormulaFacadeTest extends TestCase {

	public function test_compile_evaluate_roundtrip(): void {
		$ast = Formula::compile( '{a} * 2 + if({b} > 0, 1, 0)' );
		$this->assertSame( 90000 + 10000, Formula::evaluate( $ast, [ 'a' => 45000, 'b' => 10000 ] ) );
	}

	public function test_references_collects_unique_field_ids(): void {
		$ast = Formula::compile( '{a} + {b} * if({a} > 1, {c}, 2)' );
		$this->assertSame( [ 'a', 'b', 'c' ], Formula::references( $ast ) );
	}
}
```

`tests/Unit/Formula/FormulaGraphTest.php`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\FormulaGraph;
use PHPUnit\Framework\TestCase;

class FormulaGraphTest extends TestCase {

	public function test_orders_dependencies_before_dependents(): void {
		$result = FormulaGraph::order(
			[
				'total'    => [ 'subtotal' ],
				'subtotal' => [],
				'tax'      => [ 'subtotal' ],
				'grand'    => [ 'total', 'tax' ],
			]
		);
		$this->assertSame( [], $result['cycles'] );
		$order = $result['order'];
		$this->assertLessThan( array_search( 'total', $order, true ), array_search( 'subtotal', $order, true ) );
		$this->assertLessThan( array_search( 'grand', $order, true ), array_search( 'tax', $order, true ) );
		$this->assertCount( 4, $order );
	}

	public function test_detects_cycles(): void {
		$result = FormulaGraph::order(
			[
				'a' => [ 'b' ],
				'b' => [ 'a' ],
				'c' => [],
			]
		);
		$this->assertSame( [ 'c' ], $result['order'] );
		$this->assertEqualsCanonicalizing( [ 'a', 'b' ], $result['cycles'] );
	}

	public function test_ignores_refs_to_non_formula_fields(): void {
		$result = FormulaGraph::order( [ 'total' => [ 'qty', 'price' ] ] ); // qty/price are inputs, not keys.
		$this->assertSame( [ 'total' ], $result['order'] );
		$this->assertSame( [], $result['cycles'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter 'FormulaGraphTest|FormulaFacadeTest'`
Expected: FAIL — classes not found.

- [ ] **Step 3: Create `includes/Formula/Formula.php`**

```php
<?php
namespace Alovio\Calculator\Formula;

final class Formula {

	public static function compile( string $expr ): array {
		return ( new Parser( self::functions() ) )->parse( Lexer::tokenize( $expr ) );
	}

	public static function evaluate( array $ast, array $scaledValues ): int {
		return ( new Evaluator( self::functions() ) )->evaluate( $ast, $scaledValues );
	}

	/** @return string[] Unique field ids in first-seen order. */
	public static function references( array $ast ): array {
		$refs = [];
		self::walk( $ast, $refs );
		return array_values( array_unique( $refs ) );
	}

	/** Filterable in WP context (§15: alc_formula_functions); plain default in unit tests. */
	public static function functions(): array {
		$fns = Functions::SPECS;
		if ( function_exists( 'apply_filters' ) ) {
			$fns = apply_filters( 'alc_formula_functions', $fns );
		}
		return $fns;
	}

	private static function walk( array $node, array &$refs ): void {
		switch ( $node['type'] ) {
			case 'field':
				$refs[] = $node['id'];
				return;
			case 'neg':
				self::walk( $node['operand'], $refs );
				return;
			case 'bin':
			case 'cmp':
				self::walk( $node['left'], $refs );
				self::walk( $node['right'], $refs );
				return;
			case 'call':
				foreach ( $node['args'] as $arg ) {
					self::walk( $arg, $refs );
				}
				return;
		}
	}
}
```

NOTE for the future Pro add-on (not MVP work): the `alc_formula_functions` filter extends what the *parser* accepts, but `Evaluator::call()` has no dispatch for unknown names (they fall through to `unknown_function` → safe 0). The Pro add-on will need an evaluation-callback mechanism, not just the filter.

- [ ] **Step 4: Create `includes/Formula/FormulaGraph.php`** (Kahn's algorithm)

```php
<?php
namespace Alovio\Calculator\Formula;

final class FormulaGraph {

	/**
	 * @param array<string, string[]> $idToRefs Formula field id => referenced field ids.
	 *                                          Refs that are not formula ids are ignored.
	 * @return array{order: string[], cycles: string[]}
	 */
	public static function order( array $idToRefs ): array {
		$ids      = array_keys( $idToRefs );
		$idSet    = array_flip( $ids );
		$indegree = array_fill_keys( $ids, 0 );
		$edges    = []; // dependency => dependents[]

		foreach ( $idToRefs as $id => $refs ) {
			foreach ( array_unique( $refs ) as $ref ) {
				if ( isset( $idSet[ $ref ] ) && $ref !== $id ) {
					$edges[ $ref ][] = $id;
					$indegree[ $id ]++;
				}
				if ( $ref === $id ) { // Self-reference is a cycle of one.
					$indegree[ $id ]++;
				}
			}
		}

		$queue = [];
		foreach ( $indegree as $id => $deg ) {
			if ( 0 === $deg ) {
				$queue[] = $id;
			}
		}

		$order = [];
		while ( $queue ) {
			$id      = array_shift( $queue );
			$order[] = $id;
			foreach ( $edges[ $id ] ?? [] as $dependent ) {
				if ( 0 === --$indegree[ $dependent ] ) {
					$queue[] = $dependent;
				}
			}
		}

		$cycles = array_values( array_diff( $ids, $order ) );

		return [ 'order' => $order, 'cycles' => $cycles ];
	}
}
```

- [ ] **Step 5: Run tests to verify pass**

Run: `vendor/bin/phpunit --filter 'FormulaGraphTest|FormulaFacadeTest'`
Expected: PASS. Then run the full suite: `vendor/bin/phpunit` — all green.

- [ ] **Step 6: Commit**

```bash
git add includes/Formula/Formula.php includes/Formula/FormulaGraph.php tests/Unit/Formula/FormulaFacadeTest.php tests/Unit/Formula/FormulaGraphTest.php
git commit -m "feat: formula facade and dependency graph with cycle detection"
```

### Task 11: Shared parity fixtures + PHP fixture runner

**Files:**
- Create: `tests/fixtures/formula-cases.json`
- Test: `tests/Unit/Formula/FormulaCasesTest.php`

These fixtures are THE parity contract — Jest consumes the same file in Chunk 3. `values` and `expected` are decimal strings; error cases use `{"error": "<code>"}`.

- [ ] **Step 1: Create `tests/fixtures/formula-cases.json`**

```json
{
    "cases": [
        { "name": "integer addition", "expression": "1 + 2", "values": {}, "expected": "3" },
        { "name": "float artifact killer", "expression": "0.1 + 0.2", "values": {}, "expected": "0.3" },
        { "name": "boundary rounding 4.1", "expression": "{a} * 3", "values": { "a": "4.1" }, "expected": "12.3" },
        { "name": "precedence", "expression": "1 + 2 * 3", "values": {}, "expected": "7" },
        { "name": "parens", "expression": "(1 + 2) * 3", "values": {}, "expected": "9" },
        { "name": "unary minus", "expression": "-2 * 3 + 10", "values": {}, "expected": "4" },
        { "name": "division rounding", "expression": "10 / 3", "values": {}, "expected": "3.3333" },
        { "name": "negative division rounding", "expression": "-10 / 3", "values": {}, "expected": "-3.3333" },
        { "name": "field refs", "expression": "{area} * {rate} + {express}", "values": { "area": "50", "rate": "2.5", "express": "50" }, "expected": "175" },
        { "name": "if true branch", "expression": "if({qty} >= 10, {qty} * 0.9, {qty})", "values": { "qty": "10" }, "expected": "9" },
        { "name": "if false branch", "expression": "if({qty} >= 10, {qty} * 0.9, {qty})", "values": { "qty": "9" }, "expected": "9" },
        { "name": "if lazy untaken division by zero", "expression": "if(1 == 1, 7, 1 / 0)", "values": {}, "expected": "7" },
        { "name": "nested calls", "expression": "max(min({a}, 100), if({a} > 50, 50, 0))", "values": { "a": "75" }, "expected": "75" },
        { "name": "round default", "expression": "round(2.5)", "values": {}, "expected": "3" },
        { "name": "round negative half away from zero", "expression": "round(-2.5)", "values": {}, "expected": "-3" },
        { "name": "round to 2 decimals half away", "expression": "round(1.235, 2)", "values": {}, "expected": "1.24" },
        { "name": "round negative half at 4th decimal via mul", "expression": "{a} * 0.5", "values": { "a": "-0.0001" }, "expected": "-0.0001" },
        { "name": "negative half at conversion boundary", "expression": "{a} * 1", "values": { "a": "-0.00005" }, "expected": "-0.0001" },
        { "name": "ceil floor", "expression": "ceil(2.1) + floor(2.9)", "values": {}, "expected": "5" },
        { "name": "ceil floor negative", "expression": "ceil(-2.5) + floor(-2.5)", "values": {}, "expected": "-5" },
        { "name": "abs", "expression": "abs(-12.5)", "values": {}, "expected": "12.5" },
        { "name": "comparison yields one or zero", "expression": "(2 > 1) + (1 > 2)", "values": {}, "expected": "1" },
        { "name": "division by zero errors", "expression": "1 / {z}", "values": { "z": "0" }, "expected": { "error": "div_zero" } },
        { "name": "overflow errors", "expression": "{big} * {big}", "values": { "big": "999999999" }, "expected": { "error": "overflow" } },
        { "name": "unknown field errors", "expression": "{ghost} + 1", "values": {}, "expected": { "error": "unknown_field" } },
        { "name": "syntax error", "expression": "1 + + 2", "values": {}, "expected": { "error": "syntax" } },
        { "name": "unknown function error", "expression": "sqrt(4)", "values": {}, "expected": { "error": "unknown_function" } },
        { "name": "arity error", "expression": "if(1, 2)", "values": {}, "expected": { "error": "arity" } }
    ]
}
```

(Note `"unary minus"` case: `-2 * 3 + 10` parses as `((-2) * 3) + 10 = 4`. Note `"if true branch"`: qty=10 → `10 * 0.9 = 9`.)

- [ ] **Step 2: Write the fixture runner `tests/Unit/Formula/FormulaCasesTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Formula\FormulaError;
use PHPUnit\Framework\TestCase;

class FormulaCasesTest extends TestCase {

	/** @dataProvider casesProvider */
	public function test_fixture_case( string $expression, array $values, $expected ): void {
		$scaled = [];
		foreach ( $values as $id => $v ) {
			$scaled[ $id ] = DecimalMath::toScaled( $v );
		}

		if ( is_array( $expected ) ) {
			try {
				Formula::evaluate( Formula::compile( $expression ), $scaled );
				$this->fail( 'Expected FormulaError ' . $expected['error'] );
			} catch ( FormulaError $e ) {
				$this->assertSame( $expected['error'], $e->getErrorCode() );
			}
			return;
		}

		$result = Formula::evaluate( Formula::compile( $expression ), $scaled );
		$this->assertSame( $expected, DecimalMath::fromScaled( $result ) );
	}

	public function casesProvider(): iterable {
		$json = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/formula-cases.json' ), true );
		foreach ( $json['cases'] as $case ) {
			yield $case['name'] => [ $case['expression'], $case['values'], $case['expected'] ];
		}
	}
}
```

- [ ] **Step 3: Run**

Run: `vendor/bin/phpunit --filter FormulaCasesTest`
Expected: PASS — 28 cases. If any case fails, the engine (not the fixture) is wrong unless you can argue the fixture contradicts a DecimalMathTest assertion — fixtures are the contract. (The "negative half at conversion boundary" case pins the sign-aware `toScaled` cross-engine: naive JS `Math.round` would yield 0 there.)

- [ ] **Step 4: Commit**

```bash
git add tests/fixtures/formula-cases.json tests/Unit/Formula/FormulaCasesTest.php
git commit -m "test: shared formula parity fixtures + PHP fixture runner"
```

---

## Chunk 3: Formula Engine — JS Mirror

Implements the JS twin of Chunk 2 under `src/shared/formula/` (plain ES modules, no WP imports — shared by builder and front end). Mul/div use **BigInt internally** (spec §7: intermediates exceed `Number.MAX_SAFE_INTEGER`); stored values stay plain numbers (≤ 1e13, safe). Parity is enforced by running the SAME `tests/fixtures/formula-cases.json` under Jest.

### Task 12: `decimal.js`

**Files:**
- Create: `src/shared/formula/decimal.js`
- Test: `src/shared/formula/__tests__/decimal.test.js`
- Delete: `src/shared/formula/__tests__/smoke.test.js` (replaced by real tests)

- [ ] **Step 1: Write the failing test `src/shared/formula/__tests__/decimal.test.js`**

```js
import {
	SCALE, FormulaError, toScaled, fromScaled, add, sub, mul, div,
	roundToDecimals, ceilToInt, floorToInt,
} from '../decimal';

describe( 'decimal', () => {
	it( 'rounds at the conversion boundary, sign-aware', () => {
		expect( toScaled( 4.1 ) ).toBe( 41000 );   // naive 4.1*10000 = 41000.00000000001
		expect( toScaled( '4.1' ) ).toBe( 41000 );
		expect( toScaled( -4.1 ) ).toBe( -41000 ); // Math.round(-41000.0000...) would also pass here,
		expect( toScaled( -0.00005 ) ).toBe( -1 ); // ...but the half-case requires sign-aware rounding (spec §7).
	} );

	it( 'rejects non-numeric input', () => {
		expect( () => toScaled( 'abc' ) ).toThrow( FormulaError );
		expect( () => toScaled( '' ) ).toThrow( FormulaError );
	} );

	it( 'add/sub exact: 0.1 + 0.2 = 0.3', () => {
		expect( fromScaled( add( toScaled( '0.1' ), toScaled( '0.2' ) ) ) ).toBe( '0.3' );
		expect( fromScaled( sub( toScaled( '0.1' ), toScaled( '0.2' ) ) ) ).toBe( '-0.1' );
	} );

	it( 'mul/div rescale with half-away rounding (BigInt path)', () => {
		expect( fromScaled( mul( 1000, 2000 ) ) ).toBe( '0.02' );
		expect( fromScaled( mul( 41000, 30000 ) ) ).toBe( '12.3' );
		expect( mul( 1, 5000 ) ).toBe( 1 );    // 0.0001 * 0.5 → 0.0001 (half away)
		expect( mul( -1, 5000 ) ).toBe( -1 );
		expect( fromScaled( div( 100000, 30000 ) ) ).toBe( '3.3333' );
		expect( fromScaled( div( -100000, 30000 ) ) ).toBe( '-3.3333' );
	} );

	it( 'div by zero / overflow throw coded errors', () => {
		expect( () => div( 10000, 0 ) ).toThrow( expect.objectContaining( { code: 'div_zero' } ) );
		const big = toScaled( '999999999' );
		expect( () => mul( big, big ) ).toThrow( expect.objectContaining( { code: 'overflow' } ) );
	} );

	it( 'roundToDecimals half away from zero', () => {
		expect( roundToDecimals( 25000, 0 ) ).toBe( 30000 );
		expect( roundToDecimals( -25000, 0 ) ).toBe( -30000 );
		expect( fromScaled( roundToDecimals( toScaled( '1.235' ), 2 ) ) ).toBe( '1.24' );
	} );

	it( 'ceil/floor to integer incl. negatives', () => {
		expect( fromScaled( ceilToInt( toScaled( '2.1' ) ) ) ).toBe( '3' );
		expect( fromScaled( floorToInt( toScaled( '2.9' ) ) ) ).toBe( '2' );
		expect( fromScaled( ceilToInt( toScaled( '-2.5' ) ) ) ).toBe( '-2' );
		expect( fromScaled( floorToInt( toScaled( '-2.5' ) ) ) ).toBe( '-3' );
	} );

	it( 'fromScaled trims trailing zeros', () => {
		expect( fromScaled( 120000 ) ).toBe( '12' );
		expect( fromScaled( 125000 ) ).toBe( '12.5' );
		expect( fromScaled( 1 ) ).toBe( '0.0001' );
		expect( fromScaled( -125000 ) ).toBe( '-12.5' );
	} );
} );
```

- [ ] **Step 2: Run to verify failure**

Run: `npm test -- decimal`
Expected: FAIL — cannot find module `../decimal`.

- [ ] **Step 3: Create `src/shared/formula/decimal.js`**

```js
export const SCALE = 10000;
export const MAX_SCALED = 9999999999999; // ±999,999,999.9999

export class FormulaError extends Error {
	constructor( code, message, pos = -1 ) {
		super( message );
		this.name = 'FormulaError';
		this.code = code;
		this.pos = pos;
	}
}

function guard( absScaled ) {
	if ( absScaled > MAX_SCALED ) {
		throw new FormulaError( 'overflow', 'Value exceeds supported range (±999,999,999.9999)' );
	}
}

export function toScaled( v ) {
	if ( typeof v === 'string' && v.trim() === '' ) {
		throw new FormulaError( 'bad_number', 'Not a number: empty string' );
	}
	const f = Number( v );
	if ( ! Number.isFinite( f ) ) {
		throw new FormulaError( 'bad_number', 'Not a number: ' + String( v ) );
	}
	const sign = f < 0 ? -1 : 1;
	// Sign-aware boundary rounding: Math.round alone rounds half toward +∞ (spec §7).
	const scaled = Math.round( Math.abs( f ) * SCALE );
	guard( scaled );
	return sign * scaled;
}

export function fromScaled( x ) {
	const sign = x < 0 ? '-' : '';
	const a = Math.abs( x );
	const int = Math.trunc( a / SCALE );
	const frac = String( a % SCALE ).padStart( 4, '0' ).replace( /0+$/, '' );
	return sign + String( int ) + ( frac === '' ? '' : '.' + frac );
}

export function add( a, b ) {
	const r = a + b;
	guard( Math.abs( r ) );
	return r;
}

export function sub( a, b ) {
	return add( a, -b );
}

// Integer division n/d (BigInt, n ≥ 0n, d > 0n), half away from zero.
function divRoundBig( n, d ) {
	const q = n / d;
	const r = n - q * d;
	return 2n * r >= d ? q + 1n : q;
}

export function mul( a, b ) {
	const approx = ( a / SCALE ) * ( b / SCALE );
	if ( Math.abs( approx ) > MAX_SCALED / SCALE + 1 ) {
		throw new FormulaError( 'overflow', 'Multiplication overflow' );
	}
	const sign = a < 0 !== b < 0 ? -1 : 1;
	const A = BigInt( Math.abs( a ) );
	const B = BigInt( Math.abs( b ) );
	const S = BigInt( SCALE );
	const result = A * ( B / S ) + divRoundBig( A * ( B % S ), S );
	const num = Number( result );
	guard( num );
	return sign * num;
}

export function div( a, b ) {
	if ( b === 0 ) {
		throw new FormulaError( 'div_zero', 'Division by zero' );
	}
	const approx = a / b;
	if ( Math.abs( approx ) > MAX_SCALED / SCALE + 1 ) {
		throw new FormulaError( 'overflow', 'Division overflow' );
	}
	const sign = a < 0 !== b < 0 ? -1 : 1;
	const result = divRoundBig( BigInt( Math.abs( a ) ) * BigInt( SCALE ), BigInt( Math.abs( b ) ) );
	const num = Number( result );
	guard( num );
	return sign * num;
}

export function roundToDecimals( x, n ) {
	const clamped = Math.max( 0, Math.min( 4, n ) );
	const f = 10 ** ( 4 - clamped );
	const sign = x < 0 ? -1 : 1;
	const a = Math.abs( x );
	const q = Math.trunc( a / f );
	const r = a - q * f;
	return sign * ( 2 * r >= f ? q + 1 : q ) * f;
}

export function ceilToInt( x ) {
	let q = Math.trunc( x / SCALE );
	if ( x - q * SCALE > 0 ) {
		q++;
	}
	const result = q * SCALE;
	guard( Math.abs( result ) );
	return result;
}

export function floorToInt( x ) {
	let q = Math.trunc( x / SCALE );
	if ( x - q * SCALE < 0 ) {
		q--;
	}
	const result = q * SCALE;
	guard( Math.abs( result ) );
	return result;
}
```

Input-domain note: JS `Number()` accepts a few strings PHP `is_numeric` rejects (hex, `null`→0, `true`→1). Unreachable through the lexer (digits pre-validated) and harmless at runtime because callers (Chunk 7's `compute.js`) pass sanitized decimal strings and the PHP server stays authoritative — do not add a stricter regex; just never feed `toScaled` un-coerced user input outside those callers.

- [ ] **Step 4: Delete the smoke test and run**

```bash
rm src/shared/formula/__tests__/smoke.test.js
npm test -- decimal
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/shared/formula/decimal.js src/shared/formula/__tests__/decimal.test.js
git rm -q src/shared/formula/__tests__/smoke.test.js
git commit -m "feat: JS decimal core (BigInt mul/div, sign-aware boundary rounding)"
```

### Task 13: `lexer.js` + `parser.js`

**Files:**
- Create: `src/shared/formula/lexer.js`, `src/shared/formula/parser.js`, `src/shared/formula/functions.js`
- Test: `src/shared/formula/__tests__/parser.test.js`

These are line-for-line ports of `includes/Formula/Lexer.php` and `Parser.php` — keep names, token shapes (`{ type, value, pos }`), AST shapes, binding powers, and error codes identical. Port with the PHP files open side by side.

- [ ] **Step 1: Write the failing test `src/shared/formula/__tests__/parser.test.js`** (a condensed mirror of ParserTest — the heavy coverage comes from the shared fixtures in Task 14)

```js
import { tokenize } from '../lexer';
import { parse } from '../parser';
import { FUNCTION_SPECS } from '../functions';

const p = ( expr ) => parse( tokenize( expr ), FUNCTION_SPECS );

describe( 'lexer + parser', () => {
	it( 'tokenizes fields, numbers, comparisons with positions', () => {
		const tokens = tokenize( 'if({qty} >= 10, 5, 0)' );
		expect( tokens.map( ( t ) => t.type ) ).toEqual(
			[ 'ident', 'lparen', 'field', 'cmp', 'num', 'comma', 'num', 'comma', 'num', 'rparen' ]
		);
		expect( tokens[ 2 ].value ).toBe( 'qty' );
		expect( tokens[ 0 ].pos ).toBe( 0 );
	} );

	it( 'parses with correct precedence and scales numbers', () => {
		const ast = p( '1 + 2 * 3' );
		expect( ast.op ).toBe( '+' );
		expect( ast.right.op ).toBe( '*' );
		expect( p( '4.1' ) ).toEqual( { type: 'num', value: 41000 } );
	} );

	it( 'unary minus binds tighter than mul', () => {
		const ast = p( '-2 * 3' );
		expect( ast.op ).toBe( '*' );
		expect( ast.left.type ).toBe( 'neg' );
	} );

	it( 'throws coded errors', () => {
		expect( () => p( 'sqrt(4)' ) ).toThrow( expect.objectContaining( { code: 'unknown_function' } ) );
		expect( () => p( 'if(1, 2)' ) ).toThrow( expect.objectContaining( { code: 'arity' } ) );
		expect( () => p( '1 + $x' ) ).toThrow( expect.objectContaining( { code: 'syntax' } ) );
		expect( () => p( '1 2' ) ).toThrow( expect.objectContaining( { code: 'syntax' } ) );
		expect( () => p( '' ) ).toThrow( expect.objectContaining( { code: 'syntax' } ) );
	} );
} );
```

- [ ] **Step 2: Run to verify failure**

Run: `npm test -- parser`
Expected: FAIL — modules not found.

- [ ] **Step 3: Create `src/shared/formula/functions.js`**

```js
// name => [ minArity, maxArity ] — must stay identical to includes/Formula/Functions.php.
export const FUNCTION_SPECS = {
	if: [ 3, 3 ],
	min: [ 2, 8 ],
	max: [ 2, 8 ],
	round: [ 1, 2 ],
	ceil: [ 1, 1 ],
	floor: [ 1, 1 ],
	abs: [ 1, 1 ],
};
```

- [ ] **Step 4: Create `src/shared/formula/lexer.js`** — port `Lexer.php` exactly. Signature: `export function tokenize( expr )`. Same regexes — the sticky-`y` + `lastIndex` conversion replaces PHP's `/A` anchor for ALL THREE anchored regexes (field, number, ident); same `FormulaError( 'syntax', …, pos )` throws for unknown char / malformed number / unterminated field. Import `FormulaError` from `./decimal`.

- [ ] **Step 5: Create `src/shared/formula/parser.js`** — port `Parser.php` exactly. Signature: `export function parse( tokens, functionSpecs )` (functional style instead of a class is fine; keep an index closure). Same binding powers (cmp 10, add 20, mul 30, unary = mul+1), same node shapes, same error codes (`syntax`, `unknown_function`, `arity`). Numbers scaled via `toScaled` at parse time.

- [ ] **Step 6: Run tests to verify pass**

Run: `npm test -- parser`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/shared/formula/functions.js src/shared/formula/lexer.js src/shared/formula/parser.js src/shared/formula/__tests__/parser.test.js
git commit -m "feat: JS lexer/parser mirroring PHP grammar"
```

### Task 14: `evaluator.js` + `graph.js` + parity fixture runner

**Files:**
- Create: `src/shared/formula/evaluator.js`, `src/shared/formula/graph.js`, `src/shared/formula/index.js`
- Test: `src/shared/formula/__tests__/parity.test.js`, `src/shared/formula/__tests__/graph.test.js`

- [ ] **Step 1: Write the failing parity test `src/shared/formula/__tests__/parity.test.js`**

```js
import cases from '../../../../tests/fixtures/formula-cases.json';
import { compile, evaluate, FormulaError } from '../index';
import { toScaled, fromScaled } from '../decimal';

describe( 'PHP/JS parity fixtures', () => {
	cases.cases.forEach( ( c ) => {
		it( c.name, () => {
			const values = {};
			Object.entries( c.values ).forEach( ( [ id, v ] ) => {
				values[ id ] = toScaled( v );
			} );

			if ( typeof c.expected === 'object' ) {
				expect( () => evaluate( compile( c.expression ), values ) ).toThrow(
					expect.objectContaining( { code: c.expected.error } )
				);
				return;
			}
			expect( fromScaled( evaluate( compile( c.expression ), values ) ) ).toBe( c.expected );
		} );
	} );
} );
```

- [ ] **Step 2: Write the failing graph test `src/shared/formula/__tests__/graph.test.js`** — mirror the three PHP `FormulaGraphTest` cases (same inputs, same expected `order`/`cycles`, using `orderFormulas` from `../graph`).

- [ ] **Step 3: Run to verify failure**

Run: `npm test -- parity graph`
Expected: FAIL — modules not found.

- [ ] **Step 4: Create `src/shared/formula/evaluator.js`** — port `Evaluator.php` exactly: `export function evaluate( ast, values, functionSpecs )` with lazy `if`, comparisons returning `SCALE`/`0`, `round` second arg `Math.trunc( n / SCALE )`, errors with the same codes. Use the `decimal.js` ops for `+ - * /`.

- [ ] **Step 5: Create `src/shared/formula/graph.js`** — port `FormulaGraph.php` (Kahn's algorithm): `export function orderFormulas( idToRefs )` returning `{ order: [], cycles: [] }` with identical semantics (self-reference = cycle, refs to non-formula ids ignored).

- [ ] **Step 6: Create `src/shared/formula/index.js`** — the facade consumed by builder and front end:

```js
import { tokenize } from './lexer';
import { parse } from './parser';
import { evaluate as rawEvaluate } from './evaluator';
import { FUNCTION_SPECS } from './functions';

export { FormulaError, SCALE, toScaled, fromScaled } from './decimal';
export { orderFormulas } from './graph';

export function compile( expr ) {
	return parse( tokenize( expr ), FUNCTION_SPECS );
}

export function evaluate( ast, scaledValues ) {
	return rawEvaluate( ast, scaledValues, FUNCTION_SPECS );
}

export function references( ast ) {
	const refs = [];
	const walk = ( node ) => {
		if ( node.type === 'field' ) {
			refs.push( node.id );
		} else if ( node.type === 'neg' ) {
			walk( node.operand );
		} else if ( node.type === 'bin' || node.type === 'cmp' ) {
			walk( node.left );
			walk( node.right );
		} else if ( node.type === 'call' ) {
			node.args.forEach( walk );
		}
	};
	walk( ast );
	return [ ...new Set( refs ) ];
}
```

- [ ] **Step 7: Run the full JS suite**

Run: `npm test`
Expected: PASS — decimal + parser + graph + all 28 parity cases. The parity suite passing against the same JSON the PHP suite passes against IS the §7 parity guarantee.

- [ ] **Step 8: Commit**

```bash
git add src/shared/formula/evaluator.js src/shared/formula/graph.js src/shared/formula/index.js src/shared/formula/__tests__/parity.test.js src/shared/formula/__tests__/graph.test.js
git commit -m "feat: JS evaluator/graph + shared-fixture parity suite"
```

---

## Chunk 4: Conditional Logic + Field Domain (PHP)

### Task 15: Copy `ConditionalLogic` + fixtures + regression tests

**Files:**
- Create: `includes/Logic/ConditionalLogic.php` (copy of CF), `tests/fixtures/conditional-cases.json` (copy of CF), `src/frontend/conditional-logic.js` (copy of CF)
- Test: `tests/Unit/Logic/ConditionalLogicTest.php`

- [ ] **Step 1: Copy the engine + fixtures**

```bash
cp /Users/tahir/woo-checkout-fields/includes/Logic/ConditionalLogic.php includes/Logic/ConditionalLogic.php
cp /Users/tahir/woo-checkout-fields/tests/fixtures/conditional-cases.json tests/fixtures/conditional-cases.json
cp /Users/tahir/woo-checkout-fields/src/frontend/conditional-logic.js src/frontend/conditional-logic.js
```

- [ ] **Step 2: Change the PHP namespace** — in `includes/Logic/ConditionalLogic.php`, replace `namespace CoreLabs\CheckoutFields\Logic;` with `namespace Alovio\Calculator\Logic;`. No other edits (spec §4: verbatim modulo namespace). Run `php -l includes/Logic/ConditionalLogic.php`.

- [ ] **Step 3: Adapt the JS copy's imports only if needed** — open `src/frontend/conditional-logic.js`; it must remain framework-free and export `fieldActive( field, values )` and `activeMap( fields, values )`. If the CF copy contains checkout-specific exports (`readValues`, `wire` are in the PO copy, not CF), leave the file as copied — calculator-specific wiring lands in Chunk 7.

- [ ] **Step 4: Write the regression test** — first inspect how CF tests this engine:

Run: `ls /Users/tahir/woo-checkout-fields/tests/php/Unit/ && grep -rl conditional-cases /Users/tahir/woo-checkout-fields/tests/`
(CF's PHP tests live under `tests/php/Unit/` — the file is `tests/php/Unit/ConditionalLogicTest.php`.) Copy that test file to `tests/Unit/Logic/ConditionalLogicTest.php`, change its namespace to `Alovio\Calculator\Tests\Unit\Logic` and its imports to `Alovio\Calculator\Logic\ConditionalLogic`, and point its fixture path at `tests/fixtures/conditional-cases.json`. If CF's test extends a different base class, extend our `Alovio\Calculator\Tests\TestCase` instead.

Also copy the JS parity test (spec §13 requires the JS engine run against the same fixtures): `cp /Users/tahir/woo-checkout-fields/tests/js/conditional-logic.test.js tests/js/conditional-logic.test.js`, then fix its two paths — engine import → `../../src/frontend/conditional-logic`, fixture require → `../fixtures/conditional-cases.json`. (wp-scripts' Jest `testMatch` covers `tests/js/*.test.js`.)

- [ ] **Step 5: Run**

Run: `vendor/bin/phpunit --filter ConditionalLogicTest && npm test -- conditional-logic`
Expected: PASS on both sides — every fixture case green with zero engine edits. If anything fails, the copy was modified — re-copy; do not "fix" the engine.

- [ ] **Step 6: Add a calculator-specific guard test** to the same file: `active_map` with a `conditions[]`/`conditionMatch: 'any'` group referencing a toggle exposed as `"1"`/`""` (spec §6 table) — asserts our value conventions work against the untouched engine:

```php
public function test_toggle_convention_drives_visibility(): void {
	$group = [ 'fields' => [
		[ 'id' => 'express', 'type' => 'toggle' ],
		[ 'id' => 'note', 'type' => 'text', 'conditions' => [ [ 'field' => 'express', 'operator' => 'is', 'value' => '1' ] ], 'conditionMatch' => 'all', 'conditionAction' => 'show' ],
	] ];
	$on  = \Alovio\Calculator\Logic\ConditionalLogic::active_map( $group, [ 'express' => '1', 'note' => '' ] );
	$off = \Alovio\Calculator\Logic\ConditionalLogic::active_map( $group, [ 'express' => '', 'note' => '' ] );
	$this->assertTrue( $on['note'] );
	$this->assertFalse( $off['note'] );
}
```

VERIFIED FACT (checked against the CF source during plan review): the signature is `active_map( array $group, array $values )` where `$group = [ 'fields' => […] ]` — exactly as this test and Chunk 5's `Evaluation` call it. No adjustment expected; if the copy disagrees, you copied the wrong file.

- [ ] **Step 7: Run + commit**

```bash
vendor/bin/phpunit --filter ConditionalLogicTest && npm test -- conditional-logic
git add includes/Logic/ConditionalLogic.php tests/fixtures/conditional-cases.json src/frontend/conditional-logic.js tests/Unit/Logic/ConditionalLogicTest.php tests/js/conditional-logic.test.js
git commit -m "feat: import conditional-logic engine + fixtures from checkout-fields (verbatim)"
```

### Task 16: `FieldTypes` registry

**Files:**
- Create: `includes/Fields/FieldTypes.php`
- Test: `tests/Unit/Fields/FieldTypesTest.php`

Type-token conventions (canonical for the whole codebase): the spec's "heading/divider" is implemented as the single `heading` type (a divider is simply a heading with an empty label); the spec's "checkbox-group" is the token `checkbox_group` (underscore — valid as a PHP array key, JS identifier, and `sanitize_key` output).

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Fields;

use Alovio\Calculator\Fields\FieldTypes;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Filters;

class FieldTypesTest extends TestCase {

	public function test_free_list_matches_spec_section_6(): void {
		Filters\expectApplied( 'alc_field_types' )->andReturnFirstArg();
		$this->assertSame(
			[ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'heading', 'html', 'formula' ],
			FieldTypes::all()
		);
	}

	public function test_classifiers(): void {
		$this->assertTrue( FieldTypes::is_input( 'number' ) );
		$this->assertTrue( FieldTypes::is_choice( 'radio' ) );
		$this->assertFalse( FieldTypes::is_input( 'formula' ) );
		$this->assertFalse( FieldTypes::is_input( 'heading' ) );
		$this->assertTrue( FieldTypes::is_referenceable( 'toggle' ) );
		$this->assertFalse( FieldTypes::is_referenceable( 'text' ) );
		$this->assertTrue( FieldTypes::is_condition_controller( 'text' ) );
		$this->assertFalse( FieldTypes::is_condition_controller( 'formula' ) ); // spec §6/§7
	}
}
```

- [ ] **Step 2: Run to verify failure**, then **Step 3: Create `includes/Fields/FieldTypes.php`**

```php
<?php
namespace Alovio\Calculator\Fields;

final class FieldTypes {

	public const FREE = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'heading', 'html', 'formula' ];

	private const CHOICE = [ 'select', 'radio', 'checkbox_group' ];

	/** Fields a visitor types/picks values into. */
	private const INPUT = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text' ];

	/** Fields usable as {refs} in formulas (spec §6 formula value map). */
	private const REFERENCEABLE = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'formula' ];

	public static function all(): array {
		return apply_filters( 'alc_field_types', self::FREE );
	}

	public static function is_choice( string $type ): bool {
		return in_array( $type, self::CHOICE, true );
	}

	public static function is_input( string $type ): bool {
		return in_array( $type, self::INPUT, true );
	}

	public static function is_referenceable( string $type ): bool {
		return in_array( $type, self::REFERENCEABLE, true );
	}

	/** Spec §6: conditions may reference input fields only — never formula/heading/html. */
	public static function is_condition_controller( string $type ): bool {
		return self::is_input( $type );
	}
}
```

- [ ] **Step 4: Run tests, commit**

```bash
vendor/bin/phpunit --filter FieldTypesTest
git add includes/Fields/FieldTypes.php tests/Unit/Fields/FieldTypesTest.php
git commit -m "feat: field type registry with classifier helpers"
```

### Task 17: `FieldSchema` (config normalization/validation)

**Files:**
- Create: `includes/Fields/FieldSchema.php`
- Test: `tests/Unit/Fields/FieldSchemaTest.php`

Read `/Users/tahir/woo-checkout-fields/includes/Fields/FieldSchema.php` first for the established normalize style, then write ours fresh (the calculator schema differs enough that a fresh file beats a diff). Responsibilities (spec §5/§6/§12): normalize `{schemaVersion, fields[], settings{}}` in→out; ids unique + `sanitize_key`; types whitelisted; per-option `{value, label, price, image}` with **auto-generated `opt_` slugs**; expression cap 1000 chars; conditions only on/against valid controllers; `conditionAction` show/hide; settings (currency/theme/quoteForm) normalized with defaults.

- [ ] **Step 1: Write the failing test `tests/Unit/Fields/FieldSchemaTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Fields;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class FieldSchemaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->alias( static fn( $c ) => preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : '' );
		Functions\when( 'sanitize_email' )->alias( static fn( $e ) => filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '' );
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	private function config( array $fields, array $settings = [] ): array {
		return [ 'schemaVersion' => 1, 'fields' => $fields, 'settings' => $settings ];
	}

	public function test_generates_unique_opt_slugs_for_new_options(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'service', 'type' => 'radio', 'label' => 'Service', 'options' => [
				[ 'label' => 'Standard', 'price' => '2.5' ],
				[ 'label' => 'Deep', 'price' => 4 ],
			] ],
		] ) );
		$opts = $out['fields'][0]['options'];
		$this->assertMatchesRegularExpression( '/^opt_[a-z0-9]{4,8}$/', $opts[0]['value'] );
		$this->assertNotSame( $opts[0]['value'], $opts[1]['value'] );
		$this->assertSame( 2.5, $opts[0]['price'] );
	}

	public function test_preserves_existing_option_slugs(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'service', 'type' => 'radio', 'label' => 'S', 'options' => [
				[ 'value' => 'opt_7f3a', 'label' => 'Standard', 'price' => 2.5 ],
			] ],
		] ) );
		$this->assertSame( 'opt_7f3a', $out['fields'][0]['options'][0]['value'] );
	}

	public function test_rejects_unknown_types_and_duplicate_ids(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'a', 'type' => 'number', 'label' => 'A' ],
			[ 'id' => 'a', 'type' => 'number', 'label' => 'Dupe' ],
			[ 'id' => 'b', 'type' => 'launchcodes', 'label' => 'Bad' ],
		] ) );
		$this->assertCount( 1, $out['fields'] );
		$this->assertSame( 'a', $out['fields'][0]['id'] );
	}

	public function test_strips_conditions_with_formula_or_unknown_controllers(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'T', 'expression' => '1 + 1' ],
			[ 'id' => 'qty', 'type' => 'quantity', 'label' => 'Q' ],
			[ 'id' => 'x', 'type' => 'number', 'label' => 'X', 'conditions' => [
				[ 'field' => 'total', 'operator' => 'gt', 'value' => '10' ],
				[ 'field' => 'ghost', 'operator' => 'is', 'value' => '1' ],
				[ 'field' => 'qty', 'operator' => 'gt', 'value' => '2' ],
			], 'conditionMatch' => 'any', 'conditionAction' => 'require' ],
		] ) );
		$x = $out['fields'][2];
		$this->assertCount( 1, $x['conditions'] );                  // only qty survives
		$this->assertSame( 'qty', $x['conditions'][0]['field'] );
		$this->assertSame( 'show', $x['conditionAction'] );          // require coerced to show (spec §6)
	}

	public function test_expression_normalization(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'f1', 'type' => 'formula', 'label' => 'F', 'expression' => str_repeat( 'x', 2000 ) ],
		] ) );
		$this->assertSame( 1000, strlen( $out['fields'][0]['expression'] ) );
	}

	public function test_settings_defaults_and_sanitization(): void {
		$out = FieldSchema::normalize( $this->config( [], [
			'currency' => [ 'symbol' => '<b>$</b>', 'position' => 'nonsense', 'decimals' => 9 ],
			'theme'    => [ 'accent' => 'javascript:alert(1)' ],
			'quoteForm' => [ 'enabled' => 1, 'fields' => [ 'name', 'email', 'bogus' ], 'notifyEmail' => 'not-an-email' ],
		] ) );
		$s = $out['settings'];
		$this->assertSame( '$', $s['currency']['symbol'] );
		$this->assertSame( 'before', $s['currency']['position'] ); // default on invalid
		$this->assertSame( 2, $s['currency']['decimals'] );        // clamped 0..4 → default 2 on out-of-range
		$this->assertSame( '#0a66ff', $s['theme']['accent'] );     // default on invalid
		$this->assertTrue( $s['quoteForm']['enabled'] );
		$this->assertSame( [ 'name', 'email' ], $s['quoteForm']['fields'] );
		$this->assertSame( '', $s['quoteForm']['notifyEmail'] );
	}

	public function test_empty_input_yields_valid_empty_config(): void {
		$out = FieldSchema::normalize( [] );
		$this->assertSame( 1, $out['schemaVersion'] );
		$this->assertSame( [], $out['fields'] );
		$this->assertArrayHasKey( 'currency', $out['settings'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**, then **Step 3: Create `includes/Fields/FieldSchema.php`**

```php
<?php
namespace Alovio\Calculator\Fields;

final class FieldSchema {

	public const SCHEMA_VERSION   = 1;
	public const EXPRESSION_LIMIT = 1000;
	private const OPERATORS       = [ 'is', 'is_not', 'contains', 'gt', 'lt' ];

	public static function defaults(): array {
		return [
			'schemaVersion' => self::SCHEMA_VERSION,
			'fields'        => [],
			'settings'      => [
				'currency'  => [ 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ],
				'theme'     => [ 'accent' => '#0a66ff' ],
				'quoteForm' => [ 'enabled' => false, 'fields' => [ 'name', 'email' ], 'notifyEmail' => '', 'successMessage' => '' ],
			],
		];
	}

	public static function normalize( array $raw ): array {
		$out    = self::defaults();
		$types  = FieldTypes::all();
		$seen   = [];
		$fields = [];

		foreach ( (array) ( $raw['fields'] ?? [] ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			$id   = sanitize_key( (string) ( $field['id'] ?? '' ) );
			if ( '' === $id || isset( $seen[ $id ] ) || ! in_array( $type, $types, true ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$fields[]    = self::normalize_field( $field, $id, $type );
		}

		// Conditions can only be validated once all ids are known.
		$controllers = [];
		foreach ( $fields as $field ) {
			if ( FieldTypes::is_condition_controller( $field['type'] ) ) {
				$controllers[ $field['id'] ] = true;
			}
		}
		foreach ( $fields as &$field ) {
			$field = self::normalize_conditions( $field, $controllers );
		}
		unset( $field );

		$out['fields']   = $fields;
		$out['settings'] = self::normalize_settings( (array) ( $raw['settings'] ?? [] ) );
		return $out;
	}

	private static function normalize_field( array $raw, string $id, string $type ): array {
		$field = [
			'id'            => $id,
			'type'          => $type,
			'label'         => sanitize_text_field( (string) ( $raw['label'] ?? '' ) ),
			'showInSummary' => ! empty( $raw['showInSummary'] ),
		];

		switch ( $type ) {
			case 'number':
			case 'slider':
			case 'quantity':
				foreach ( [ 'min', 'max', 'step', 'default' ] as $k ) {
					$field[ $k ] = isset( $raw[ $k ] ) && is_numeric( $raw[ $k ] ) ? (float) $raw[ $k ] : null;
				}
				// Deliberately NO price on numeric fields — formulas do the multiplying (keeps the §6 value maps unambiguous).
				break;

			case 'toggle':
				$field['price']   = isset( $raw['price'] ) && is_numeric( $raw['price'] ) ? (float) $raw['price'] : 0.0;
				$field['default'] = ! empty( $raw['default'] );
				break;

			case 'select':
			case 'radio':
			case 'checkbox_group':
				$field['options'] = self::normalize_options( (array) ( $raw['options'] ?? [] ) );
				break;

			case 'formula':
				$field['expression'] = substr( trim( (string) ( $raw['expression'] ?? '' ) ), 0, self::EXPRESSION_LIMIT );
				break;

			case 'html':
				$field['content'] = wp_kses_post( (string) ( $raw['content'] ?? '' ) );
				break;

			case 'text':
			case 'heading':
				$field['placeholder'] = sanitize_text_field( (string) ( $raw['placeholder'] ?? '' ) );
				break;
		}

		return $field;
	}

	private static function normalize_options( array $rawOptions ): array {
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
				'value' => $value,
				'label' => sanitize_text_field( (string) ( $opt['label'] ?? '' ) ),
				'price' => isset( $opt['price'] ) && is_numeric( $opt['price'] ) ? (float) $opt['price'] : 0.0,
				'image' => isset( $opt['image'] ) ? max( 0, (int) $opt['image'] ) : 0,
			];
		}
		return $options;
	}

	private static function generate_slug( array $used ): string {
		do {
			$slug = 'opt_' . substr( bin2hex( random_bytes( 4 ) ), 0, 6 );
		} while ( isset( $used[ $slug ] ) );
		return $slug;
	}

	private static function normalize_conditions( array $field, array $controllers ): array {
		$conditions = [];
		foreach ( (array) ( $field['conditions'] ?? [] ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$controller = sanitize_key( (string) ( $rule['field'] ?? '' ) );
			$operator   = (string) ( $rule['operator'] ?? '' );
			if ( ! isset( $controllers[ $controller ] ) || $controller === $field['id'] || ! in_array( $operator, self::OPERATORS, true ) ) {
				continue; // Formula/unknown/self controllers rejected (spec §6/§7).
			}
			$conditions[] = [
				'field'    => $controller,
				'operator' => $operator,
				'value'    => sanitize_text_field( (string) ( $rule['value'] ?? '' ) ),
			];
		}
		$field['conditions']      = $conditions;
		$field['conditionMatch']  = 'any' === ( $field['conditionMatch'] ?? '' ) ? 'any' : 'all';
		$field['conditionAction'] = 'hide' === ( $field['conditionAction'] ?? '' ) ? 'hide' : 'show'; // require coerced (spec §6)
		return $field;
	}

	private static function normalize_settings( array $raw ): array {
		$d = self::defaults()['settings'];

		$currency = (array) ( $raw['currency'] ?? [] );
		$symbol   = sanitize_text_field( (string) ( $currency['symbol'] ?? $d['currency']['symbol'] ) );
		$decimals = $currency['decimals'] ?? $d['currency']['decimals'];

		$quote  = (array) ( $raw['quoteForm'] ?? [] );
		$fields = array_values( array_intersect( [ 'name', 'email', 'phone', 'message' ], (array) ( $quote['fields'] ?? $d['quoteForm']['fields'] ) ) );
		if ( ! in_array( 'name', $fields, true ) ) {
			array_unshift( $fields, 'name' );
		}
		if ( ! in_array( 'email', $fields, true ) ) {
			array_splice( $fields, 1, 0, 'email' );
		}

		$accent = sanitize_hex_color( (string) ( ( $raw['theme']['accent'] ?? '' ) ) );

		return [
			'currency'  => [
				'symbol'      => '' !== $symbol ? $symbol : $d['currency']['symbol'],
				'position'    => in_array( $currency['position'] ?? '', [ 'before', 'after' ], true ) ? $currency['position'] : $d['currency']['position'],
				'decimals'    => is_numeric( $decimals ) && (int) $decimals >= 0 && (int) $decimals <= 4 ? (int) $decimals : $d['currency']['decimals'],
				'thousandSep' => sanitize_text_field( (string) ( $currency['thousandSep'] ?? $d['currency']['thousandSep'] ) ),
				'decimalSep'  => sanitize_text_field( (string) ( $currency['decimalSep'] ?? $d['currency']['decimalSep'] ) ),
			],
			'theme'     => [ 'accent' => '' !== (string) $accent ? $accent : $d['theme']['accent'] ],
			'quoteForm' => [
				'enabled'        => ! empty( $quote['enabled'] ),
				'fields'         => $fields,
				'notifyEmail'    => sanitize_email( (string) ( $quote['notifyEmail'] ?? '' ) ),
				'successMessage' => sanitize_text_field( (string) ( $quote['successMessage'] ?? '' ) ),
			],
		];
	}
}
```

- [ ] **Step 4: Run tests** (`vendor/bin/phpunit --filter FieldSchemaTest`) — iterate until green; the test pins the contract, adjust the implementation, not the test.

- [ ] **Step 5: Commit**

```bash
git add includes/Fields/FieldSchema.php tests/Unit/Fields/FieldSchemaTest.php
git commit -m "feat: config schema normalization (opt_ slugs, controller validation, settings)"
```

### Task 18: `FieldRepository` + CPT registration

**Files:**
- Create: `includes/Fields/FieldRepository.php`
- Modify: `includes/Plugin.php` (register CPT in `init()`)
- Test: `tests/Unit/Fields/FieldRepositoryTest.php`

- [ ] **Step 1: Write the failing test** — Brain Monkey mocks for `get_post_meta` / `update_post_meta` / `get_post`; assert: `get()` runs stored JSON through `FieldSchema::normalize` (feed it a stored config with a bogus field type, expect it stripped); `save()` JSON-encodes the normalized config (expect `update_post_meta` called with normalized array encoded via `wp_json_encode` — mock `wp_json_encode` as `json_encode` alias); `get()` on missing/invalid meta returns `FieldSchema::defaults()`.

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Fields;

use Alovio\Calculator\Fields\FieldRepository;
use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class FieldRepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->justReturn( '#0a66ff' );
		Functions\when( 'sanitize_email' )->justReturn( '' );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	public function test_get_normalizes_stored_json(): void {
		$stored = json_encode( [ 'fields' => [
			[ 'id' => 'a', 'type' => 'number', 'label' => 'A' ],
			[ 'id' => 'evil', 'type' => 'launchcodes', 'label' => 'X' ],
		] ] );
		Functions\when( 'get_post_meta' )->justReturn( $stored );
		$config = ( new FieldRepository() )->get( 7 );
		$this->assertCount( 1, $config['fields'] );
	}

	public function test_get_returns_defaults_on_garbage(): void {
		Functions\when( 'get_post_meta' )->justReturn( '{not json' );
		$this->assertSame( FieldSchema::defaults(), ( new FieldRepository() )->get( 7 ) );
	}

	public function test_save_writes_normalized_json(): void {
		$captured = null;
		Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $value ) use ( &$captured ) {
			$captured = [ $id, $key, $value ];
			return true;
		} );
		( new FieldRepository() )->save( 7, [ 'fields' => [ [ 'id' => 'a', 'type' => 'number', 'label' => 'A' ] ] ] );
		$this->assertSame( 7, $captured[0] );
		$this->assertSame( '_alc_config', $captured[1] );
		$decoded = json_decode( $captured[2], true );
		$this->assertSame( 1, $decoded['schemaVersion'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**, then **Step 3: Create `includes/Fields/FieldRepository.php`**

```php
<?php
namespace Alovio\Calculator\Fields;

final class FieldRepository {

	public const META_KEY = '_alc_config';
	public const POST_TYPE = 'alc_calculator';

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'          => [ 'name' => __( 'Calculators', 'alovio-calculator' ) ],
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => [ 'title' ],
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			]
		);
	}

	public function get( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			return FieldSchema::defaults();
		}
		return FieldSchema::normalize( $decoded );
	}

	public function save( int $post_id, array $config ): array {
		$normalized = FieldSchema::normalize( $config );
		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $normalized ) );
		return $normalized;
	}
}
```

- [ ] **Step 4: Wire CPT registration** — in `includes/Plugin.php`, add to `init()`:

```php
Fields\FieldRepository::register_post_type();
```

(and `use` nothing — the fully qualified call `\Alovio\Calculator\Fields\FieldRepository::register_post_type();` is fine inside the namespace as `Fields\FieldRepository::…`).

- [ ] **Step 5: Run tests + syntax check, commit**

```bash
vendor/bin/phpunit --filter FieldRepositoryTest && php -l includes/Plugin.php
git add includes/Fields/FieldRepository.php tests/Unit/Fields/FieldRepositoryTest.php includes/Plugin.php
git commit -m "feat: calculator CPT + config repository over postmeta JSON"
```

### Task 19: Template presets (6 verticals)

**Files:**
- Create: `includes/Templates/Presets.php`
- Test: `tests/Unit/Templates/PresetsTest.php`

Each preset is a complete `_alc_config`-shaped array: realistic fields with per-option prices, at least one conditional rule, one formula with `showInSummary: true`, and currency defaults. Option values use hand-authored `opt_` slugs (valid per FieldSchema since they pass the `opt_` prefix + uniqueness check).

- [ ] **Step 1: Write the failing test `tests/Unit/Templates/PresetsTest.php`**

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Templates;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Templates\Presets;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class PresetsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	public function test_six_presets_exist(): void {
		$this->assertSame(
			[ 'cleaning-price', 'moving-cost', 'print-quote', 'agency-estimate', 'salon-pricing', 'rental-cost' ],
			array_keys( Presets::all() )
		);
	}

	public function test_every_preset_is_normalization_stable_and_compiles(): void {
		foreach ( Presets::all() as $key => $preset ) {
			$normalized = FieldSchema::normalize( $preset['config'] );
			$this->assertSame( $normalized, FieldSchema::normalize( $normalized ), "Preset {$key} must be normalization-idempotent" );
			$formulas = array_filter( $normalized['fields'], static fn( $f ) => 'formula' === $f['type'] );
			$this->assertNotEmpty( $formulas, "Preset {$key} needs at least one formula" );
			foreach ( $formulas as $f ) {
				Formula::compile( $f['expression'] ); // Throws on invalid syntax.
			}
			$summary = array_filter( $normalized['fields'], static fn( $f ) => ! empty( $f['showInSummary'] ) );
			$this->assertNotEmpty( $summary, "Preset {$key} needs a summary line" );
		}
	}

	public function test_every_formula_reference_resolves_to_a_field(): void {
		foreach ( Presets::all() as $key => $preset ) {
			$normalized = FieldSchema::normalize( $preset['config'] );
			$ids = array_column( $normalized['fields'], 'id' );
			foreach ( $normalized['fields'] as $f ) {
				if ( 'formula' !== $f['type'] ) {
					continue;
				}
				foreach ( Formula::references( Formula::compile( $f['expression'] ) ) as $ref ) {
					$this->assertContains( $ref, $ids, "Preset {$key}: {{$ref}} must exist" );
				}
			}
		}
	}
}
```

- [ ] **Step 2: Run to verify failure**, then **Step 3: Create `includes/Templates/Presets.php`** with this exact structure — `all()` returns `key => [ 'title' => __(…), 'description' => __(…), 'config' => […] ]`. Write all six configs yourself following this complete reference preset (the other five vary fields/prices/labels to match their vertical; each must satisfy every assertion in the test — at least one formula with `showInSummary`, ≥1 conditional rule, resolvable references, hand-authored unique `opt_` slugs):

```php
'cleaning-price' => [
	'title'       => __( 'Cleaning Price Calculator', 'alovio-calculator' ),
	'description' => __( 'Per-square-meter cleaning quote with service level and extras.', 'alovio-calculator' ),
	'config'      => [
		'schemaVersion' => 1,
		'fields'        => [
			[ 'id' => 'area', 'type' => 'slider', 'label' => __( 'Area (m²)', 'alovio-calculator' ), 'min' => 10, 'max' => 500, 'step' => 5, 'default' => 50, 'showInSummary' => true ],
			[ 'id' => 'service', 'type' => 'radio', 'label' => __( 'Service level', 'alovio-calculator' ), 'showInSummary' => true, 'options' => [
				[ 'value' => 'opt_std', 'label' => __( 'Standard', 'alovio-calculator' ), 'price' => 2.5 ],
				[ 'value' => 'opt_deep', 'label' => __( 'Deep clean', 'alovio-calculator' ), 'price' => 4 ],
			] ],
			[ 'id' => 'windows', 'type' => 'quantity', 'label' => __( 'Windows', 'alovio-calculator' ), 'min' => 0, 'max' => 50, 'default' => 0, 'showInSummary' => true ],
			[ 'id' => 'express', 'type' => 'toggle', 'label' => __( 'Express (24h)', 'alovio-calculator' ), 'price' => 30, 'showInSummary' => true ],
			[ 'id' => 'express_note', 'type' => 'heading', 'label' => __( 'Express slots are limited on weekends.', 'alovio-calculator' ), 'conditions' => [
				[ 'field' => 'express', 'operator' => 'is', 'value' => '1' ],
			], 'conditionMatch' => 'all', 'conditionAction' => 'show' ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => __( 'Estimated price', 'alovio-calculator' ), 'showInSummary' => true,
				'expression' => '{area} * {service} + {windows} * 6 + {express}' ],
		],
		'settings'      => [ 'quoteForm' => [ 'enabled' => true, 'fields' => [ 'name', 'email', 'phone' ] ] ],
	],
],
```

Vertical briefs for the other five (each ~5–7 fields):
- **moving-cost**: rooms (quantity), distance km (number, per-km price via formula `{distance} * 1.2`), floor without elevator (select w/ 3 priced options), packing service (toggle **priced** — a price-0 toggle evaluates to 0 in formulas and can never drive an `if`), fragile-items note (heading shown when packing on), total formula.
- **print-quote**: product (radio: flyer/poster/sticker w/ unit prices), quantity (number, min 50), double-sided (toggle, **price 1** — boolean flag: a price-0 toggle is 0 whether on or off, so `if({double}, …)` would never fire; price 1 makes on ⇒ 1; it is referenced ONLY inside the `if` condition of `if({double} > 0, {qty} * 0.05, 0)`, never added to the total directly), express production (toggle priced), total with `max(…, 25)` minimum-order clamp.
- **agency-estimate**: project type (radio priced), pages (slider), CMS setup (toggle priced), SEO package (select priced), care-plan note (heading conditional on SEO option via `contains`), total formula with `round(…, 0)`.
- **salon-pricing**: treatment (radio priced), hair length (select priced), add-ons (checkbox_group, 3 priced options), weekend appointment (toggle priced), total formula.
- **rental-cost**: unit type (radio priced/day), days (quantity min 1 default 1), insurance (toggle priced/day → formula `({unit} + if({insurance} > 0, 12, 0)) * {days}` — use the `> 0` idiom consistently), delivery (toggle priced flat), total formula.

- [ ] **Step 4: Run tests** (`vendor/bin/phpunit --filter PresetsTest`) — all three tests must pass for all six presets.

- [ ] **Step 5: Commit**

```bash
git add includes/Templates/Presets.php tests/Unit/Templates/PresetsTest.php
git commit -m "feat: six vertical template presets, normalization-stable and compile-checked"
```

---

## Chunk 5: Server Calculation, REST API, Entries

### Task 20: Entries table installer + activation wiring

**Files:**
- Create: `includes/Entries/EntriesTable.php`
- Modify: `includes/Plugin.php` (`activate()`, multisite hook)

- [ ] **Step 1: Create `includes/Entries/EntriesTable.php`**

```php
<?php
namespace Alovio\Calculator\Entries;

final class EntriesTable {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'alc_entries';
	}

	/** Spec §5 DDL. Idempotent via dbDelta. */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				calculator_id BIGINT UNSIGNED NOT NULL,
				name VARCHAR(190) NOT NULL DEFAULT '',
				email VARCHAR(190) NOT NULL DEFAULT '',
				phone VARCHAR(64) NOT NULL DEFAULT '',
				message TEXT NULL,
				snapshot LONGTEXT NOT NULL,
				total DECIMAL(18,4) NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'new',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY calculator_id (calculator_id),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/** Network activation + new-subsite support (spec §5 Multisite). */
	public static function install_for_network( bool $network_wide ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			self::install();
			return;
		}
		foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::install();
			restore_current_blog();
		}
	}
}
```

(dbDelta formatting rules matter: two spaces after `PRIMARY KEY`, each field on its own line — keep as written.)

- [ ] **Step 2: Wire activation** — in `includes/Plugin.php`:
  - change `boot()`'s activation registration to `register_activation_hook( ALC_FILE, [ $this, 'activate' ] );` (already present) and update `activate()`:

```php
public function activate( bool $network_wide = false ): void {
	Entries\EntriesTable::install_for_network( $network_wide );
	update_option( 'alc_version', ALC_VERSION );
}
```

  - add to `boot()`:

```php
add_action( 'wp_initialize_site', static function ( $new_site ) {
	if ( is_plugin_active_for_network( plugin_basename( ALC_FILE ) ) ) {
		switch_to_blog( (int) $new_site->blog_id );
		Entries\EntriesTable::install();
		restore_current_blog();
	}
}, 10, 1 );
```

with `require_once ABSPATH . 'wp-admin/includes/plugin.php';` guarded inside the closure before `is_plugin_active_for_network` (front-end requests don't load it).

- [ ] **Step 3: Verify + commit**

```bash
php -l includes/Entries/EntriesTable.php && php -l includes/Plugin.php && vendor/bin/phpunit
git add includes/Entries/EntriesTable.php includes/Plugin.php
git commit -m "feat: entries table installer with multisite support"
```

### Task 21: `EntriesRepository`

**Files:**
- Create: `includes/Entries/EntriesRepository.php`
- Test: `tests/Unit/Entries/EntriesRepositoryTest.php`

- [ ] **Step 1: Write the failing test** — focus on the pure shaping logic (`row_from_submission`), not `$wpdb` plumbing:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\EntriesRepository;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class EntriesRepositoryTest extends TestCase {

	public function test_row_from_submission_shapes_and_clips(): void {
		Functions\when( 'current_time' )->justReturn( '2026-06-12 10:00:00' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$row = EntriesRepository::row_from_submission(
			7,
			[ 'name' => str_repeat( 'n', 300 ), 'email' => 'a@b.co', 'phone' => '+99450', 'message' => 'hi' ],
			[ 'lineItems' => [ [ 'id' => 'total', 'label' => 'Price', 'amount' => 1750000 ] ], 'totalScaled' => 1750000, 'totalDisplay' => '$175.00', 'values' => [ 'area' => '50' ] ]
		);
		$this->assertSame( 7, $row['calculator_id'] );
		$this->assertSame( 190, strlen( $row['name'] ) );
		$this->assertSame( 'a@b.co', $row['email'] );
		$this->assertSame( '175.0000', $row['total'] );
		$this->assertSame( 'new', $row['status'] );
		$this->assertSame( '2026-06-12 10:00:00', $row['created_at'] );
		$this->assertIsString( $row['snapshot'] );
		$this->assertSame( 1750000, json_decode( $row['snapshot'], true )['totalScaled'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**, then **Step 3: Create `includes/Entries/EntriesRepository.php`**

```php
<?php
namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Formula\DecimalMath;

final class EntriesRepository {

	/** Pure shaping — unit-tested. Snapshot = values + line items + totals (spec §5). */
	public static function row_from_submission( int $calculator_id, array $contact, array $result ): array {
		return [
			'calculator_id' => $calculator_id,
			'name'          => mb_substr( (string) ( $contact['name'] ?? '' ), 0, 190 ),
			'email'         => mb_substr( (string) ( $contact['email'] ?? '' ), 0, 190 ),
			'phone'         => mb_substr( (string) ( $contact['phone'] ?? '' ), 0, 64 ),
			'message'       => (string) ( $contact['message'] ?? '' ),
			'snapshot'      => wp_json_encode( $result ),
			'total'         => number_format( ( $result['totalScaled'] ?? 0 ) / DecimalMath::SCALE, 4, '.', '' ),
			'status'        => 'new',
			'created_at'    => current_time( 'mysql' ),
		];
	}

	public function insert( array $row ): int {
		global $wpdb;
		$wpdb->insert( EntriesTable::table_name(), $row );
		return (int) $wpdb->insert_id;
	}

	/** @return array{rows: array[], total: int} */
	public function paginate( int $calculator_id = 0, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table  = EntriesTable::table_name();
		$where  = $calculator_id > 0 ? $wpdb->prepare( 'WHERE calculator_id = %d', $calculator_id ) : '';
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- table name + pre-prepared where
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		return [ 'rows' => $rows ?: [], 'total' => $total ];
	}

	public function set_status( int $id, string $status ): void {
		global $wpdb;
		$wpdb->update( EntriesTable::table_name(), [ 'status' => 'read' === $status ? 'read' : 'new' ], [ 'id' => $id ] );
	}

	public function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( EntriesTable::table_name(), [ 'id' => $id ] );
	}

	/** Used by the entries REST routes for 404 semantics. */
	public function find( int $id ): ?array {
		global $wpdb;
		$table = EntriesTable::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $row ?: null;
	}

	/** Used by the CSV exporter — ALL rows, no pagination. */
	public function all_for_export( int $calculator_id = 0 ): array {
		global $wpdb;
		$table = EntriesTable::table_name();
		$where = $calculator_id > 0 ? $wpdb->prepare( 'WHERE calculator_id = %d', $calculator_id ) : '';
		return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY id ASC", ARRAY_A ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public function delete_by_email( string $email ): int {
		global $wpdb;
		return (int) $wpdb->delete( EntriesTable::table_name(), [ 'email' => $email ] );
	}

	/** @return array[] */
	public function get_by_email( string $email ): array {
		global $wpdb;
		$table = EntriesTable::table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC", $email ), ARRAY_A ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
```

- [ ] **Step 4: Run tests + commit**

```bash
vendor/bin/phpunit --filter EntriesRepositoryTest
git add includes/Entries/EntriesRepository.php tests/Unit/Entries/EntriesRepositoryTest.php
git commit -m "feat: entries repository with tested row shaping"
```

### Task 22: `Evaluation` — the server-side calculation authority

**Files:**
- Create: `includes/Logic/Evaluation.php`
- Test: `tests/Unit/Logic/EvaluationTest.php`

This is the single place where spec §6's two value maps + §8's runtime order are implemented server-side. Consumed by `CalculatorRenderer` (initial render) and `QuoteController` (authoritative recompute). **Total convention:** the grand total = the value of the LAST active `formula` field in field order (presets follow this; document in the builder UI copy later).

`Evaluation::run( array $config, array $rawValues ): array` returns:

```php
[
	'conditionValues' => [ 'express' => '1', … ],          // §6 condition value map (strings)
	'active'          => [ 'note' => true, … ],            // ConditionalLogic::active_map result
	'values'          => [ 'area' => 500000, … ],          // §6 formula value map, scaled; inactive ⇒ 0
	'lineItems'       => [ [ 'id', 'label', 'amount' /* scaled */, 'isCurrency' => bool ], … ], // active showInSummary fields
	'totalScaled'     => 1750000 | null,
	'errors'          => [ 'total' => 'div_zero', … ],     // formula failures, value coerced to 0 (§7)
]
```

- [ ] **Step 1: Write the failing test `tests/Unit/Logic/EvaluationTest.php`** (uses the real FieldSchema-normalized cleaning preset shape; mock only `apply_filters`/sanitizers as in FieldSchemaTest):

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Logic;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Logic\Evaluation;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class EvaluationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	private function config(): array {
		return FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'area', 'type' => 'slider', 'label' => 'Area', 'min' => 10, 'max' => 500, 'default' => 50, 'showInSummary' => true ],
			[ 'id' => 'service', 'type' => 'radio', 'label' => 'Service', 'showInSummary' => true, 'options' => [
				[ 'value' => 'opt_std', 'label' => 'Standard', 'price' => 2.5 ],
				[ 'value' => 'opt_deep', 'label' => 'Deep', 'price' => 4 ],
			] ],
			[ 'id' => 'express', 'type' => 'toggle', 'label' => 'Express', 'price' => 50 ],
			[ 'id' => 'discount_note', 'type' => 'heading', 'label' => 'Discount!', 'conditions' => [
				[ 'field' => 'area', 'operator' => 'gt', 'value' => '100' ],
			], 'conditionMatch' => 'all', 'conditionAction' => 'show' ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'Total', 'showInSummary' => true,
				'expression' => '{area} * {service} + {express}' ],
		] ] );
	}

	public function test_happy_path_total_and_line_items(): void {
		$r = Evaluation::run( $this->config(), [ 'area' => '50', 'service' => 'opt_deep', 'express' => '1' ] );
		$this->assertSame( 2500000, $r['totalScaled'] ); // 50*4 + 50 = 250
		$this->assertSame( [], $r['errors'] );
		$ids = array_column( $r['lineItems'], 'id' );
		$this->assertSame( [ 'area', 'service', 'total' ], $ids );
		$this->assertFalse( $r['active']['discount_note'] );
	}

	public function test_condition_values_follow_spec_table(): void {
		$r = Evaluation::run( $this->config(), [ 'area' => '150', 'service' => 'opt_std', 'express' => '' ] );
		$this->assertSame( '150', $r['conditionValues']['area'] );
		$this->assertSame( 'opt_std', $r['conditionValues']['service'] ); // slug, not price
		$this->assertSame( '', $r['conditionValues']['express'] );
		$this->assertTrue( $r['active']['discount_note'] ); // area > 100
	}

	public function test_invalid_inputs_are_coerced_not_trusted(): void {
		$r = Evaluation::run( $this->config(), [ 'area' => '999999', 'service' => 'opt_hax', 'express' => 'yes' ] );
		$this->assertSame( 5000000, $r['values']['area'] );   // clamped to max 500
		$this->assertSame( 0, $r['values']['service'] );      // unknown slug ⇒ no selection ⇒ 0
		$this->assertSame( 500000, $r['values']['express'] ); // any truthy raw ⇒ on (price 50)
		$r2 = Evaluation::run( $this->config(), [ 'express' => 0 ] );
		$this->assertSame( 0, $r2['values']['express'] );      // JSON numeric zero ⇒ off (not the string-strict trap)
	}

	public function test_default_values_used_when_missing(): void {
		$r = Evaluation::run( $this->config(), [] );
		$this->assertSame( 500000, $r['values']['area'] ); // default 50
	}

	public function test_broken_formula_yields_zero_and_error(): void {
		$config = $this->config();
		$config['fields'][4]['expression'] = '{area} / 0';
		$r = Evaluation::run( $config, [ 'area' => '50' ] );
		$this->assertSame( 0, $r['totalScaled'] );
		$this->assertSame( 'div_zero', $r['errors']['total'] );
	}

	public function test_inactive_field_contributes_zero(): void {
		$config = $this->config();
		// Hide express unless area > 100.
		$config['fields'][2]['conditions']      = [ [ 'field' => 'area', 'operator' => 'gt', 'value' => '100' ] ];
		$config['fields'][2]['conditionMatch']  = 'all';
		$config['fields'][2]['conditionAction'] = 'show';
		$r = Evaluation::run( $config, [ 'area' => '50', 'service' => 'opt_std', 'express' => '1' ] );
		$this->assertSame( 0, $r['values']['express'] );
		$this->assertSame( 1250000, $r['totalScaled'] ); // 50*2.5 only
	}

	public function test_checkbox_group_sums_and_joins(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'extras', 'type' => 'checkbox_group', 'label' => 'Extras', 'options' => [
				[ 'value' => 'opt_a', 'label' => 'A', 'price' => 10 ],
				[ 'value' => 'opt_b', 'label' => 'B', 'price' => 5 ],
			] ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'T', 'expression' => '{extras}', 'showInSummary' => true ],
		] ] );
		$r = Evaluation::run( $config, [ 'extras' => [ 'opt_a', 'opt_b', 'opt_zzz' ] ] );
		$this->assertSame( 150000, $r['totalScaled'] );
		$this->assertSame( 'opt_a,opt_b', $r['conditionValues']['extras'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**, then **Step 3: Create `includes/Logic/Evaluation.php`**

```php
<?php
namespace Alovio\Calculator\Logic;

use Alovio\Calculator\Fields\FieldTypes;
use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\FormulaGraph;

final class Evaluation {

	public static function run( array $config, array $rawValues ): array {
		$fields = $config['fields'];

		$conditionValues = self::condition_values( $fields, $rawValues );
		$active          = ConditionalLogic::active_map( [ 'fields' => $fields ], $conditionValues );

		// §6 formula value map for inputs; inactive ⇒ 0.
		$values = [];
		foreach ( $fields as $field ) {
			if ( ! FieldTypes::is_referenceable( $field['type'] ) || 'formula' === $field['type'] ) {
				continue;
			}
			$values[ $field['id'] ] = ( $active[ $field['id'] ] ?? true )
				? self::input_amount( $field, $rawValues[ $field['id'] ] ?? null )
				: 0;
		}

		// Formulas in dependency order (§7/§8).
		$errors   = [];
		$formulas = [];
		$asts     = [];
		foreach ( $fields as $field ) {
			if ( 'formula' !== $field['type'] ) {
				continue;
			}
			try {
				$ast                        = Formula::compile( $field['expression'] );
				$asts[ $field['id'] ]       = $ast;
				$formulas[ $field['id'] ]   = Formula::references( $ast );
			} catch ( FormulaError $e ) {
				$errors[ $field['id'] ] = $e->getErrorCode();
				$formulas[ $field['id'] ] = [];
			}
		}
		$graph = FormulaGraph::order( $formulas );
		foreach ( $graph['cycles'] as $id ) {
			$errors[ $id ]  = 'cycle';
			$values[ $id ]  = 0;
		}
		foreach ( $graph['order'] as $id ) {
			if ( isset( $errors[ $id ] ) || ! isset( $asts[ $id ] ) ) {
				$values[ $id ] = 0;
				continue;
			}
			if ( false === ( $active[ $id ] ?? true ) ) {
				$values[ $id ] = 0; // Inactive formulas contribute 0, skip evaluation (§6).
				continue;
			}
			try {
				$values[ $id ] = Formula::evaluate( $asts[ $id ], $values );
			} catch ( FormulaError $e ) {
				$errors[ $id ] = $e->getErrorCode();
				$values[ $id ] = 0;
			}
		}

		// Summary line items + grand total (= last active formula in field order).
		$lineItems   = [];
		$totalScaled = null;
		foreach ( $fields as $field ) {
			$id = $field['id'];
			if ( 'formula' === $field['type'] && ( $active[ $id ] ?? true ) ) {
				$totalScaled = $values[ $id ];
			}
			if ( empty( $field['showInSummary'] ) || false === ( $active[ $id ] ?? true ) || ! isset( $values[ $id ] ) ) {
				continue;
			}
			$isCurrency  = 'formula' === $field['type'] || self::is_priced( $field );
			$lineItems[] = [ 'id' => $id, 'label' => $field['label'], 'amount' => $values[ $id ], 'isCurrency' => $isCurrency ];
		}

		return [
			'conditionValues' => $conditionValues,
			'active'          => $active,
			'values'          => $values,
			'lineItems'       => $lineItems,
			'totalScaled'     => $totalScaled,
			'errors'          => $errors,
		];
	}

	/** Spec §6 condition value map. Untrusted raw input is coerced here. */
	private static function condition_values( array $fields, array $raw ): array {
		$out = [];
		foreach ( $fields as $field ) {
			if ( ! FieldTypes::is_condition_controller( $field['type'] ) ) {
				continue;
			}
			$id = $field['id'];
			$v  = $raw[ $id ] ?? null;
			switch ( $field['type'] ) {
				case 'number':
				case 'slider':
				case 'quantity':
					$out[ $id ] = (string) self::clamped_number( $field, $v );
					break;
				case 'select':
				case 'radio':
					$out[ $id ] = self::valid_slug( $field, is_string( $v ) ? $v : '' );
					break;
				case 'checkbox_group':
					$out[ $id ] = implode( ',', self::valid_slugs( $field, is_array( $v ) ? $v : [] ) );
					break;
				case 'toggle':
					$out[ $id ] = self::toggle_on( $field, $v ) ? '1' : '';
					break;
				case 'text':
					$out[ $id ] = is_string( $v ) ? trim( $v ) : '';
					break;
			}
		}
		return $out;
	}

	/** Spec §6 formula value map for a single input field (scaled). */
	private static function input_amount( array $field, $v ): int {
		switch ( $field['type'] ) {
			case 'number':
			case 'slider':
			case 'quantity':
				return DecimalMath::toScaled( self::clamped_number( $field, $v ) );
			case 'select':
			case 'radio':
				$slug = self::valid_slug( $field, is_string( $v ) ? $v : '' );
				foreach ( $field['options'] as $opt ) {
					if ( $opt['value'] === $slug ) {
						return DecimalMath::toScaled( $opt['price'] );
					}
				}
				return 0;
			case 'checkbox_group':
				$sum = 0;
				$selected = self::valid_slugs( $field, is_array( $v ) ? $v : [] );
				foreach ( $field['options'] as $opt ) {
					if ( in_array( $opt['value'], $selected, true ) ) {
						$sum = DecimalMath::add( $sum, DecimalMath::toScaled( $opt['price'] ) );
					}
				}
				return $sum;
			case 'toggle':
				return self::toggle_on( $field, $v ) ? DecimalMath::toScaled( $field['price'] ) : 0;
		}
		return 0;
	}

	private static function clamped_number( array $field, $v ): float {
		$n = is_numeric( $v ) ? (float) $v : (float) ( $field['default'] ?? 0 );
		if ( isset( $field['min'] ) && null !== $field['min'] ) {
			$n = max( (float) $field['min'], $n );
		}
		if ( isset( $field['max'] ) && null !== $field['max'] ) {
			$n = min( (float) $field['max'], $n );
		}
		return $n;
	}

	private static function valid_slug( array $field, string $v ): string {
		foreach ( $field['options'] as $opt ) {
			if ( $opt['value'] === $v ) {
				return $v;
			}
		}
		return '';
	}

	private static function valid_slugs( array $field, array $vs ): array {
		$valid = array_column( $field['options'], 'value' );
		return array_values( array_intersect( array_map( 'strval', $vs ), $valid ) );
	}

	/** Currency line items = choice/toggle (their amounts are money); numeric inputs display as plain counts. */
	private static function is_priced( array $field ): bool {
		return FieldTypes::is_choice( $field['type'] ) || 'toggle' === $field['type'];
	}

	/** JSON-tolerant on/off: null ⇒ field default; 0, 0.0, '', '0', false, [] ⇒ off; anything else ⇒ on. Single source — used by BOTH value maps so they cannot drift. */
	private static function toggle_on( array $field, $v ): bool {
		if ( null === $v ) {
			return ! empty( $field['default'] );
		}
		if ( false === $v ) {
			return false;
		}
		$s = is_scalar( $v ) ? (string) $v : '';
		return '' !== $s && '0' !== $s;
	}
}
```

NOTE: the `active_map( [ 'fields' => … ], $values )` signature used here is the verified CF signature — see Task 15 Step 6.

- [ ] **Step 4: Run** `vendor/bin/phpunit --filter EvaluationTest` — iterate to green; then full suite.

- [ ] **Step 5: Commit**

```bash
git add includes/Logic/Evaluation.php tests/Unit/Logic/EvaluationTest.php
git commit -m "feat: server-side calculation authority (value maps, active set, topo eval, summary)"
```

### Task 23: REST — calculators CRUD

**Files:**
- Create: `includes/Admin/RestController.php`
- Modify: `includes/Plugin.php` (instantiate in `boot()`: `add_action( 'rest_api_init', [ new Admin\RestController(), 'register_routes' ] );`)

Read `/Users/tahir/woo-checkout-fields/includes/Admin/RestController.php` first and keep its structure. Routes (spec §4), all with `permission_callback` = `fn() => current_user_can( 'manage_options' )`:

| Route | Handler behavior |
|---|---|
| `GET /alc/v1/calculators` | `get_posts` of `alc_calculator` (any status, `numberposts => -1`) → `[ { id, title, updated, shortcode: "[alovio_calculator id=\"N\"]" } ]` |
| `POST /alc/v1/calculators` | params: `title` (string, required), `template` (string, optional preset key) → `wp_insert_post` (status `publish`) + `FieldRepository::save( $id, $config )` where config = preset config or `FieldSchema::defaults()`; invalid template key → `WP_Error` 400. Param `duplicateOf` (int, optional): copy the CONFIG from that post (post-type-guarded like `{id}` routes) but use the supplied `title` as-is — the UI sends "Old title (copy)". Returns `{ id }` |
| `GET /alc/v1/calculators/{id}` | 404 `WP_Error` unless post exists with our post type → `{ id, title, config: FieldRepository::get(id) }` |
| `PUT /alc/v1/calculators/{id}` | params: `title?`, `config?` → `wp_update_post` title, `FieldRepository::save` config; returns saved `{ id, title, config }` (normalized — the builder re-hydrates from this) |
| `DELETE /alc/v1/calculators/{id}` | `wp_delete_post( $id, true )` → `{ deleted: true }` |

- [ ] **Step 1: Create the controller** following the CF file's idioms (one class, `register_routes()`, typed `args` with `sanitize_callback`s: `absint` for ids, `sanitize_text_field` for title; config passed raw into `FieldRepository::save` — FieldSchema is the sanitizer).
- [ ] **Step 2: Guard the post type on every `{id}` route**: helper `private function find( int $id ): ?\WP_Post` checking `get_post_type( $post ) === FieldRepository::POST_TYPE` — return `WP_Error( 'alc_not_found', …, [ 'status' => 404 ] )` otherwise. This prevents reading/writing arbitrary posts' meta through our routes.
- [ ] **Step 3: Verify** `php -l includes/Admin/RestController.php && vendor/bin/phpunit` (full-route behavior is exercised in the Chunk 8 wp-env smoke; no Brain Monkey REST tests — they'd mock everything they assert).
- [ ] **Step 4: Commit**

```bash
git add includes/Admin/RestController.php includes/Plugin.php
git commit -m "feat: calculators CRUD REST API"
```

### Task 24: REST — public quote endpoint

**Files:**
- Create: `includes/Entries/QuoteController.php`
- Modify: `includes/Plugin.php` (register alongside RestController)
- Test: `tests/Unit/Entries/QuoteControllerTest.php`

Spec §10 exactly: public route, NO nonce, honeypot, `REMOTE_ADDR`-only rate limit (5/min via transient), payload caps, server recompute via `Evaluation`, response contract `201 {ok:true}` / `400 {ok:false, code, message, fieldErrors}` / `429`.

- [ ] **Step 1: Write the failing test** for the pure validation core `QuoteController::validate_contact( array $contact, array $quoteForm ): array{contact: array, fieldErrors: array}`:

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\QuoteController;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class QuoteControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_email' )->alias( static fn( $e ) => filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '' );
		Functions\when( 'sanitize_textarea_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( '__' )->returnArg();
	}

	private function quoteForm( array $fields = [ 'name', 'email', 'phone', 'message' ] ): array {
		return [ 'enabled' => true, 'fields' => $fields, 'notifyEmail' => '', 'successMessage' => '' ];
	}

	public function test_requires_name_and_email(): void {
		$r = QuoteController::validate_contact( [ 'name' => '', 'email' => 'nope' ], $this->quoteForm() );
		$this->assertArrayHasKey( 'name', $r['fieldErrors'] );
		$this->assertArrayHasKey( 'email', $r['fieldErrors'] );
	}

	public function test_valid_contact_passes_and_is_sanitized(): void {
		$r = QuoteController::validate_contact(
			[ 'name' => ' <b>Tahir</b> ', 'email' => 'a@b.co', 'phone' => '+994', 'message' => 'hi' ],
			$this->quoteForm()
		);
		$this->assertSame( [], $r['fieldErrors'] );
		$this->assertSame( 'Tahir', $r['contact']['name'] );
	}

	public function test_fields_not_in_quote_form_are_dropped(): void {
		$r = QuoteController::validate_contact(
			[ 'name' => 'T', 'email' => 'a@b.co', 'phone' => 'x', 'message' => 'y' ],
			$this->quoteForm( [ 'name', 'email' ] )
		);
		$this->assertArrayNotHasKey( 'phone', $r['contact'] );
		$this->assertArrayNotHasKey( 'message', $r['contact'] );
	}

	public function test_oversize_values_rejected(): void {
		$r = QuoteController::validate_contact(
			[ 'name' => str_repeat( 'a', 5000 ), 'email' => 'a@b.co' ],
			$this->quoteForm()
		);
		$this->assertArrayHasKey( 'name', $r['fieldErrors'] );
	}
}
```

- [ ] **Step 2: Run to verify failure**, then **Step 3: Create `includes/Entries/QuoteController.php`**

```php
<?php
namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Fields\FieldRepository;
use Alovio\Calculator\Logic\Evaluation;

final class QuoteController {

	private const RATE_LIMIT  = 5;       // per minute per IP
	private const VALUE_LIMIT = 500;     // chars per submitted field value
	private const VALUES_MAX  = 200;     // submitted field count cap

	public function register_routes(): void {
		register_rest_route(
			'alc/v1',
			'/quote',
			[
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public by design — spec §10 (no nonce; cache-safe).
				'callback'            => [ $this, 'handle' ],
			]
		);
	}

	public function handle( \WP_REST_Request $request ) {
		if ( '' !== (string) $request->get_param( 'alc_website' ) ) { // Honeypot.
			return new \WP_REST_Response( [ 'ok' => true ], 201 );    // Pretend success to bots.
		}

		if ( ! $this->within_rate_limit() ) {
			return new \WP_REST_Response( [ 'ok' => false, 'code' => 'rate_limited', 'message' => __( 'Too many requests. Please try again in a minute.', 'alovio-calculator' ), 'fieldErrors' => [] ], 429 );
		}

		$calculator_id = absint( $request->get_param( 'calculatorId' ) );
		$post          = get_post( $calculator_id );
		if ( ! $post || FieldRepository::POST_TYPE !== get_post_type( $post ) ) {
			return $this->bad_request( 'not_found', __( 'Calculator not found.', 'alovio-calculator' ) );
		}

		$config = ( new FieldRepository() )->get( $calculator_id );
		if ( empty( $config['settings']['quoteForm']['enabled'] ) ) {
			return $this->bad_request( 'quotes_disabled', __( 'Quote requests are not enabled.', 'alovio-calculator' ) );
		}

		$rawValues = $request->get_param( 'values' );
		$rawValues = is_array( $rawValues ) ? array_slice( $rawValues, 0, self::VALUES_MAX, true ) : [];
		foreach ( $rawValues as $k => $v ) {
			if ( is_string( $v ) && strlen( $v ) > self::VALUE_LIMIT ) {
				$rawValues[ $k ] = substr( $v, 0, self::VALUE_LIMIT );
			}
			if ( is_array( $v ) ) {
				$rawValues[ $k ] = array_map( 'strval', array_slice( $v, 0, 50 ) );
			}
		}

		$validated = self::validate_contact( (array) $request->get_param( 'contact' ), $config['settings']['quoteForm'] );
		if ( ! empty( $validated['fieldErrors'] ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'code' => 'invalid', 'message' => __( 'Please correct the highlighted fields.', 'alovio-calculator' ), 'fieldErrors' => $validated['fieldErrors'] ], 400 );
		}

		// Authoritative recompute — the client's total is ignored (spec §10).
		$result = Evaluation::run( $config, $rawValues );
		$snapshot = [
			'values'      => array_map( 'sanitize_text_field', $result['conditionValues'] ), // §12: text fields carry raw visitor input — sanitize at the storage boundary (comparator semantics upstream stay untouched).
			'lineItems'   => $result['lineItems'],
			'totalScaled' => $result['totalScaled'] ?? 0,
			'currency'    => $config['settings']['currency'],
		];

		$repo     = new EntriesRepository();
		$entry_id = $repo->insert( EntriesRepository::row_from_submission( $calculator_id, $validated['contact'], $snapshot ) );

		update_option( 'alc_entry_count', (int) get_option( 'alc_entry_count', 0 ) + 1 ); // Review nudge counter (§10).
		( new EntryMailer() )->notify( $post, $config, $validated['contact'], $snapshot );

		return new \WP_REST_Response( [ 'ok' => true ], 201 );
	}

	/** Pure, unit-tested. */
	public static function validate_contact( array $contact, array $quoteForm ): array {
		$enabled = $quoteForm['fields'];
		$out     = [];
		$errors  = [];

		foreach ( $contact as $k => $v ) {
			if ( is_string( $v ) && strlen( $v ) > 2000 ) {
				$errors[ $k ] = __( 'Too long.', 'alovio-calculator' );
			}
		}

		if ( in_array( 'name', $enabled, true ) ) {
			$out['name'] = sanitize_text_field( (string) ( $contact['name'] ?? '' ) );
			if ( '' === $out['name'] && ! isset( $errors['name'] ) ) {
				$errors['name'] = __( 'Name is required.', 'alovio-calculator' );
			}
		}
		if ( in_array( 'email', $enabled, true ) ) {
			$out['email'] = sanitize_email( (string) ( $contact['email'] ?? '' ) );
			if ( '' === $out['email'] && ! isset( $errors['email'] ) ) {
				$errors['email'] = __( 'A valid email is required.', 'alovio-calculator' );
			}
		}
		if ( in_array( 'phone', $enabled, true ) ) {
			$out['phone'] = sanitize_text_field( (string) ( $contact['phone'] ?? '' ) );
		}
		if ( in_array( 'message', $enabled, true ) ) {
			$out['message'] = sanitize_textarea_field( (string) ( $contact['message'] ?? '' ) );
		}

		$errors = array_intersect_key( $errors, array_flip( $enabled ) );
		return [ 'contact' => $out, 'fieldErrors' => $errors ];
	}

	private function within_rate_limit(): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // REMOTE_ADDR only — spec §10.
		$key   = 'alc_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private function bad_request( string $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => false, 'code' => $code, 'message' => $message, 'fieldErrors' => [] ], 400 );
	}
}
```

- [ ] **Step 4: Create `includes/Entries/EntryMailer.php`** (best-effort, failures swallowed — spec §12):

```php
<?php
namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Formula\DecimalMath;

final class EntryMailer {

	public function notify( \WP_Post $calculator, array $config, array $contact, array $snapshot ): void {
		$to = $config['settings']['quoteForm']['notifyEmail'];
		if ( '' === $to ) {
			$to = get_option( 'admin_email' );
		}
		$lines   = [];
		$lines[] = sprintf( __( 'New quote request — %s', 'alovio-calculator' ), $calculator->post_title );
		foreach ( $contact as $k => $v ) {
			$lines[] = ucfirst( $k ) . ': ' . $v;
		}
		$lines[] = '';
		foreach ( $snapshot['lineItems'] as $item ) {
			$lines[] = $item['label'] . ': ' . DecimalMath::fromScaled( $item['amount'] );
		}
		$lines[] = __( 'Total', 'alovio-calculator' ) . ': ' . DecimalMath::fromScaled( $snapshot['totalScaled'] );
		$sent = wp_mail( $to, sprintf( __( '[%s] New quote request', 'alovio-calculator' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), implode( "\n", $lines ) );
		if ( ! $sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Alovio Calculator: quote notification email failed to send.' ); // §12: logged silently; the entry is already stored.
		}
	}
}
```

- [ ] **Step 5: Wire registration** in `Plugin::boot()` (same `rest_api_init` action as Task 23): `add_action( 'rest_api_init', [ new Entries\QuoteController(), 'register_routes' ] );`

- [ ] **Step 6: Run** `vendor/bin/phpunit --filter QuoteControllerTest` + `php -l` both new files. **Commit:**

```bash
git add includes/Entries/QuoteController.php includes/Entries/EntryMailer.php includes/Plugin.php tests/Unit/Entries/QuoteControllerTest.php
git commit -m "feat: public quote endpoint (honeypot, rate limit, authoritative recompute) + mailer"
```

### Task 25: Entries REST routes, CSV export, privacy hooks

**Files:**
- Create: `includes/Entries/EntriesRestController.php`, `includes/Entries/CsvExporter.php`, `includes/Entries/Privacy.php`
- Modify: `includes/Plugin.php`
- Test: `tests/Unit/Entries/CsvExporterTest.php`

- [ ] **Step 1: `EntriesRestController`** — `manage_options` routes per spec §4 using `EntriesRepository`: `GET /alc/v1/entries` (params `calculator` absint, `page`, `per_page` ≤100 → `{ rows, total }` with `snapshot` JSON-decoded per row), `PUT /alc/v1/entries/{id}` (`{ status: new|read }`), `DELETE /alc/v1/entries/{id}`. Same permission-callback idiom as Task 23; 404 semantics for `{id}` routes via `EntriesRepository::find( $id )` (returns `WP_Error` `alc_not_found`, status 404, when null).

- [ ] **Step 2: `CsvExporter`** — TDD the pure row formatter first (`tests/Unit/Entries/CsvExporterTest.php`):

```php
public function test_csv_line_escapes_and_orders_columns(): void {
	Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	$line = \Alovio\Calculator\Entries\CsvExporter::csv_row( [
		'id' => 3, 'created_at' => '2026-06-12 10:00:00', 'name' => 'A "B"', 'email' => 'a@b.co',
		'phone' => '', 'message' => "multi\nline", 'total' => '175.0000', 'status' => 'new',
		'snapshot' => '{"values":{"area":"50"}}', 'calculator_id' => 7,
	] );
	$this->assertSame( '3,7,"2026-06-12 10:00:00","A ""B""",a@b.co,,"multi line",175.0000,new,"{""values"":{""area"":""50""}}"', $line );
}
```

Implement `csv_row( array $row ): string` (RFC-4180 quoting, newlines flattened to spaces, fixed column order `id, calculator_id, created_at, name, email, phone, message, total, status, snapshot`; guard Excel formula injection — prefix any cell starting with `=`, `+`, `-` or `@` with a single quote) plus a `handle()` for `admin_post_alc_export_entries` that checks `current_user_can( 'manage_options' )` + `check_admin_referer( 'alc_export_entries' )`, sends `header( 'Content-Type: text/csv; charset=utf-8' )` and `header( 'Content-Disposition: attachment; filename=alovio-calculator-entries.csv' )`, streams the header row then every row from `EntriesRepository::all_for_export( $calculator )`, and `exit`s. (Adjust the Step 2 test expectation if the injection guard changes a sample cell — the test fixture above contains no formula-leading cells, so it stands.)

- [ ] **Step 3: `Privacy`** — register on `wp_privacy_personal_data_exporters` / `…_erasers` (id `alovio-calculator`): exporter maps `EntriesRepository::get_by_email( $email )` rows to WP's `[ group_id, group_label, item_id, data: [label,value][] ]` shape (name, email, phone, message, total, created_at); eraser calls `delete_by_email` and reports `items_removed`. Follow the structure documented at developer.wordpress.org/plugins/privacy/ — both callbacks return `'done' => true` (single page; our per-email volume is small).

- [ ] **Step 4: Wire all three in `Plugin::boot()`** (`rest_api_init`, `admin_post_alc_export_entries`, the two privacy filters).

- [ ] **Step 5: Run + commit**

```bash
vendor/bin/phpunit --filter CsvExporterTest && php -l includes/Entries/EntriesRestController.php && php -l includes/Entries/CsvExporter.php && php -l includes/Entries/Privacy.php && php -l includes/Plugin.php
git add includes/Entries/EntriesRestController.php includes/Entries/CsvExporter.php includes/Entries/Privacy.php includes/Plugin.php tests/Unit/Entries/CsvExporterTest.php
git commit -m "feat: entries admin REST, CSV export, privacy exporter/eraser"
```

---

## Chunk 6: Builder (React Admin App)

The builder framework is copied from CF and kept structurally identical; new UI is additive. **After every task in this chunk:** `npm run build` must succeed and `npm test` stay green — treat build failure as a failing test.

### Task 26: Copy builder framework + admin page mount

**Files:**
- Create (copies): `src/builder/store.js`, `src/builder/reducer.js`, `src/builder/Canvas.jsx`, `src/builder/FieldPalette.jsx`, `src/builder/App.jsx`, `src/builder/ConditionEditor.jsx`, `src/builder/FieldSettings.jsx` (+ any CSS the CF builder imports — check `ls /Users/tahir/woo-checkout-fields/src/builder/`)
- Create: `includes/Admin/AdminPage.php`, `includes/Admin/BuilderAssets.php`
- Modify: `src/index.js`, `includes/Plugin.php`

- [ ] **Step 1: Copy every file** from `/Users/tahir/woo-checkout-fields/src/builder/` into `src/builder/`, **plus the builder stylesheet** — it lives OUTSIDE that directory: `cp /Users/tahir/woo-checkout-fields/assets/css/builder.css assets/css/builder.css` (create the dir). Then sweep identifiers: `CLCF` → `ALC`, `clcf` → `alc`, store key `'clcf/builder'` (verify the actual string in `store.js`) → `'alc/builder'`, textdomain → `'alovio-calculator'`. Sweep audit: `grep -rn "clcf\|CLCF\|corelabs\|checkout" src/builder/ assets/` must return zero hits when done.

- [ ] **Step 2: Point `src/index.js` at the app:**

```js
import { createRoot } from '@wordpress/element';
import App from './builder/App';
import '../assets/css/builder.css'; // CF imports this from its index.js — without it the builder renders unstyled (webpack emits it as build/index.css, which BuilderAssets enqueues behind a file_exists guard).

const node = document.getElementById( 'alc-builder-root' );
if ( node ) {
	createRoot( node ).render( <App /> );
}
```

(If CF's `index.js` uses `render` instead of `createRoot`, mirror CF — match the `@wordpress/element` version's idiom.)

- [ ] **Step 3: Create `includes/Admin/AdminPage.php`** — top-level menu, the React mount div, nothing else:

```php
<?php
namespace Alovio\Calculator\Admin;

final class AdminPage {

	public const SLUG = 'alovio-calculator';

	public function register(): void {
		add_menu_page(
			__( 'Alovio Calculator', 'alovio-calculator' ),
			__( 'Calculator', 'alovio-calculator' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ],
			'dashicons-calculator',
			58
		);
	}

	public function render(): void {
		echo '<div id="alc-builder-root"></div>';
	}
}
```

- [ ] **Step 4: Create `includes/Admin/BuilderAssets.php`** — copy CF's `BuilderAssets.php`, rename namespace/handles, enqueue only on our page (`'toplevel_page_' . AdminPage::SLUG` hook check), and localize:

```php
wp_localize_script( 'alc-builder', 'ALC_BUILDER', [
	'root'       => esc_url_raw( rest_url( 'alc/v1/' ) ),
	'nonce'      => wp_create_nonce( 'wp_rest' ),
	'fieldTypes' => \Alovio\Calculator\Fields\FieldTypes::all(),
	'isPro'      => apply_filters( 'alc_is_pro', false ),
	'templates'  => array_map(
		static fn( $key, $t ) => [ 'key' => $key, 'title' => $t['title'], 'description' => $t['description'] ],
		array_keys( \Alovio\Calculator\Templates\Presets::all() ),
		\Alovio\Calculator\Templates\Presets::all()
	),
	'exportNonce' => wp_create_nonce( 'alc_export_entries' ),
	'adminPost'   => esc_url_raw( admin_url( 'admin-post.php' ) ),
] );
```

- [ ] **Step 5: Wire in `Plugin::boot()`:** `add_action( 'admin_menu', [ new Admin\AdminPage(), 'register' ] );` and `add_action( 'admin_enqueue_scripts', [ new Admin\BuilderAssets(), 'enqueue' ] );`

- [ ] **Step 6: Build + commit**

```bash
npm run build && ls build/index.css && php -l includes/Admin/AdminPage.php && php -l includes/Admin/BuilderAssets.php
git add src/builder src/index.js assets/css/builder.css includes/Admin/AdminPage.php includes/Admin/BuilderAssets.php includes/Plugin.php
git commit -m "feat: import builder framework from checkout-fields + admin mount"
```

### Task 27: App shell — views, calculator list, template picker

**Files:**
- Modify: `src/builder/App.jsx`
- Create: `src/builder/CalculatorList.jsx`, `src/builder/TemplatePicker.jsx`, `src/builder/api.js`

- [ ] **Step 1: Create `src/builder/api.js`** — one tiny fetch wrapper used by every view:

```js
import apiFetch from '@wordpress/api-fetch';

apiFetch.use( apiFetch.createRootURLMiddleware( window.ALC_BUILDER.root ) );
apiFetch.use( apiFetch.createNonceMiddleware( window.ALC_BUILDER.nonce ) );

export const listCalculators = () => apiFetch( { path: 'calculators' } );
export const createCalculator = ( body ) => apiFetch( { path: 'calculators', method: 'POST', data: body } );
export const getCalculator = ( id ) => apiFetch( { path: `calculators/${ id }` } );
export const saveCalculator = ( id, body ) => apiFetch( { path: `calculators/${ id }`, method: 'PUT', data: body } );
export const deleteCalculator = ( id ) => apiFetch( { path: `calculators/${ id }`, method: 'DELETE' } );
export const listEntries = ( query ) => apiFetch( { path: `entries?${ new URLSearchParams( query ) }` } );
export const updateEntry = ( id, body ) => apiFetch( { path: `entries/${ id }`, method: 'PUT', data: body } );
export const deleteEntry = ( id ) => apiFetch( { path: `entries/${ id }`, method: 'DELETE' } );
```

(`@wordpress/api-fetch` arrives via the build's extracted dependencies — no package.json change needed; verify `build/index.asset.php` lists `wp-api-fetch` after building.)

- [ ] **Step 2: Rework `App.jsx` into a view switcher.** State: `view: 'list' | 'builder' | 'entries'`, `calculatorId: number|null`. `'list'` renders `CalculatorList`; `'builder'` renders the copied CF builder body (Canvas + FieldPalette + FieldSettings) hydrated from `getCalculator( calculatorId )` instead of CF's single-group endpoint, saving via `saveCalculator( calculatorId, { title, config } )` and re-hydrating the store from the normalized response (the server may rewrite option slugs); `'entries'` renders a placeholder `<p>` in THIS task (EntriesList arrives in Task 30 — do not import it yet or the build breaks). Keep CF's dirty-state/save-button pattern intact.

- [ ] **Step 3: Create `CalculatorList.jsx`** — table of calculators from `listCalculators()`: title, updated date, shortcode `<code>` with a copy button (`navigator.clipboard.writeText`), row actions Edit (→ builder view) / Duplicate (`createCalculator( { title: title + ' (copy)', duplicateOf: id } )`) / Delete (with `window.confirm`); top bar: "Add new" (opens `TemplatePicker`) and "Entries" button (→ entries view). Use `@wordpress/components` (`Button`, `Modal`, `Notice`) — no custom CSS beyond a wrapper class.

- [ ] **Step 4: Create `TemplatePicker.jsx`** — `Modal` listing `window.ALC_BUILDER.templates` as cards (title + description) plus a "Blank calculator" card; a title `TextControl`; Create button calls `createCalculator( { title, template: key } )` then jumps straight into the builder view for the returned id.

- [ ] **Step 5: Build, manual sanity, commit** — `npm run build`; then in the wp-env sandbox (or any dev site with the plugin) confirm: list loads, create-from-template lands in the builder with the preset's fields visible, duplicate and delete work.

```bash
git add src/builder/App.jsx src/builder/CalculatorList.jsx src/builder/TemplatePicker.jsx src/builder/api.js
git commit -m "feat: builder app shell with calculator list and template picker"
```

### Task 28: FieldSettings adaptation — calculator field types + per-option price editor

**Files:**
- Modify: `src/builder/FieldSettings.jsx`, `src/builder/FieldPalette.jsx`, `src/builder/reducer.js` (only if CF's ADD_FIELD defaults need new per-type defaults)
- Create: `src/builder/OptionsEditor.jsx`

- [ ] **Step 1: Update the palette** to exactly the spec §6 free types (labels via `__()`): number, slider, select, radio, checkbox group, toggle, quantity, text, heading, html, formula. New-field defaults per type (e.g. slider `{ min: 0, max: 100, step: 1, default: 0 }`, toggle `{ price: 0 }`, formula `{ expression: '' }`).

- [ ] **Step 2: Create `OptionsEditor.jsx`** — used by select/radio/checkbox_group settings. Rows of: label `TextControl`, price `TextControl` (`type="number"`, step `0.01`), image picker (radio only; stores attachment id, shows 40px thumb, removable), remove button; "Add option" appends `{ label: '', price: 0 }`. **Image picker implementation** (this is a plain `add_menu_page` screen, NOT the block editor, so `@wordpress/block-editor`'s `MediaUpload` renders nothing here): add `wp_enqueue_media();` to `BuilderAssets::enqueue()`, then use `MediaUpload` from **`@wordpress/media-utils`** (works standalone) — or equivalently open a `wp.media` frame directly. Verify `build/index.asset.php` picks up the `wp-media-utils` dependency after building. **No slug input** — new options are sent without `value` and the server's FieldSchema assigns `opt_` slugs; the editor must preserve existing `value` keys untouched when editing (they are the stable identity conditions reference).

- [ ] **Step 3: Extend `FieldSettings.jsx`** per type: number/slider/quantity → min/max/step/default (NO price input — formulas do the multiplying, matching FieldSchema); toggle → price + default on/off; choice types → `OptionsEditor`; text → placeholder; heading → label only; html → `TextareaControl`; ALL types → "Show in summary" `CheckboxControl` + the existing conditions section. Formula fields get the `FormulaPanel` (next task) instead of a price.

- [ ] **Step 4: Build + commit**

```bash
npm run build
git add src/builder/FieldSettings.jsx src/builder/FieldPalette.jsx src/builder/OptionsEditor.jsx src/builder/reducer.js
git commit -m "feat: calculator field settings with per-option price editor"
```

### Task 29: FormulaPanel with live validation

**Files:**
- Create: `src/builder/FormulaPanel.jsx`, `src/builder/formula-validation.js`
- Modify: `src/builder/FieldSettings.jsx` (mount the panel for formula fields)
- Test: `src/builder/__tests__/formula-validation.test.js`

- [ ] **Step 1: TDD the pure validation helper** — `src/builder/__tests__/formula-validation.test.js`:

```js
import { validateExpression } from '../formula-validation';

const fields = [
	{ id: 'area', type: 'slider' },
	{ id: 'service', type: 'radio' },
	{ id: 'note', type: 'text' },     // not referenceable
	{ id: 'tax', type: 'formula', expression: '{subtotal} * 0.18' },
	{ id: 'subtotal', type: 'formula', expression: '{area} * {service}' },
];

describe( 'validateExpression', () => {
	it( 'accepts valid expressions', () => {
		expect( validateExpression( '{area} * 2', 'subtotal', fields ) ).toEqual( { ok: true, error: null } );
	} );
	it( 'flags syntax errors with the engine error code', () => {
		expect( validateExpression( '{area} +', 'subtotal', fields ).error.code ).toBe( 'syntax' );
	} );
	it( 'flags references to unknown or non-referenceable fields', () => {
		expect( validateExpression( '{ghost}', 'subtotal', fields ).error.code ).toBe( 'unknown_field' );
		expect( validateExpression( '{note}', 'subtotal', fields ).error.code ).toBe( 'unknown_field' );
	} );
	it( 'flags cycles introduced by the draft expression', () => {
		expect( validateExpression( '{tax} + 1', 'subtotal', fields ).error.code ).toBe( 'cycle' );
	} );
} );
```

- [ ] **Step 2: Implement `src/builder/formula-validation.js`** using the shared engine: `compile` (catch `FormulaError` → `{ ok: false, error: { code, message, pos } }`); check every `references()` id is in the referenceable set (number/slider/select/radio/checkbox_group/toggle/quantity/formula minus self); rebuild the formula graph with the draft expression substituted and report `cycle` if `orderFormulas` returns the field in `cycles`. Run `npm test -- formula-validation` → green.

- [ ] **Step 3: Create `FormulaPanel.jsx`** — `TextareaControl` for the expression; on change (debounced 300ms) run `validateExpression` and render a red `Notice` with the message + a caret position hint, or a green "Formula OK" hint; a `SelectControl` "Insert field" listing referenceable sibling fields that inserts `{id}` at the cursor; helper text documenting the function set (`if, min, max, round, ceil, floor, abs` — min/max take 2–8 arguments; leading-dot literals like `.5` and unary `+` are not supported, write `0.5`). Errors NEVER block saving (spec §7) — the panel is advisory.

- [ ] **Step 4: Build + test + commit**

```bash
npm run build && npm test
git add src/builder/FormulaPanel.jsx src/builder/formula-validation.js src/builder/__tests__/formula-validation.test.js src/builder/FieldSettings.jsx
git commit -m "feat: formula panel with live engine-backed validation"
```

### Task 30: ConditionEditor adaptation + EntriesList + SettingsTab

**Files:**
- Modify: `src/builder/ConditionEditor.jsx`, `src/builder/App.jsx` (replace the entries placeholder; mount SettingsTab)
- Create: `src/builder/EntriesList.jsx`, `src/builder/SettingsTab.jsx`

- [ ] **Step 1: Adapt `ConditionEditor.jsx`** (CF copy) to the spec §6 contract:
  - Controller dropdown: sibling fields filtered to controller types (`number, slider, select, radio, checkbox_group, toggle, quantity, text`) — formula/heading/html excluded; remove CF's `@context-token` source machinery entirely (calculator has no external sources).
  - Operators: the five engine operators, labeled `is / is not / contains / greater than / less than`.
  - Value input adapts to the controller type: select/radio/checkbox_group → `SelectControl` of the controller's option **labels** storing the option **slug**; toggle → On/Off `SelectControl` storing `'1'`/`''`; number/slider/quantity → numeric `TextControl`; text → plain `TextControl`.
  - Keep CF's multi-row UI + all/any `conditionMatch` selector; action selector reduced to show/hide.

- [ ] **Step 2: Create `EntriesList.jsx`** — calculator filter `SelectControl`, paginated table from `listEntries( { calculator, page, per_page: 20 } )` (columns: date, name, email, total, status badge), row click opens a `Modal` rendering the snapshot line items + values, actions mark-read (`updateEntry`)/delete, and an "Export CSV" `Button` linking `\`${ adminPost }?action=alc_export_entries&calculator=${ id }&_wpnonce=${ exportNonce }\``.

- [ ] **Step 3: Create `SettingsTab.jsx`** (per-calculator, edits `config.settings` in the store): currency symbol/position/decimals/separators controls; theme accent `ColorPicker`; quoteForm enabled `ToggleControl`, field checkboxes (name/email always on + disabled), notify email, success message. Mount as a tab next to the field canvas (reuse CF's tab pattern if it has one; otherwise a simple `TabPanel` from `@wordpress/components` with tabs Fields | Settings).

- [ ] **Step 4: Build + manual sanity + commit**

```bash
npm run build
git add src/builder/ConditionEditor.jsx src/builder/EntriesList.jsx src/builder/SettingsTab.jsx src/builder/App.jsx
git commit -m "feat: condition editor (calculator contract), entries view, settings tab"
```

---

## Chunk 7: Front End — Rendering, Live Calculation, Quote Form

### Task 31: `CurrencyFormatter` (PHP + JS, mirrored)

**Files:**
- Create: `includes/Frontend/CurrencyFormatter.php`, `src/shared/currency.js`
- Test: `tests/Unit/Frontend/CurrencyFormatterTest.php`, `src/shared/__tests__/currency.test.js`

- [ ] **Step 1: Write both failing tests** — same cases each side:

PHP (`tests/Unit/Frontend/CurrencyFormatterTest.php`):

```php
<?php
namespace Alovio\Calculator\Tests\Unit\Frontend;

use Alovio\Calculator\Frontend\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase {

	private const CUR = [ 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ];

	public function test_formats_scaled_amounts(): void {
		$this->assertSame( '$1,234.50', CurrencyFormatter::format( 12345000, self::CUR ) );
		$this->assertSame( '$0.00', CurrencyFormatter::format( 0, self::CUR ) );
		$this->assertSame( '-$12.30', CurrencyFormatter::format( -123000, self::CUR ) );
	}

	public function test_position_after_and_custom_separators(): void {
		$cur = [ 'symbol' => '₼', 'position' => 'after', 'decimals' => 2, 'thousandSep' => ' ', 'decimalSep' => ',' ];
		$this->assertSame( '1 234,50₼', CurrencyFormatter::format( 12345000, $cur ) );
	}

	public function test_decimals_zero(): void {
		$cur = array_merge( self::CUR, [ 'decimals' => 0 ] );
		$this->assertSame( '$1,235', CurrencyFormatter::format( 12345000, $cur ) ); // rounds half-away
	}
}
```

JS (`src/shared/__tests__/currency.test.js`): the same three tests against `formatCurrency( scaled, currency )` from `../currency` with identical expected strings.

- [ ] **Step 2: Implement both.** PHP:

```php
<?php
namespace Alovio\Calculator\Frontend;

use Alovio\Calculator\Formula\DecimalMath;

final class CurrencyFormatter {

	public static function format( int $scaled, array $currency ): string {
		$decimals = (int) $currency['decimals'];
		$rounded  = DecimalMath::roundToDecimals( $scaled, $decimals );
		$sign     = $rounded < 0 ? '-' : '';
		$abs      = abs( $rounded );
		$int      = intdiv( $abs, DecimalMath::SCALE );
		$fracPart = substr( str_pad( (string) ( $abs % DecimalMath::SCALE ), 4, '0', STR_PAD_LEFT ), 0, $decimals );
		$intStr   = number_format( $int, 0, '', $currency['thousandSep'] );
		$number   = $intStr . ( $decimals > 0 ? $currency['decimalSep'] . $fracPart : '' );
		return 'after' === $currency['position']
			? $sign . $number . $currency['symbol']
			: $sign . $currency['symbol'] . $number;
	}
}
```

JS mirror in `src/shared/currency.js` using `roundToDecimals` from `./formula/decimal` and a manual thousands-grouping loop (NOT `toLocaleString` — separators come from settings, not the browser locale).

- [ ] **Step 3: Run both suites + commit**

```bash
vendor/bin/phpunit --filter CurrencyFormatterTest && npm test -- currency
git add includes/Frontend/CurrencyFormatter.php src/shared/currency.js tests/Unit/Frontend/CurrencyFormatterTest.php src/shared/__tests__/currency.test.js
git commit -m "feat: mirrored currency formatter (settings-driven, locale-independent)"
```

### Task 32: `CalculatorRenderer` (server render, TDD)

**Files:**
- Create: `includes/Frontend/CalculatorRenderer.php`
- Test: `tests/Unit/Frontend/CalculatorRendererTest.php`

Renders (spec §8): wrapper `.alc-calculator[data-alc-id]` with the accent CSS custom property; per-field wrappers `.alc-field[data-alc-field="{id}"]` (inactive ones get `hidden`); the JSON config payload `<script type="application/json" class="alc-config">`; the sticky summary `.alc-summary` with server-computed initial line items + total (via `Evaluation::run( $config, [] )` — defaults); the quote form (when enabled) with honeypot. **All output escaped**; config embedded with `wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP )`. The embedded payload contains ONLY what the front end needs: `fields` (without admin-only noise), `settings.currency`, `settings.quoteForm.enabled/fields/successMessage`, `quoteEndpoint` (`rest_url( 'alc/v1/quote' )`), `calculatorId` — never `notifyEmail`.

- [ ] **Step 1: Write the failing test** (Brain Monkey aliases: `esc_attr`/`esc_html` → `htmlspecialchars`, `esc_url` → identity, `wp_json_encode` → `json_encode` with the flags, `rest_url` → fixed string, `__` → identity, plus the FieldSchema sanitizer set from EvaluationTest). Assertions:
  - output contains `data-alc-id="7"` and one `class="alc-config"` script whose decoded JSON has `fields`, `settings.currency`, `quoteEndpoint`, and **no** `notifyEmail` anywhere in the raw HTML;
  - a field labeled `<b>Bold</b>` appears entity-escaped (`&lt;b&gt;`), never raw;
  - the `discount_note` field (condition `area > 100`, default 50) renders with the `hidden` attribute; the always-active fields don't;
  - the summary contains the initial total formatted via `CurrencyFormatter` for default values (compute the expected string by hand from the test config);
  - with `quoteForm.enabled = true` the output contains `name="alc_website"` (honeypot) and inputs for exactly the enabled contact fields; with it disabled, no `<form` at all;
  - `aria-live="polite"` present on the total element;
  - every summary row carries `data-alc-line="{field-id}"` and the total element carries `data-alc-total` (the two hooks Task 34's `summary.js` matches on);
  - the decoded payload pins `calculatorId`, `settings.quoteForm.fields`, and a non-empty `successMessage` (the renderer resolves an empty stored `successMessage` to the translated default `__( "Thanks! We'll be in touch shortly.", 'alovio-calculator' )` — the frontend bundle has no wp-i18n, so the translated fallback MUST ship in the payload).

- [ ] **Step 2: Implement** `CalculatorRenderer::render( int $id, array $config ): string`. Structure (keep each helper ≤40 lines; one `render_field` switch delegating to per-type private methods):
  - inputs: number/quantity → `<input type="number">` with min/max/step/value; slider → `<input type="range">` + live value `<output>`; select → `<select>`; radio/checkbox_group → fieldset/legend with labeled inputs (radio: image thumb via `wp_get_attachment_image` when `image` set); toggle → labeled checkbox; text → `<input type="text">`; heading → `<h3>`; html → `wp_kses_post` content (already normalized, re-kses anyway); formula → `.alc-line[data-alc-field]` showing its initial value (currency-formatted).
  - All initial visibility/values come from one `Evaluation::run( $config, [] )` call — the same authority the quote endpoint uses (no FOUC, JS-less static output, spec §8).
  - Summary panel: `<aside class="alc-summary">` with a `<ul>` of rows `<li data-alc-line="{id}"><span class="alc-line-label">…</span><span class="alc-line-value">…</span></li>` for each initial line item, and the total element `<p class="alc-total" aria-live="polite" data-alc-total>` — these `data-alc-line`/`data-alc-total` attributes are the contract `summary.js` (Task 34) updates against.
  - Quote form: enabled contact inputs + honeypot `<input type="text" name="alc_website" class="alc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">` + submit button + empty `.alc-quote-feedback` div.

- [ ] **Step 3: Run** `vendor/bin/phpunit --filter CalculatorRendererTest` → green; full suite green.

- [ ] **Step 4: Commit**

```bash
git add includes/Frontend/CalculatorRenderer.php tests/Unit/Frontend/CalculatorRendererTest.php
git commit -m "feat: server-side calculator renderer (escaped, initial state via Evaluation)"
```

### Task 33: Shortcode, block, conditional asset enqueue

**Files:**
- Create: `includes/Frontend/Shortcode.php`, `includes/Frontend/FrontendAssets.php`, `src/block/block.json`, `src/block/index.js`, `src/block/edit.js`
- Modify: `webpack.config.js` (third entry — named `'block/index'`, see Step 2), `includes/Plugin.php`

- [ ] **Step 1: `Shortcode.php`** — `add_shortcode( 'alovio_calculator', … )`: `absint` the `id` attr, verify post type, `FieldRepository::get`, mark assets needed (`FrontendAssets::mark_needed()`), return `CalculatorRenderer::render`. Unknown/missing id → `''` (admins with `manage_options` get a visible `<p>` notice instead — debuggability without leaking to visitors).

- [ ] **Step 2: Block** — `src/block/block.json`:

```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "alovio/calculator",
    "title": "Alovio Calculator",
    "category": "widgets",
    "icon": "calculator",
    "description": "Embed a cost/quote calculator.",
    "textdomain": "alovio-calculator",
    "attributes": { "calculatorId": { "type": "number", "default": 0 } },
    "supports": { "html": false },
    "editorScript": "file:./index.js"
}
```

`edit.js`: `useBlockProps`, fetch the calculator list via `@wordpress/api-fetch` (admin context — REST nonce available), `SelectControl` in `InspectorControls` + `Placeholder` with the same select when `calculatorId` is 0, and `<ServerSideRender block="alovio/calculator" attributes={ attributes } />` once selected. `index.js`: `registerBlockType( metadata, { edit, save: () => null } )` (dynamic block).
**Webpack entry name matters:** add the entry as `'block/index': './src/block/index.js'` (NOT `block:`) so the bundle emits at `build/block/index.js` — `block.json`'s `"editorScript": "file:./index.js"` resolves relative to the copied json, and a flat `build/block.js` would silently make it load the wrong bundle (the admin-builder `index.js`). wp-scripts copies `src/block/block.json` to `build/block/block.json` automatically.
PHP registration in `Plugin::init()`: `register_block_type( ALC_DIR . 'build/block', [ 'render_callback' => …same handler as the shortcode… ] );` — after building, verify `ls build/block/` shows both `block.json` and `index.js` side by side.

- [ ] **Step 3: `FrontendAssets.php`** — registers `alc-frontend` script (`build/frontend.js` + asset deps) and `alc-frontend` style on `wp_enqueue_scripts` but **enqueues only when marked needed** (static flag set by shortcode/block render; plus a `has_block( 'alovio/calculator' )` / `has_shortcode` pre-check in `wp` action for footer-printed styles). Late marking is handled by calling `wp_enqueue_script` directly inside `mark_needed()` if `wp_enqueue_scripts` already fired.

- [ ] **Step 4: Build, verify `build/block/index.js` + `build/block/block.json` sit side by side, `php -l` all new files, full PHPUnit, commit**

```bash
npm run build && ls build/block/ && vendor/bin/phpunit
git add src/block webpack.config.js includes/Frontend/Shortcode.php includes/Frontend/FrontendAssets.php includes/Plugin.php
git commit -m "feat: shortcode + dynamic block embedding with conditional assets"
```

### Task 34: Front-end live calculation (`compute.js` + wiring)

**Files:**
- Create: `src/frontend/compute.js`, `src/frontend/calculator.js`, `src/frontend/summary.js`
- Modify: `src/frontend.js`
- Test: `src/frontend/__tests__/compute.test.js`

- [ ] **Step 1: TDD `compute.js`** — the client mirror of `Evaluation` (§6 maps + §8 order). Pure functions, no DOM:

```js
// compute.js exports:
// prepare( fields )  → { asts: {id: ast|null}, errors: {id: code}, order: string[] } — compile once at init
// conditionValues( fields, rawValues ) → string map per spec §6 table
// run( fields, prepared, rawValues )   → { active, values, lineItems, totalScaled }
```

`src/frontend/__tests__/compute.test.js` ports these EvaluationTest cases 1:1 (same expected numbers): happy-path total/line items, condition-value table row checks, invalid-input coercion (clamp/unknown slug/toggle truthiness incl. numeric 0 ⇒ off), inactive-contributes-zero, checkbox sum+join, broken-formula→0. IMPORTANT: the JS configs must be the **hand-normalized shape** (what the embedded payload actually carries after PHP's `FieldSchema::normalize` — explicit `conditions: []`, `conditionMatch`, `conditionAction`, numeric option prices), NOT the raw literals from the PHP test, since JS cannot call `normalize`. Copy the expected values from the PHP test verbatim — parity with the PHP authority is the point.

- [ ] **Step 2: Implement `compute.js`** using `src/shared/formula` (compile/evaluate/orderFormulas/toScaled) and `src/frontend/conditional-logic.js`'s `activeMap`. Then run `npm test -- compute` → green.

- [ ] **Step 3: `calculator.js`** — DOM wiring per instance: read config JSON; `prepare()` once; collect raw values from inputs (`[data-alc-field]` scoping; checkbox groups → array; toggle → checked); on delegated `input`/`change`: `run()` → toggle `hidden` per `active` (and `aria-hidden`), update each summary line + slider `<output>`, update total via `formatCurrency`. Total element keeps `aria-live="polite"` from server markup.

- [ ] **Step 4: `summary.js`** — render line items into the server-rendered `.alc-summary` (match rows by `[data-alc-line]` id, add/remove rows as activity changes; write the total into `[data-alc-total]`); mobile dock handled purely in CSS (Task 36).

- [ ] **Step 5: `src/frontend.js` entry:**

```js
import { initCalculators } from './frontend/calculator';

document.addEventListener( 'DOMContentLoaded', () => initCalculators( document ) );
```

- [ ] **Step 6: Build + test + commit**

```bash
npm run build && npm test
git add src/frontend/compute.js src/frontend/calculator.js src/frontend/summary.js src/frontend.js src/frontend/__tests__/compute.test.js
git commit -m "feat: live front-end calculation mirroring server Evaluation"
```

### Task 35: Quote form submission UX

**Files:**
- Create: `src/frontend/quote-form.js`
- Modify: `src/frontend/calculator.js` (wire it)

- [ ] **Step 1: Implement per the spec §10 response contract:** on submit (preventDefault): disable button; POST `quoteEndpoint` JSON `{ calculatorId, values: <current raw values>, contact: {…}, alc_website: <honeypot input value> }` via `fetch` (no nonce header — public endpoint); handle:
  - `201` → replace form contents with the success message (`settings.quoteForm.successMessage` or the default string, which ships in the config payload already translated), clear contact inputs — calculator selections persist;
  - `400` → render `fieldErrors` as `.alc-field-error` messages under the matching inputs + the top-level message in `.alc-quote-feedback`;
  - `429` / network failure → generic retry message in `.alc-quote-feedback`;
  - always re-enable the button on failure.

- [ ] **Step 2: Build; manual smoke in the sandbox** (submit happy path → entry appears in admin Entries view + mail logged; submit invalid email → inline error; 6 rapid submits → 429 message).

- [ ] **Step 3: Commit**

```bash
git add src/frontend/quote-form.js src/frontend/calculator.js
git commit -m "feat: quote form submission with full response-contract UX"
```

### Task 36: Front-end stylesheet

**Files:**
- Create: `src/frontend/frontend-style.scss` — NOT named `style.*`: wp-scripts' default config extracts `style.*` imports into a separate `style-frontend.css`, while this name emits the expected `build/frontend.css` alongside the JS. Import it from `src/frontend.js`; `FrontendAssets` registers exactly `build/frontend.css` (verify with `ls build/` after building — if your wp-scripts version emits a different name, point the registration at the real file).

- [ ] **Step 1: Implement the single theme (spec §8):** everything namespaced `.alc-`; design tokens as CSS custom properties on `.alc-calculator` (`--alc-accent` set inline by the renderer, `--alc-radius: 8px`, font inherited from the theme); two-column layout (fields | summary) via CSS grid collapsing to one column under 720px with `.alc-summary` becoming `position: sticky; bottom: 0` (mobile dock); visible focus states (`:focus-visible` outline using the accent); `.alc-hp` visually hidden (absolute, 1px, clip) — NOT `display:none` (bots skip those); logical properties (`margin-inline-start` etc.) for RTL safety; `.alc-field[hidden] { display: none !important; }` to beat theme resets; error/success message styles.

- [ ] **Step 2: Build and check the budget** (spec §8: front-end JS ≤ 30 KB gzipped):

```bash
npm run build && gzip -c build/frontend.js | wc -c
```
Expected: < 30720. If over: verify no `@wordpress/*` packages leaked into the frontend bundle (`cat build/frontend.asset.php` — dependencies must be empty or `wp-i18n` at most; the shared formula engine and DOM code need nothing else).

- [ ] **Step 3: Commit**

```bash
git add src/frontend/frontend-style.scss src/frontend.js includes/Frontend/FrontendAssets.php
git commit -m "feat: front-end theme (custom-prop tokens, sticky summary, RTL-safe)"
```

---

## Chunk 8: Pro Gates, Polish, QA, Release Prep

### Task 37: ProModule stub + single upsell surface

**Files:**
- Create: `includes/Pro/ProModule.php`
- Modify: `includes/Plugin.php`, `src/builder/App.jsx`

- [ ] **Step 1: `ProModule.php`** — the §15 gates, no Pro behavior:

```php
<?php
namespace Alovio\Calculator\Pro;

final class ProModule {

	/** All Pro gating flows through these filters; the future Pro add-on plugin hooks them. */
	public static function register(): void {
		// Intentionally empty in the free plugin. Documented gates:
		// alc_is_pro (bool), alc_field_types (array), alc_formula_functions (array), alc_price_modes (array).
	}
}
```

- [ ] **Step 2: Builder "Pro" tab** — one additional tab in the App's TabPanel listing the planned Pro features (multi-step wizard, PDF quotes, repeaters, image option styles, webhooks, analytics) as static text + a link to `https://alovio.org/calculator` — shown only when `! window.ALC_BUILDER.isPro`. No admin notices, no banners anywhere else (Guideline 11: single, contextual, settings-page-confined upsell — spec §15).

- [ ] **Step 3: Build + commit**

```bash
npm run build
git add includes/Pro/ProModule.php includes/Plugin.php src/builder/App.jsx
git commit -m "feat: pro gating filters + single contextual upsell tab"
```

### Task 38: Review nudge + global settings + uninstall

**Files:**
- Create: `includes/Admin/ReviewNudge.php`, `uninstall.php`
- Modify: `includes/Admin/RestController.php` (global settings route), `src/builder/CalculatorList.jsx`, `includes/Plugin.php` (wire ReviewNudge + the `admin_post_alc_dismiss_review` handler)

- [ ] **Step 1: `ReviewNudge.php`** — `admin_notices` on our admin page only: when `(int) get_option( 'alc_entry_count', 0 ) >= 3` and `! get_option( 'alc_review_dismissed' )`, render a dismissible notice ("Your calculators have collected 3 quote requests — if Alovio Calculator is working for you, a review on WordPress.org helps a lot") with a wp.org review link and a dismiss link hitting `admin_post_alc_dismiss_review` (nonce-checked, sets the option). Behavior (unambiguous): show while entry count ≥ 3 AND not dismissed; dismissing is permanent. No other variants.

- [ ] **Step 2: Global settings route + UI** — `GET/PUT /alc/v1/settings` (`manage_options`): `{ deleteOnUninstall: bool }` ↔ option `alc_delete_on_uninstall`. In `CalculatorList.jsx` footer add a "Plugin settings" disclosure with the single `ToggleControl` ("Delete all plugin data on uninstall").

- [ ] **Step 3: `uninstall.php`** (spec §5):

```php
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function alc_uninstall_site(): void {
	if ( ! get_option( 'alc_delete_on_uninstall' ) ) {
		return;
	}
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}alc_entries" ); // phpcs:ignore WordPress.DB
	// 'any' would skip trash/auto-draft (exclude_from_search statuses) — query ids directly by post_type.
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'alc_calculator' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( [ 'alc_version', 'alc_entry_count', 'alc_review_dismissed', 'alc_delete_on_uninstall' ] as $opt ) {
		delete_option( $opt );
	}
	// Sweep any not-yet-expired rate-limiter transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient%alc\_rl\_%'" ); // phpcs:ignore WordPress.DB
}

if ( is_multisite() ) {
	foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
		switch_to_blog( (int) $site_id );
		alc_uninstall_site();
		restore_current_blog();
	}
} else {
	alc_uninstall_site();
}
```

- [ ] **Step 4: php -l everything new, build, commit**

```bash
php -l includes/Admin/ReviewNudge.php && php -l uninstall.php && npm run build
git add includes/Admin/ReviewNudge.php uninstall.php includes/Admin/RestController.php src/builder/CalculatorList.jsx includes/Plugin.php
git commit -m "feat: review nudge, opt-in uninstall cleanup, global settings"
```

### Task 39: PHPCS + WPCS + Plugin Check gates

**Files:**
- Create: `phpcs.xml.dist`
- Modify: `composer.json` (require-dev + scripts)

- [ ] **Step 1: Install standards**

```bash
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev wp-coding-standards/wpcs:"^3.0" dealerdirect/phpcodesniffer-composer-installer:"^1.0" phpcompatibility/phpcompatibility-wp:"^2.1"
```

- [ ] **Step 2: Create `phpcs.xml.dist`** — ruleset `WordPress-Extra` + `PHPCompatibilityWP` (`testVersion 7.4-`), scanning `includes/`, `alovio-calculator.php`, `uninstall.php`; exclusions: `WordPress.Files.FileName` (PSR-4 naming), `Universal.Arrays.DisallowShortArraySyntax` (the WPCS ^3.0 sniff name for short arrays — the codebase uses `[]` throughout), prefix rules configured for `alc`/`ALC`/`Alovio\Calculator` via `WordPress.NamingConventions.PrefixAllGlobals` properties; text domain check property set to `alovio-calculator`.

- [ ] **Step 3: Run and fix everything**

```bash
vendor/bin/phpcs
vendor/bin/phpcbf   # auto-fix first
vendor/bin/phpcs    # then fix the rest by hand — zero errors required (warnings reviewed individually)
```
Re-run `vendor/bin/phpunit` after fixes (formatting churn can break nothing silently — prove it).

- [ ] **Step 4: Plugin Check** — runs inside the wp-env sandbox (Task 40 sets it up):

```bash
npx wp-env run cli wp plugin install plugin-check --activate
npx wp-env run cli wp plugin check alovio-calculator
```
Expected: no ERRORS (spec §13 — these gates are mandatory; a violation post-auto-approve means plugin closure).

- [ ] **Step 5: Commit**

```bash
git add phpcs.xml.dist composer.json
git commit -m "chore: PHPCS/WPCS + PHPCompatibility gates, codebase clean"
```

### Task 40: wp-env smoke environment + release QA script

**Files:**
- Create: `.wp-env.json`, `docs/qa-checklist.md`

- [ ] **Step 1: Create `.wp-env.json`**

```json
{
    "core": null,
    "plugins": [ "." ]
}
```

Run: `npx wp-env start` → a WP sandbox at `http://localhost:8888` (admin/password). (Uses Docker — already installed on this machine.)

- [ ] **Step 2: Execute the end-to-end smoke** (this is the verification for the REST/admin code paths unit tests deliberately skipped):
  1. Activate plugin → no notices/fatals; `wp_alc_entries` table exists (`npx wp-env run cli wp db query "SHOW TABLES LIKE '%alc_entries%'"`).
  2. Admin → Calculator → create from **cleaning-price** template → builder shows the preset fields.
  3. Toggle Express in the front preview page: create a page with `[alovio_calculator id="N"]`, view it logged-out → initial totals render without JS errors; flipping inputs updates the sticky summary live; the conditional heading appears only when its rule matches; numbers match hand-computed fixture math (50×4+50=250 etc.).
  4. Insert the block in a second page, pick the calculator → ServerSideRender preview shows; front end works identically.
  5. Submit a quote (valid + invalid email + 6 rapid submissions) → 201/400/429 behaviors; entry visible in Entries view with correct snapshot/total; `npx wp-env run cli wp eval 'var_dump(get_option("alc_entry_count"));'` increments; review nudge appears at ≥3.
  6. CSV export downloads with correct columns; privacy export/erase by the test email works from Tools → Export/Erase Personal Data.
  7. Builder round-trip (the most-used path — the PUT route's only exercise): change the Express price 50→60 in the builder, Save, reload the builder AND the front page → both reflect 60 and FieldSchema re-normalization didn't mangle anything; mark an entry read, then delete it (Task 30's actions).
  8. Uninstall toggle on → delete plugin via admin → table + CPT + options gone; toggle off → data retained.
- [ ] **Step 3: Write `docs/qa-checklist.md`** capturing the matrix for every release (spec §13): the smoke above + caching plugins (LiteSpeed Cache, WP Rocket if licensed, Autoptimize: JS defer/minify on → calculator still computes, quote still submits) + themes (Astra, Kadence, GeneratePress, Twenty Twenty-Five, Hello) + `define('SCRIPT_DEBUG', true)` console-clean check + keyboard-only walkthrough (tab order, slider arrows, toggle space, submit) + a screen-reader pass on the total announcement (`aria-live`).

- [ ] **Step 4: Commit**

```bash
git add .wp-env.json docs/qa-checklist.md
git commit -m "chore: wp-env sandbox + release QA checklist"
```

### Task 41: readme.txt (full), i18n sweep, version 1.0.0, submission package

**Files:**
- Modify: `readme.txt`, `alovio-calculator.php`, `package.json`
- Create: `.distignore`

- [ ] **Step 1: Write the full `readme.txt`** per the research GTM notes (`~/wp-plugin-market-research-2026-06.md` §5). Requirements:
  - First content line of the description leads with the wedge: conditional logic — **free** — plus decimal-safe accuracy and PHP 7.4+ support.
  - Sections: Description (≤3 short paragraphs + feature bullet list), "Free vs Pro" honest table (Pro listed as "coming soon"), FAQ (≥6: "Does it process payments?" → No, by design; conditional logic free?; accuracy; CCB/CFF migration; GDPR/entries; theme compatibility), Screenshots (6 placeholders matching the template verticals), Changelog (`1.0.0 — Initial release`).
  - Long-tail template keywords woven into prose naturally: moving cost calculator, cleaning price calculator, print quote, agency estimate, salon pricing, rental cost.
  - Tags (≤5): `cost calculator, price calculator, quote calculator, calculator builder, estimation`.
  - `Tested up to:` the current WP release at submission time (verify on wordpress.org/download — do not guess).
- [ ] **Step 2: i18n sweep** — `grep -rn "__(\|_e(\|esc_html__(" includes/ src/ | grep -v "alovio-calculator"` must return zero rows missing the textdomain; run `npx wp-scripts build` then spot-check `build/index.js` for `wp.i18n` usage; confirm `wp_set_script_translations( 'alc-builder', 'alovio-calculator' )` is called in BuilderAssets (add if Task 26's copy lost it).
- [ ] **Step 3: Create `.distignore`** (excludes `src/`, `tests/`, `node_modules/`, `vendor/`, `docs/`, config files from the wp.org zip) and verify the package: `npm run build && npx wp-scripts plugin-zip` (or `rsync` per `.distignore`) → unzip into a clean wp-env and activate: everything works without `src/`/`vendor/`.
- [ ] **Step 4: Version bump to 1.0.0** — `alovio-calculator.php` header + `ALC_VERSION` + `readme.txt` stable tag + `package.json`. (This is a real release bump — the only kind allowed.)
- [ ] **Step 5: Final gates, then commit**

```bash
vendor/bin/phpunit && npm test && npm run build && vendor/bin/phpcs
git add readme.txt alovio-calculator.php package.json .distignore
git commit -m "release: 1.0.0 — readme, i18n sweep, distribution package"
```

- [ ] **Step 6: Submission checklist (manual, outside the repo):** create the private GitHub repo `74h1r/alovio-calculator` and push `main`; SVN-commit the built zip contents to the wp.org repo per the approval email (trunk + tag `1.0.0`, `assets/` for banner-772x250, banner-1544x500, icon-256, 6 screenshots — produce these from the wp-env demo site); confirm the plugin page renders the readme correctly; stay Featured-Plugins-eligible (<10K installs, updated <6 months — set a calendar reminder).

---

## Execution Notes

- **Dependency order is the chunk order.** Within a chunk, tasks are sequential. Chunks 2→3 (PHP engine before JS mirror), 4→5 (schema before REST), 5→6→7 (API before builder before front end).
- **The parity fixtures are the product's spine** — if PHP and JS ever disagree on a fixture, stop feature work and fix the engine first.
- **Never edit copied engine files** (`ConditionalLogic.php`, `conditional-logic.js`) — if they seem wrong, the integration is wrong.
- After Chunk 8, the next milestones (not in this plan): wp.org submission, demo site content, comparison pages, Pro add-on plugin (separate spec).
