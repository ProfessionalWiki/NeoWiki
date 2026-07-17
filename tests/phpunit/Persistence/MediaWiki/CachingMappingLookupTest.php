<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup;
use Psr\Log\NullLogger;
use TestLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup
 */
class CachingMappingLookupTest extends TestCase {

	public function testGateUsesBindingAuthorizeRead(): void {
		// probablyCan is a UI-hint check that skips the expensive ACL hook that extensions use
		// for read restrictions; the gate must use the binding authorizeRead with the 'read'
		// action. Reverting to a hint verb, or a different action, fails this test.
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getPrefixedDBkey' )->willReturn( 'Mapping:CachingGateMapping' );

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturn( $title );

		$inner = $this->createMock( MappingLookup::class );
		$inner->expects( $this->never() )->method( 'getMapping' );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'probablyCan' )->willReturn( true );
		$authority->method( 'authorizeRead' )->willReturnCallback( function ( string $action ) {
			$this->assertSame( 'read', $action );
			return false;
		} );
		$authority->method( 'getUser' )->willReturn( new UserIdentityValue( 9999, 'Petr' ) );

		$logger = new TestLogger( true, null, true );

		$lookup = new CachingMappingLookup(
			$inner,
			$this->createMock( MappingNameLookup::class ),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$factory,
			$authority,
			$this->createMock( IConnectionProvider::class ),
			$logger,
		);

		$this->assertNull( $lookup->getMapping( new MappingName( 'CachingGateMapping' ) ) );

		// Mirrors AuthorityBasedSubjectAuthorizerTest::testDeniedReadIsLogged.
		$this->assertSame(
			[ [ 'info', 'Denied read of page {page} to {user}',
				[ 'page' => 'Mapping:CachingGateMapping', 'user' => 'Petr' ] ] ],
			$logger->getBuffer()
		);
	}

	public function testDeniedUserGetsNullEvenWhenTheMappingIsAlreadyCached(): void {
		$cache = $this->newCache();

		$allowed = new CachingMappingLookup(
			$this->newSpyLookup(),
			$this->createMock( MappingNameLookup::class ),
			$cache,
			$this->newTitleFactory( 1, 100 ),
			$this->newAuthority(),
			$this->newConnectionProvider(),
			new NullLogger(),
		);
		$allowed->getMapping( new MappingName( 'Person' ) );

		$denied = new CachingMappingLookup(
			$this->newSpyLookup(),
			$this->createMock( MappingNameLookup::class ),
			$cache,
			$this->newTitleFactory( 1, 100 ),
			$this->newAuthority( canRead: false ),
			$this->newConnectionProvider(),
			new NullLogger(),
		);

		$this->assertNull( $denied->getMapping( new MappingName( 'Person' ) ) );
	}

	private function newSpyLookup(): MappingLookup {
		return new class() implements MappingLookup {
			public Mapping $mapping;

			public function __construct() {
				$this->mapping = new Mapping(
					name: new MappingName( 'Person' ),
					schema: new SchemaName( 'Person' ),
					target: 'http://example.org/target',
					prefixes: [],
					subjectClass: 'http://example.org/Class',
					properties: new PropertyMappings( [] ),
				);
			}

			public function getMapping( MappingName $name ): ?Mapping {
				return $this->mapping;
			}

			public function getAllMappings(): array {
				return [ $this->mapping ];
			}
		};
	}

	private function newTitleFactory( int $articleId, int ...$revIds ): TitleFactory {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( $articleId );
		$title->method( 'getLatestRevID' )->willReturnOnConsecutiveCalls( ...$revIds );

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturn( $title );
		return $factory;
	}

	private function newCache(): WANObjectCache {
		return new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	private function newAuthority( bool $canRead = true ): Authority {
		$authority = $this->createMock( Authority::class );
		$authority->method( 'authorizeRead' )->willReturn( $canRead );
		$authority->method( 'getUser' )->willReturn( new UserIdentityValue( 1, 'TestUser' ) );
		return $authority;
	}

	private function newConnectionProvider(): IConnectionProvider {
		$replica = $this->createMock( IReadableDatabase::class );
		$replica->method( 'getSessionLagStatus' )->willReturn( [ 'lag' => 0, 'since' => INF ] );

		$provider = $this->createMock( IConnectionProvider::class );
		$provider->method( 'getReplicaDatabase' )->willReturn( $replica );
		return $provider;
	}

}
