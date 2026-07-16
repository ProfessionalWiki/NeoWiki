<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * The wikitext SPARQL surfaces exist only when at least one SPARQL store is configured, and are absent
 * otherwise — both directions, mirroring the Neo4j conditional-registration tests (#1016). The REST
 * route's presence is covered by SparqlRouteRegistrationTest and OnExtensionRegistrationTest.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\SparqlPlugin
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::getFirstSparqlPlugin
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onParserFirstCallInit
 * @group Database
 */
class SparqlSurfaceRegistrationTest extends NeoWikiIntegrationTestCase {

	protected function tearDown(): void {
		// The singleton bakes the store config; reset it so the next test rebuilds from restored config.
		NeoWikiExtension::resetInstance();
		parent::tearDown();
	}

	public function testParserFunctionRegisteredWhenStoreConfigured(): void {
		$this->configureSparqlStore();

		$parser = $this->recordingParser( $names );
		NeoWikiHooks::onParserFirstCallInit( $parser );

		$this->assertContains( 'sparql_raw', $names );
	}

	public function testParserFunctionNotRegisteredWithoutStore(): void {
		$this->clearSparqlStores();

		$parser = $this->recordingParser( $names );
		NeoWikiHooks::onParserFirstCallInit( $parser );

		$this->assertNotContains( 'sparql_raw', $names );
	}

	public function testLuaFunctionOfferedWhenStoreConfigured(): void {
		$this->configureSparqlStore();

		$names = NeoWikiExtension::getInstance()->getFirstSparqlPlugin()?->getLuaLibraryFunctionNames() ?? [];

		$this->assertContains( 'sparqlQuery', $names );
	}

	public function testLuaFunctionNotOfferedWithoutStore(): void {
		$this->clearSparqlStores();

		$this->assertNull( NeoWikiExtension::getInstance()->getFirstSparqlPlugin() );
	}

	private function configureSparqlStore(): void {
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [ [ 'updateUrl' => 'https://qlever.example/api' ] ] );
		NeoWikiExtension::resetInstance();
	}

	private function clearSparqlStores(): void {
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [] );
		NeoWikiExtension::resetInstance();
	}

	private function recordingParser( ?array &$names ): Parser {
		$names = [];
		$parser = $this->createMock( Parser::class );
		$parser->method( 'setFunctionHook' )->willReturnCallback(
			static function ( string $name ) use ( &$names ): void {
				$names[] = $name;
			}
		);
		return $parser;
	}

}
