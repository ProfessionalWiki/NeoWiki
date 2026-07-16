<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Config\HashConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;
use ProfessionalWiki\NeoWiki\NeoWikiConfig;
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
			'NeoWikiRdfBaseUri' => null,
			'CanonicalServer' => 'https://wiki.example',
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
			'NeoWikiRdfBaseUri' => null,
			'CanonicalServer' => 'https://wiki.example',
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
			'NeoWikiRdfBaseUri' => null,
			'CanonicalServer' => 'https://wiki.example',
			'NeoWikiNeo4jInternalWriteUrl' => 'bolt://config-write',
			'NeoWikiNeo4jInternalReadUrl' => 'bolt://config-read',
		] ) );

		$this->assertSame( 'bolt://env-write', $config->neo4jInternalWriteUrl );
		$this->assertSame( 'bolt://env-read', $config->neo4jInternalReadUrl );
	}

	public function testRdfBaseUriDefaultsToTheCanonicalServer(): void {
		$config = ( new NeoWikiConfigFactory() )->buildFromMediaWikiConfig( new HashConfig( [
			'NeoWikiEnableDevelopmentUI' => false,
			'NeoWikiNeo4jInternalWriteUrl' => null,
			'NeoWikiNeo4jInternalReadUrl' => null,
			'NeoWikiRdfBaseUri' => null,
			'CanonicalServer' => 'https://wiki.example',
		] ) );

		$this->assertSame( 'https://wiki.example', $config->rdfBaseUri );
	}

	public function testConfiguredRdfBaseUriOverridesTheCanonicalServer(): void {
		$config = ( new NeoWikiConfigFactory() )->buildFromMediaWikiConfig( new HashConfig( [
			'NeoWikiEnableDevelopmentUI' => false,
			'NeoWikiNeo4jInternalWriteUrl' => null,
			'NeoWikiNeo4jInternalReadUrl' => null,
			'NeoWikiRdfBaseUri' => 'https://id.example.org/ns',
			'CanonicalServer' => 'https://wiki.example',
		] ) );

		$this->assertSame( 'https://id.example.org/ns', $config->rdfBaseUri );
	}

	public function testFullSparqlStoreEntryIsParsed(): void {
		$config = $this->buildSparqlConfig( [ [
			'updateUrl' => 'https://qlever.example/api',
			'queryUrl' => 'https://qlever.example/query',
			'accessToken' => 'secret-token',
			'projection' => 'edm',
		] ] );

		$this->assertCount( 1, $config->sparqlStores );
		$store = $config->sparqlStores[0];
		$this->assertSame( 'https://qlever.example/api', $store->updateUrl );
		$this->assertSame( 'https://qlever.example/query', $store->queryUrl );
		$this->assertSame( 'secret-token', $store->accessToken );
		$this->assertSame( 'edm', $store->projection );
	}

	public function testMinimalSparqlStoreEntryAppliesDefaults(): void {
		$config = $this->buildSparqlConfig( [ [ 'updateUrl' => 'https://qlever.example/api' ] ] );

		$store = $config->sparqlStores[0];
		$this->assertSame( 'https://qlever.example/api', $store->updateUrl );
		$this->assertSame( 'https://qlever.example/api', $store->queryUrl );
		$this->assertNull( $store->accessToken );
		$this->assertSame( 'native', $store->projection );
	}

	public function testBlankQueryUrlFallsBackToUpdateUrl(): void {
		$config = $this->buildSparqlConfig( [ [
			'updateUrl' => 'https://qlever.example/api',
			'queryUrl' => '   ',
		] ] );

		$this->assertSame( 'https://qlever.example/api', $config->sparqlStores[0]->queryUrl );
	}

	public function testSparqlStoreEntryMissingUpdateUrlIsSkippedWithWarning(): void {
		$logger = new TestLogger();

		$config = $this->buildSparqlConfig(
			[ [ 'accessToken' => 'secret-token' ], [ 'updateUrl' => 'https://ok.example/api' ] ],
			$logger
		);

		$this->assertCount( 1, $config->sparqlStores );
		$this->assertSame( 'https://ok.example/api', $config->sparqlStores[0]->updateUrl );
		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testNonArraySparqlStoreEntryIsSkippedWithWarning(): void {
		$logger = new TestLogger();

		$config = $this->buildSparqlConfig( [ 'not-an-array', [ 'updateUrl' => 'https://ok.example/api' ] ], $logger );

		$this->assertCount( 1, $config->sparqlStores );
		$this->assertSame( 'https://ok.example/api', $config->sparqlStores[0]->updateUrl );
		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testEmptySparqlStoresConfigProducesEmptyList(): void {
		$this->assertSame( [], $this->buildSparqlConfig( [] )->sparqlStores );
	}

	/**
	 * @param array<int, mixed> $sparqlStores
	 */
	private function buildSparqlConfig( array $sparqlStores, ?LoggerInterface $logger = null ): NeoWikiConfig {
		$factory = $logger === null ? new NeoWikiConfigFactory() : new NeoWikiConfigFactory( $logger );

		return $factory->buildFromMediaWikiConfig( new HashConfig( [
			'NeoWikiEnableDevelopmentUI' => false,
			'NeoWikiRdfBaseUri' => null,
			'CanonicalServer' => 'https://wiki.example',
			'NeoWikiNeo4jInternalWriteUrl' => null,
			'NeoWikiNeo4jInternalReadUrl' => null,
			'NeoWikiSparqlStores' => $sparqlStores,
		] ) );
	}

}
