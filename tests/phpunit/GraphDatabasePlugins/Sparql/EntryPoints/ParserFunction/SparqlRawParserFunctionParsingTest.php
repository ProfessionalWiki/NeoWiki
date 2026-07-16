<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction\SparqlRawParserFunction;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FakeSparqlQueryEndpoint;

/**
 * What a reader ends up with, rather than what the parser function returns: the corruption this
 * guards against happens in MediaWiki's parser, after the function has handed its text back, so it
 * is invisible to a test that only inspects the return value.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction\SparqlRawParserFunction
 * @group Database
 */
class SparqlRawParserFunctionParsingTest extends MediaWikiIntegrationTestCase {

	private const string RESULTS = '{"head":{"vars":["n"]},"results":{"bindings":[{"n":{"datatype":"http://www.w3.org/2001/XMLSchema#int","type":"literal","value":"42"}}]}}';

	private function parseSparqlRaw( string $results ): string {
		$parser = $this->getServiceContainer()->getParserFactory()->create();

		$parser->setFunctionHook(
			'sparql_raw',
			static function ( Parser $parser, string $query ) use ( $results ): string|array {
				return ( new SparqlRawParserFunction(
					new SparqlQueryService( FakeSparqlQueryEndpoint::returning( $results ) )
				) )->handle( $parser, $query );
			}
		);

		return $parser->parse(
			'{{#sparql_raw: SELECT ?n WHERE { ?s ?p ?n } }}',
			Title::makeTitle( NS_MAIN, 'SparqlRawParsingTest' ),
			ParserOptions::newFromAnon()
		)->getText();
	}

	public function testDatatypeIriSurvivesParsingUnchanged(): void {
		$html = html_entity_decode( $this->parseSparqlRaw( self::RESULTS ) );

		$this->assertStringContainsString( '"datatype": "http://www.w3.org/2001/XMLSchema#int"', $html );
	}

	public function testIrisAreNotTurnedIntoLinks(): void {
		$html = $this->parseSparqlRaw( self::RESULTS );

		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( '%22', $html );
	}

}
