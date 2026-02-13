<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Exception;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use MediaWiki\Parser\Parser;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\KeywordCypherQueryValidator;
use ProfessionalWiki\NeoWiki\EntryPoints\CypherRawParserFunction;
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

	public function testEmptyQueryReturnsError(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createDummyQueryEngine(),
			new KeywordCypherQueryValidator()
		);

		$result = $parserFunction->handle( $this->createMockParser(), '' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testWriteQueryIsRejected(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createDummyQueryEngine(),
			new KeywordCypherQueryValidator()
		);

		$result = $parserFunction->handle( $this->createMockParser(), "CREATE (n:Person {name: 'Alice'})" );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testValidReadQueryReturnsFormattedResult(): void {
		$testData = [
			[ 'name' => 'Alice', 'age' => 30 ],
			[ 'name' => 'Bob', 'age' => 25 ]
		];

		$parserFunction = new CypherRawParserFunction(
			$this->createQueryEngineWithData( $testData ),
			new KeywordCypherQueryValidator()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n:Person) RETURN n' );

		$this->assertStringContainsString( '<pre><code class="json">', $result );
		$this->assertStringContainsString( 'Alice', $result );
		$this->assertStringContainsString( 'Bob', $result );
	}

	public function testQueryExecutionExceptionReturnsError(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createQueryEngineWithException( new Exception( 'Connection failed' ) ),
			new KeywordCypherQueryValidator()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testTrimWhitespaceFromQuery(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createQueryEngineWithData( [] ),
			new KeywordCypherQueryValidator()
		);

		$result = $parserFunction->handle( $this->createMockParser(), '  MATCH (n) RETURN n  ' );

		$this->assertStringContainsString( '<pre><code class="json">', $result );
	}

	public function testValidatorExceptionReturnsError(): void {
		$throwingValidator = new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				throw new RuntimeException( 'Neo4j connection refused' );
			}
		};

		$parserFunction = new CypherRawParserFunction(
			$this->createDummyQueryEngine(),
			$throwingValidator
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testOutputIsHTMLEscaped(): void {
		$testData = [
			[ 'name' => '<script>alert("xss")</script>' ]
		];

		$parserFunction = new CypherRawParserFunction(
			$this->createQueryEngineWithData( $testData ),
			new KeywordCypherQueryValidator()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringNotContainsString( '<script>alert', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

}
