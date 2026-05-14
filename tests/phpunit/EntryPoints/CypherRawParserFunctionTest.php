<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Exception;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use MediaWiki\Parser\Parser;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\Application\Query\Cypher\QueryResultNormalizer;
use ProfessionalWiki\NeoWiki\Application\Query\Cypher\QueryService;
use ProfessionalWiki\NeoWiki\EntryPoints\CypherRawParserFunction;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\KeywordCypherQueryValidator;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\QueryEngine;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\CypherRawParserFunction
 */
class CypherRawParserFunctionTest extends TestCase {

	private function createMockParser(): Parser {
		return $this->createMock( Parser::class );
	}

	private function createDummyQueryEngine(): QueryEngine {
		// Create a simple mock that won't be called
		return $this->createMock( QueryEngine::class );
	}

	private function createQueryEngineWithData( array $returnData ): QueryEngine {
		$queryEngine = $this->createMock( QueryEngine::class );

		$cypherMaps = array_map(
			fn( array $row ) => new CypherMap( $row ),
			$returnData
		);
		$summary = null;
		$result = new SummarizedResult( $summary, $cypherMaps );

		$queryEngine
			->method( 'runReadQuery' )
			->willReturn( $result );

		return $queryEngine;
	}

	private function createQueryEngineWithException( Exception $exception ): QueryEngine {
		$queryEngine = $this->createMock( QueryEngine::class );
		$queryEngine
			->method( 'runReadQuery' )
			->willThrowException( $exception );
		return $queryEngine;
	}

	private function createParserFunction(
		QueryEngine $engine,
		?CypherQueryValidator $validator = null,
	): CypherRawParserFunction {
		$validator ??= new KeywordCypherQueryValidator();

		return new CypherRawParserFunction(
			new QueryService(
				$engine,
				$validator,
				new QueryResultNormalizer(),
			)
		);
	}

	public function testEmptyQueryReturnsError(): void {
		$parserFunction = $this->createParserFunction( $this->createDummyQueryEngine() );

		$result = $parserFunction->handle( $this->createMockParser(), '' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testWriteQueryIsRejected(): void {
		$parserFunction = $this->createParserFunction( $this->createDummyQueryEngine() );

		$result = $parserFunction->handle( $this->createMockParser(), "CREATE (n:Person {name: 'Alice'})" );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testValidReadQueryReturnsFormattedResult(): void {
		$testData = [
			[ 'name' => 'Alice', 'age' => 30 ],
			[ 'name' => 'Bob', 'age' => 25 ]
		];

		$parserFunction = $this->createParserFunction( $this->createQueryEngineWithData( $testData ) );

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n:Person) RETURN n' );

		$this->assertStringContainsString( '<div class="mw-neowiki-cypher-result"><pre>', $result );
		$this->assertStringContainsString( 'Alice', $result );
		$this->assertStringContainsString( 'Bob', $result );
	}

	public function testQueryExecutionExceptionReturnsError(): void {
		$parserFunction = $this->createParserFunction(
			$this->createQueryEngineWithException( new Exception( 'Connection failed' ) )
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testTrimWhitespaceFromQuery(): void {
		$parserFunction = $this->createParserFunction( $this->createQueryEngineWithData( [] ) );

		$result = $parserFunction->handle( $this->createMockParser(), '  MATCH (n) RETURN n  ' );

		$this->assertStringContainsString( '<div class="mw-neowiki-cypher-result"><pre>', $result );
	}

	public function testValidatorExceptionReturnsError(): void {
		$throwingValidator = new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				throw new RuntimeException( 'Neo4j connection refused' );
			}
		};

		$parserFunction = $this->createParserFunction( $this->createDummyQueryEngine(), $throwingValidator );

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testOutputIsHTMLEscaped(): void {
		$testData = [
			[ 'name' => '<script>alert("xss")</script>' ]
		];

		$parserFunction = $this->createParserFunction( $this->createQueryEngineWithData( $testData ) );

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringNotContainsString( '<script>alert', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

}
