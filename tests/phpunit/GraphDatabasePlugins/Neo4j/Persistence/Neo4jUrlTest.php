<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jUrl;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jUrl
 */
class Neo4jUrlTest extends TestCase {

	private const string URL = 'bolt://neo4j:secret@neo4j-host:7687';
	private const string URL_WITH_DELIMITERS_IN_PASSWORD = 'bolt://neo4j:12/3?4#5 :6@7@neo4j-host:7687';

	public function testTheAddressLeavesOutTheCredentials(): void {
		$this->assertSame(
			'bolt://neo4j-host:7687',
			Neo4jUrl::fromString( self::URL )->address
		);
	}

	public function testTheCredentialsLogIn(): void {
		$this->assertEquals(
			Authenticate::basic( 'neo4j', 'secret' ),
			Neo4jUrl::fromString( self::URL )->authentication
		);
	}

	public function testAPasswordMayHoldUrlDelimiters(): void {
		$this->assertEquals(
			Authenticate::basic( 'neo4j', '12/3?4#5 :6@7' ),
			Neo4jUrl::fromString( self::URL_WITH_DELIMITERS_IN_PASSWORD )->authentication
		);
	}

	public function testTheAddressLeavesOutAPasswordHoldingUrlDelimiters(): void {
		$this->assertSame(
			'bolt://neo4j-host:7687',
			Neo4jUrl::fromString( self::URL_WITH_DELIMITERS_IN_PASSWORD )->address
		);
	}

	public function testAUrlWithoutCredentialsIsItsOwnAddress(): void {
		$this->assertSame(
			'neo4j+s://neo4j-host:7687?policy=eu',
			Neo4jUrl::fromString( 'neo4j+s://neo4j-host:7687?policy=eu' )->address
		);
	}

	public function testAUrlWithoutCredentialsDoesNotLogIn(): void {
		$this->assertEquals(
			Authenticate::disabled(),
			Neo4jUrl::fromString( 'bolt://neo4j-host:7687' )->authentication
		);
	}

	public function testAUsernameWithoutPasswordDoesNotLogIn(): void {
		$this->assertEquals(
			Authenticate::disabled(),
			Neo4jUrl::fromString( 'bolt://neo4j@neo4j-host:7687' )->authentication
		);
	}

	public function testAUrlWithoutSchemeIsRejectedWithoutQuotingIt(): void {
		try {
			Neo4jUrl::fromString( 'neo4j:hunter2@neo4j-host:7687' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringNotContainsString( 'hunter2', $e->getMessage() );
			return;
		}

		$this->fail( 'A URL without a scheme was accepted' );
	}

}
