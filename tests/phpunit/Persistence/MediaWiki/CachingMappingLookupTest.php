<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup
 */
class CachingMappingLookupTest extends TestCase {

	public function testDeniedMappingIsNullAndDoesNotReachTheInnerLookup(): void {
		// The gate must run before the cache and the inner lookup, so a denied Mapping is never
		// loaded and a cache hit cannot serve it either.
		$inner = $this->createMock( MappingLookup::class );
		$inner->expects( $this->never() )->method( 'getMapping' );

		$lookup = $this->newLookup( $inner, new StubPageReadAuthorizer( allowed: false ) );

		$this->assertNull( $lookup->getMapping( new MappingName( 'CachingGateMapping' ) ) );
	}

	public function testReadableMappingReachesTheInnerLookup(): void {
		$inner = $this->createMock( MappingLookup::class );
		$inner->expects( $this->once() )->method( 'getMapping' );

		$lookup = $this->newLookup( $inner, new StubPageReadAuthorizer( allowed: true ) );

		$lookup->getMapping( new MappingName( 'CachingGateMapping' ) );
	}

	private function newLookup( MappingLookup $inner, StubPageReadAuthorizer $readAuthorizer ): CachingMappingLookup {
		return new CachingMappingLookup(
			$inner,
			$this->createMock( MappingNameLookup::class ),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->newTitleFactory(),
			$readAuthorizer,
			$this->newConnectionProvider(),
		);
	}

	private function newConnectionProvider(): IConnectionProvider {
		$replica = $this->createMock( IReadableDatabase::class );
		$replica->method( 'getSessionLagStatus' )->willReturn( [ 'lag' => 0, 'since' => INF ] );

		$provider = $this->createMock( IConnectionProvider::class );
		$provider->method( 'getReplicaDatabase' )->willReturn( $replica );
		return $provider;
	}

	private function newTitleFactory(): TitleFactory {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturn( $title );
		return $factory;
	}

}
