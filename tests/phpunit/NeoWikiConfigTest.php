<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiConfig;
use ProfessionalWiki\NeoWiki\SparqlStoreConfig;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiConfig
 */
class NeoWikiConfigTest extends TestCase {

	public function testHasNeo4jBackendWhenBothUrlsSet(): void {
		$config = $this->newConfig( readUrl: 'bolt://read', writeUrl: 'bolt://write' );

		$this->assertTrue( $config->hasNeo4jBackend() );
	}

	public function testNoNeo4jBackendWhenBothUrlsNull(): void {
		$config = $this->newConfig( readUrl: null, writeUrl: null );

		$this->assertFalse( $config->hasNeo4jBackend() );
	}

	public function testNoNeo4jBackendWhenOnlyReadUrlSet(): void {
		$config = $this->newConfig( readUrl: 'bolt://read', writeUrl: null );

		$this->assertFalse( $config->hasNeo4jBackend() );
	}

	public function testNoNeo4jBackendWhenOnlyWriteUrlSet(): void {
		$config = $this->newConfig( readUrl: null, writeUrl: 'bolt://write' );

		$this->assertFalse( $config->hasNeo4jBackend() );
	}

	public function testQueriedStoreHoldsItsOwnProjection(): void {
		$config = $this->newConfigWithStores(
			$this->newStore( 'https://qlever.example/api', 'native' )
		);

		$this->assertTrue( $config->queriedStoreHoldsProjection( 'native' ) );
	}

	public function testQueriedStoreHoldsASiblingProjectionPointedAtTheSameEndpoint(): void {
		$config = $this->newConfigWithStores(
			$this->newStore( 'https://qlever.example/api', 'native' ),
			$this->newStore( 'https://qlever.example/api', 'EDM' )
		);

		$this->assertTrue( $config->queriedStoreHoldsProjection( 'EDM' ) );
	}

	public function testProjectionAtAnotherEndpointIsNotHeldByTheQueriedStore(): void {
		$config = $this->newConfigWithStores(
			$this->newStore( 'https://qlever.example/api', 'native' ),
			$this->newStore( 'https://elsewhere.example/api', 'EDM' )
		);

		$this->assertFalse( $config->queriedStoreHoldsProjection( 'EDM' ) );
	}

	public function testUnconfiguredProjectionIsNotHeld(): void {
		$config = $this->newConfigWithStores(
			$this->newStore( 'https://qlever.example/api', 'native' ),
			$this->newStore( 'https://qlever.example/api', 'EDM' )
		);

		$this->assertFalse( $config->queriedStoreHoldsProjection( 'CIDOC' ) );
	}

	public function testProjectionNamesAreMatchedExactly(): void {
		$config = $this->newConfigWithStores(
			$this->newStore( 'https://qlever.example/api', 'EDM' )
		);

		$this->assertFalse( $config->queriedStoreHoldsProjection( 'edm' ) );
	}

	public function testNoProjectionIsHeldWithoutAConfiguredStore(): void {
		$this->assertFalse(
			$this->newConfig( readUrl: null, writeUrl: null )->queriedStoreHoldsProjection( 'native' )
		);
	}

	private function newStore( string $url, string $projection ): SparqlStoreConfig {
		return new SparqlStoreConfig(
			updateUrl: $url,
			queryUrl: $url,
			accessToken: null,
			projection: $projection,
		);
	}

	private function newConfigWithStores( SparqlStoreConfig ...$stores ): NeoWikiConfig {
		return new NeoWikiConfig(
			enableDevelopmentUIs: false,
			neo4jInternalWriteUrl: null,
			neo4jInternalReadUrl: null,
			wikiId: 'testwiki',
			rdfBaseUri: 'https://wiki.example',
			sparqlStores: $stores,
		);
	}

	private function newConfig( ?string $readUrl, ?string $writeUrl ): NeoWikiConfig {
		return new NeoWikiConfig(
			enableDevelopmentUIs: false,
			neo4jInternalWriteUrl: $writeUrl,
			neo4jInternalReadUrl: $readUrl,
			wikiId: 'testwiki',
			rdfBaseUri: 'https://wiki.example',
			sparqlStores: [],
		);
	}

}
