<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Config\HashConfig;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiConfigFactory;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiConfigFactory
 */
class NeoWikiConfigFactoryTest extends TestCase {

	use HandlesNeo4jEnvOverrides;

	protected function setUp(): void {
		parent::setUp();
		$this->snapshotAndClearNeo4jEnvOverrides();
	}

	protected function tearDown(): void {
		$this->restoreNeo4jEnvOverrides();
		parent::tearDown();
	}

	public function testUnsetUrlsProduceNullInsteadOfThrowing(): void {
		$config = ( new NeoWikiConfigFactory() )->buildFromMediaWikiConfig( new HashConfig( [
			'NeoWikiEnableDevelopmentUI' => false,
			'NeoWikiNeo4jInternalWriteUrl' => null,
			'NeoWikiNeo4jInternalReadUrl' => null,
		] ) );

		$this->assertNull( $config->neo4jInternalWriteUrl );
		$this->assertNull( $config->neo4jInternalReadUrl );
		$this->assertFalse( $config->hasNeo4jBackend() );
	}

	public function testConfiguredUrlsArePassedThrough(): void {
		$config = ( new NeoWikiConfigFactory() )->buildFromMediaWikiConfig( new HashConfig( [
			'NeoWikiEnableDevelopmentUI' => false,
			'NeoWikiNeo4jInternalWriteUrl' => 'bolt://write:7687',
			'NeoWikiNeo4jInternalReadUrl' => 'bolt://read:7687',
		] ) );

		$this->assertSame( 'bolt://write:7687', $config->neo4jInternalWriteUrl );
		$this->assertSame( 'bolt://read:7687', $config->neo4jInternalReadUrl );
		$this->assertTrue( $config->hasNeo4jBackend() );
	}

	public function testEnvOverrideWinsOverConfigValue(): void {
		putenv( 'NEO4J_URL_OVERRIDE=bolt://env-write' );
		putenv( 'NEO4J_URL_READ_OVERRIDE=bolt://env-read' );

		$config = ( new NeoWikiConfigFactory() )->buildFromMediaWikiConfig( new HashConfig( [
			'NeoWikiEnableDevelopmentUI' => false,
			'NeoWikiNeo4jInternalWriteUrl' => 'bolt://config-write',
			'NeoWikiNeo4jInternalReadUrl' => 'bolt://config-read',
		] ) );

		$this->assertSame( 'bolt://env-write', $config->neo4jInternalWriteUrl );
		$this->assertSame( 'bolt://env-read', $config->neo4jInternalReadUrl );
	}

}
