<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup;
use TestLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;

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

}
