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

	// ---- callback-level handle() coverage (QuoteControllerTest conventions: plain get_param stub, no WP_REST_Request) ----

	private function request( array $params ): object {
		return new class( $params ) {
			private $p;
			public function __construct( $p ) { $this->p = $p; }
			public function get_param( $k ) { return $this->p[ $k ] ?? null; }
		};
	}

	public function test_handle_rejects_unknown_event_with_400(): void {
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\expect( 'update_post_meta' )->never();
		$res = ( new Counter() )->handle( $this->request( array( 'calc' => 7, 'event' => 'boom' ) ) );
		$this->assertSame( 400, $res->get_status() );
	}

	public function test_handle_rejects_unknown_calculator_with_400(): void {
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'get_post_type' )->justReturn( 'post' ); // not alovio_calculator
		Functions\expect( 'update_post_meta' )->never();
		$res = ( new Counter() )->handle( $this->request( array( 'calc' => 999999, 'event' => 'view' ) ) );
		$this->assertSame( 400, $res->get_status() );
	}

	public function test_handle_rate_limited_with_429(): void {
		Functions\when( 'get_transient' )->justReturn( 20 ); // bucket already at RATE_LIMIT
		$res = ( new Counter() )->handle( $this->request( array( 'calc' => 7, 'event' => 'view' ) ) );
		$this->assertSame( 429, $res->get_status() );
	}
}
