<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\ReplicaCacheOptions;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\ReplicaCacheOptions
 */
class ReplicaCacheOptionsTest extends TestCase {

	/**
	 * The below-1.47 branch is left to the CI matrix: asking for the options there is what emits the
	 * deprecation this class exists to avoid, so exercising it on a newer MediaWiki would reintroduce it.
	 */
	public function testNoOptionsAreAskedForFromMediaWiki147(): void {
		$this->assertSame( [], $this->newOptions( '1.47.0' )->forRead() );
	}

	public function testTheDatabaseIsLeftAloneFromMediaWiki147(): void {
		$provider = $this->createMock( IConnectionProvider::class );
		$provider->expects( $this->never() )->method( 'getReplicaDatabase' );

		( new ReplicaCacheOptions( $provider, '1.47.0' ) )->forRead();
	}

	public function testAPreReleaseOfMediaWiki147CountsAsMediaWiki147(): void {
		$this->assertSame( [], $this->newOptions( '1.47.0-alpha' )->forRead() );
	}

	private function newOptions( string $mediaWikiVersion ): ReplicaCacheOptions {
		return new ReplicaCacheOptions( $this->createMock( IConnectionProvider::class ), $mediaWikiVersion );
	}

}
