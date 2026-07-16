<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentityValue;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup
 * @group Database
 */
class CachingMappingLookupTest extends NeoWikiIntegrationTestCase {

	public function testGateUsesBindingAuthorizeRead(): void {
		// The mapping page must exist so the gate (which runs after the existence check) is
		// reached. probablyCan=true + authorizeRead=false must deny: probablyCan is a UI-hint
		// check that skips the expensive ACL hook extensions use for read restrictions.
		$this->createMapping( 'CachingGateMapping', $this->personToEdm() );

		$inner = $this->createMock( MappingLookup::class );
		$inner->expects( $this->never() )->method( 'getMapping' );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'probablyCan' )->willReturn( true );
		$authority->method( 'authorizeRead' )->willReturnCallback( function ( string $action ) {
			$this->assertSame( 'read', $action );
			return false;
		} );
		$authority->method( 'getUser' )->willReturn( new UserIdentityValue( 9999, 'Petr' ) );

		$lookup = new CachingMappingLookup(
			$inner,
			$this->createMock( MappingNameLookup::class ),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getTitleFactory(),
			$authority,
			$this->getServiceContainer()->getConnectionProvider(),
			new NullLogger(),
		);

		$this->assertNull( $lookup->getMapping( new MappingName( 'CachingGateMapping' ) ) );
	}

	private function personToEdm(): string {
		return <<<JSON
			{
				"version": 1,
				"schema": "Person",
				"target": "edm",
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"dc": "http://purl.org/dc/elements/1.1/"
				},
				"subject": { "class": "edm:ProvidedCHO" },
				"properties": {
					"Name": { "predicate": "dc:title", "lang": "en" }
				}
			}
			JSON;
	}

}
