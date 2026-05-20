<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Application;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits
 * @group Database
 */
class Neo4jQueryLimitsTest extends MediaWikiIntegrationTestCase {

	public function testAnonymousUserGetsDefaultTierTimeout(): void {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$limits = Neo4jQueryLimits::forUser( $user );

		$this->assertSame( 30, $limits->timeoutSeconds );
	}

	public function testAnonymousUserGetsDefaultTierMaxRows(): void {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$limits = Neo4jQueryLimits::forUser( $user );

		$this->assertSame( 5000, $limits->maxRows );
	}

	public function testUserWithApiHighLimitsGetsExpensiveTierTimeout(): void {
		$user = $this->getTestUser( [ 'bot' ] )->getUser();

		$limits = Neo4jQueryLimits::forUser( $user );

		$this->assertSame( 300, $limits->timeoutSeconds );
	}

	public function testUserWithApiHighLimitsGetsExpensiveTierMaxRows(): void {
		$user = $this->getTestUser( [ 'bot' ] )->getUser();

		$limits = Neo4jQueryLimits::forUser( $user );

		$this->assertSame( 50000, $limits->maxRows );
	}

	public function testRespectsConfigOverridesForDefaultTier(): void {
		$this->overrideConfigValue( 'NeoWikiQueryLimits', [
			'default'   => [ 'timeoutSeconds' => 5, 'maxRows' => 100 ],
			'expensive' => [ 'timeoutSeconds' => 99, 'maxRows' => 999 ],
		] );

		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$limits = Neo4jQueryLimits::forUser( $user );

		$this->assertSame( 5, $limits->timeoutSeconds );
		$this->assertSame( 100, $limits->maxRows );
	}

}
