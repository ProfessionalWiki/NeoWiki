<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jReadQueryEngine;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubRawQueryAuthorizer;

/**
 * What a reader ends up with, rather than what the parser function returns: the corruption this
 * guards against happens in MediaWiki's parser, after the function has handed its text back, so it
 * is invisible to a test that only inspects the return value.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction
 * @group Database
 */
class CypherRawParserFunctionParsingTest extends MediaWikiIntegrationTestCase {

	private const string WEBSITE = 'https://www.rijksmuseum.nl';

	private function parseCypherRaw(): string {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$queryService = $this->queryServiceReturningWebsite();

		$parser->setFunctionHook(
			'cypher_raw',
			static function ( Parser $parser, string $cypherQuery ) use ( $queryService ): string|array {
				return ( new CypherRawParserFunction( $queryService, new Neo4jQueryLimits( 30, 5000 ) ) )
					->handle( $parser, $cypherQuery );
			}
		);

		$parserOptions = ParserOptions::newFromAnon();

		return $parser->parse(
			'{{#cypher_raw: MATCH (m:Museum) RETURN m.Website AS site }}',
			Title::makeTitle( NS_MAIN, 'CypherRawParsingTest' ),
			$parserOptions
		)->runOutputPipeline( $parserOptions, [] )->getContentHolderText();
	}

	private function queryServiceReturningWebsite(): Neo4jQueryService {
		// SummarizedResult takes its summary by reference, so it needs a variable.
		$summary = null;

		$queryEngine = $this->createMock( Neo4jReadQueryEngine::class );
		$queryEngine->method( 'runReadQuery' )->willReturn(
			new SummarizedResult( $summary, [ new CypherMap( [ 'site' => self::WEBSITE ] ) ] )
		);

		return new Neo4jQueryService(
			$queryEngine,
			new KeywordCypherQueryValidator(),
			new Neo4jResultNormalizer(),
			new StubRawQueryAuthorizer( true ),
		);
	}

	public function testUrlValueSurvivesParsingUnchanged(): void {
		$html = html_entity_decode( $this->parseCypherRaw() );

		$this->assertStringContainsString( '"site": "' . self::WEBSITE . '"', $html );
	}

	public function testUrlsAreNotTurnedIntoLinks(): void {
		$html = $this->parseCypherRaw();

		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( '%22', $html );
	}

}
