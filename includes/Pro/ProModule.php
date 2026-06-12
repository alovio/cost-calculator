<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Pro;

defined( 'ABSPATH' ) || exit;

/**
 * Pro gating stub (spec §15). All Pro gating flows through these filters; the
 * future Pro ADD-ON PLUGIN (separate, Freemius-distributed — Guideline 5) hooks them:
 *
 *   alc_is_pro (bool)               — unlocks Pro UI affordances in the builder
 *   alc_field_types (array)         — additional field types
 *   alc_formula_functions (array)   — additional parser-accepted functions
 *                                     (NOTE: needs an evaluation-callback mechanism too,
 *                                     the Evaluator has no dispatch for unknown names)
 *   alc_price_modes (array)         — reserved for Pro pricing modes
 *
 * Intentionally registers nothing in the free plugin.
 */
final class ProModule {

	public static function register(): void {}
}
