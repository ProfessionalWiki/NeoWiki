<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiConfig;

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

	private function newConfig( ?string $readUrl, ?string $writeUrl ): NeoWikiConfig {
		return new NeoWikiConfig(
			enableDevelopmentUIs: false,
			neo4jInternalWriteUrl: $writeUrl,
			neo4jInternalReadUrl: $readUrl,
			wikiId: 'testwiki',
			rdfBaseUri: 'https://wiki.example',
		);
	}

}
