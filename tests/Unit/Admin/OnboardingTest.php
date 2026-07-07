<?php
namespace Alovio\Calculator\Tests\Unit\Admin;

use Alovio\Calculator\Admin\Onboarding;
use Alovio\Calculator\Tests\TestCase;

class OnboardingTest extends TestCase {

	public function test_whatsnew_flagged_only_when_crossing_2_0_0(): void {
		$this->assertTrue( Onboarding::should_flag_whatsnew( '1.4.1', '2.0.0' ) );
		$this->assertTrue( Onboarding::should_flag_whatsnew( '1.0.0', '2.1.0' ) );
		$this->assertFalse( Onboarding::should_flag_whatsnew( '2.0.0', '2.0.1' ) );
		$this->assertFalse( Onboarding::should_flag_whatsnew( '2.0.0', '2.0.0' ) );
	}

	public function test_welcome_wins_over_whatsnew(): void {
		$this->assertSame( 'welcome', Onboarding::notice_to_show( true, true ) );
		$this->assertSame( 'welcome', Onboarding::notice_to_show( true, false ) );
		$this->assertSame( 'whatsnew', Onboarding::notice_to_show( false, true ) );
		$this->assertNull( Onboarding::notice_to_show( false, false ) );
	}
}
