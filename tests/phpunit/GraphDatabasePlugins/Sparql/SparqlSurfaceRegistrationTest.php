<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * The REST route's presence is covered by SparqlRouteRegistrationTest and OnExtensionRegistrationTest.
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

	public function testParserFunctionExplainsItCannotRunWithoutStore(): void {
		$this->clearSparqlStores();

		$html = $this->parseWikitextOn(
			'NoSparqlStorePage',
			'{{#sparql_raw: SELECT ?s WHERE { ?s ?p ?o } }}',
			$this->parserOptionsShowingMessageKeys()
		);

		$this->assertStringContainsString( '(neowiki-sparql-raw-error-not-configured)', $html );
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

}
