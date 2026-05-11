<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Query;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\Query\QueryLimits;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\QueryLimits
 * @group Database
 */
class QueryLimitsTest extends MediaWikiIntegrationTestCase {

	public function testAnonymousUserGetsDefaultTierTimeout(): void {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$limits = QueryLimits::forUser( $user );

		$this->assertSame( 30, $limits->timeoutSeconds );
	}

	public function testAnonymousUserGetsDefaultTierMaxRows(): void {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$limits = QueryLimits::forUser( $user );

		$this->assertSame( 5000, $limits->maxRows );
	}

	public function testUserWithApiHighLimitsGetsExpensiveTierTimeout(): void {
		$user = $this->getTestUser( [ 'bot' ] )->getUser();

		$limits = QueryLimits::forUser( $user );

		$this->assertSame( 300, $limits->timeoutSeconds );
	}

	public function testUserWithApiHighLimitsGetsExpensiveTierMaxRows(): void {
		$user = $this->getTestUser( [ 'bot' ] )->getUser();

		$limits = QueryLimits::forUser( $user );

		$this->assertSame( 50000, $limits->maxRows );
	}

	public function testRespectsConfigOverridesForDefaultTier(): void {
		$this->overrideConfigValue( 'NeoWikiQueryLimits', [
			'default'   => [ 'timeoutSeconds' => 5, 'maxRows' => 100 ],
			'expensive' => [ 'timeoutSeconds' => 99, 'maxRows' => 999 ],
		] );

		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$limits = QueryLimits::forUser( $user );

		$this->assertSame( 5, $limits->timeoutSeconds );
		$this->assertSame( 100, $limits->maxRows );
	}

}
