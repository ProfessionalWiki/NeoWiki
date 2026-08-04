<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPagePropertyProvider;

/**
 * Guards the registration helpers themselves. Both go through the same hook, and getting that wrong
 * loses one of them silently: the test stays green because whatever was dropped simply never sees a
 * projection, so nothing it would have asserted on ever runs.
 *
 * @covers \ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase
 * @group Database
 */
class NeoWikiIntegrationTestCaseTest extends NeoWikiIntegrationTestCase {

	public function testPluginsAndProvidersRegisteredSeparatelyBothTakeEffect(): void {
		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );
		$this->registerPagePropertyProviders( new StubPagePropertyProvider( [ 'marker' => 'present' ] ) );

		$this->insertPage( 'Page seen by both registrations', 'Plain content.' );

		$this->assertCount( 1, $spy->savedPages, 'the plugin registered first still receives the page' );
		$this->assertSame(
			'present',
			$spy->savedPages[0]->getProperties()->get( 'marker' ),
			'the provider registered second still contributes its properties'
		);
	}

}
