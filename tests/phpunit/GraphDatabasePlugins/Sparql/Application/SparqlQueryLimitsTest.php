<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Application;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits
 * @group Database
 */
class SparqlQueryLimitsTest extends MediaWikiIntegrationTestCase {

	private function overrideTiers(): void {
		$this->overrideConfigValue( 'NeoWikiQueryLimits', [
			'default'   => [ 'timeoutSeconds' => 5, 'maxRows' => 100 ],
			'expensive' => [ 'timeoutSeconds' => 99, 'maxRows' => 999 ],
		] );
	}

	public function testDefaultTierReadsTheDefaultTierConfig(): void {
		$this->overrideTiers();

		$this->assertSame( 5, SparqlQueryLimits::defaultTier()->timeoutSeconds );
	}

	public function testUserWithApiHighLimitsGetsTheExpensiveTierTimeout(): void {
		$this->overrideTiers();

		$limits = SparqlQueryLimits::forUser( $this->getTestUser( [ 'bot' ] )->getUser() );

		$this->assertSame( 99, $limits->timeoutSeconds );
	}

}
