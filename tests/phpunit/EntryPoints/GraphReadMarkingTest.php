<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MockHttpTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphReads;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\ParseTimePermissionFixtures;

/**
 * Each read is marked whether or not the store answers it, so the SPARQL store here only fails: configuring
 * one registers the SPARQL functions.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction\SparqlRawParserFunction
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\GraphReads
 * @group Database
 */
class GraphReadMarkingTest extends NeoWikiIntegrationTestCase {

	use MockHttpTrait;
	use ParseTimePermissionFixtures;

	private const string MODULE = 'GraphReadMarkingTestModule';

	protected function setUp(): void {
		parent::setUp();
		$this->configureAnUnreachableSparqlStore();
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 503 ) );
	}

	public function testACypherQueryMarksTheParse(): void {
		$this->assertTrue( GraphReads::happenedIn( $this->parse( '{{#cypher_raw: RETURN 1 AS n}}' ) ) );
	}

	public function testASparqlQueryMarksTheParse(): void {
		$this->assertTrue( GraphReads::happenedIn( $this->parse( '{{#sparql_raw: SELECT * WHERE { ?s ?p ?o } }}' ) ) );
	}

	public function testACypherQueryFromLuaMarksTheParse(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$this->assertTrue( GraphReads::happenedIn( $this->parseModuleRunning( "nw.query( 'RETURN 1 AS n' )" ) ) );
	}

	public function testASparqlQueryFromLuaMarksTheParse(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$this->assertTrue(
			GraphReads::happenedIn( $this->parseModuleRunning( "nw.sparqlQuery( 'SELECT * WHERE { ?s ?p ?o }' )" ) )
		);
	}

	public function testAParseThatReadsNoStoreIsNotMarked(): void {
		$this->assertFalse( GraphReads::happenedIn( $this->parse( 'Plain text' ) ) );
	}

	private function parse( string $wikitext ): ParserOutput {
		return $this->parserOutputOn( 'GraphReadMarkingTestPage', $wikitext, ParserOptions::newFromAnon() );
	}

	private function parseModuleRunning( string $luaStatement ): ParserOutput {
		$this->editPage(
			Title::makeTitle( NS_MODULE, self::MODULE ),
			new ScribuntoContent(
				"local nw = require( 'mw.neowiki' )\n"
				. "local p = {}\n"
				. "function p.read() pcall( function() $luaStatement end ) return '' end\n"
				. 'return p'
			)
		);

		return $this->parse( '{{#invoke:' . self::MODULE . '|read}}' );
	}

}
