<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use Exception;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Needs the integration base class to set the wiki's Neo4j URLs, and the test Neo4j, which requires a login,
 * to show that the credentials still reach it.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::getNeo4jClient
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::getReadOnlyNeo4jClient
 */
class Neo4jPasswordStaysOutOfErrorsTest extends NeoWikiIntegrationTestCase {

	private const string MALFORMED_URL = 'bolt://neo4j:hunter2@neo:notaport';

	/**
	 * A URL parser reads this password as port 123 of host neo4j followed by a path, so only splitting the
	 * credentials off at the last `@` keeps it out of the driver's error.
	 */
	private const string UNREACHABLE_URL = 'bolt://neo4j:123/hunter2@127.0.0.1:1';

	/**
	 * @dataProvider clientProvider
	 */
	public function testAMalformedUrlIsRejectedWithoutThePassword( callable $buildClient ): void {
		$message = $this->errorFrom( self::MALFORMED_URL, $buildClient );

		$this->assertStringContainsString( 'neo:notaport', $message );
		$this->assertStringNotContainsString( 'hunter2', $message );
	}

	public static function clientProvider(): iterable {
		yield 'write client' => [ static fn () => NeoWikiExtension::getInstance()->getNeo4jClient() ];
		yield 'read client' => [ static fn () => NeoWikiExtension::getInstance()->getReadOnlyNeo4jClient() ];
	}

	public function testAConnectionErrorLeavesOutThePassword(): void {
		$message = $this->errorFrom(
			self::UNREACHABLE_URL,
			static fn () => NeoWikiExtension::getInstance()->getNeo4jClient()->run( 'RETURN 1' )
		);

		$this->assertStringContainsString( '127.0.0.1:1', $message );
		$this->assertStringNotContainsString( 'hunter2', $message );
	}

	public function testTheClientLogsInWithTheCredentialsInTheUrl(): void {
		try {
			$result = NeoWikiExtension::getInstance()->getNeo4jClient()->run( 'RETURN 1 AS n' );
		} catch ( Exception $e ) {
			// Only an unreachable Neo4j is a reason to skip: a refused login is the failure this test is for.
			if ( str_contains( $e->getMessage(), 'Cannot connect' ) ) {
				$this->markTestSkipped( 'Neo4j not available' );
			}

			throw $e;
		}

		$this->assertSame( 1, $result->first()->get( 'n' ) );
	}

	private function errorFrom( string $url, callable $useClient ): string {
		return $this->runWithoutGraphBackend( function () use ( $url, $useClient ): string {
			$this->overrideConfigValue( 'NeoWikiNeo4jInternalReadUrl', $url );
			$this->overrideConfigValue( 'NeoWikiNeo4jInternalWriteUrl', $url );
			NeoWikiExtension::resetInstance();

			try {
				$useClient();
			} catch ( Exception $e ) {
				return $e->getMessage();
			}

			$this->fail( 'Using the Neo4j client did not fail' );
		} );
	}

}
