<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Exception;
use Laudis\Neo4j\Databags\SummarizedResult;
use MediaWiki\Parser\Parser;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\CypherQueryFilter;
use ProfessionalWiki\NeoWiki\EntryPoints\CypherRawParserFunction;
use ProfessionalWiki\NeoWiki\Persistence\QueryEngine;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\CypherRawParserFunction
 */
class CypherRawParserFunctionTest extends TestCase {

	private function createMockParser(): Parser {
		return $this->createMock( Parser::class );
	}

	private function createMockQueryEngine(): QueryEngine {
		return $this->createMock( QueryEngine::class );
	}

	public function testEmptyQueryReturnsError(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createMockQueryEngine(),
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), '' );

		$this->assertStringContainsString( 'Empty Cypher query provided', $result );
		$this->assertStringContainsString( 'error', $result );
	}

	public function testWriteQueryIsRejected(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createMockQueryEngine(),
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), "CREATE (n:Person {name: 'Alice'})" );

		$this->assertStringContainsString( 'Write queries are not allowed', $result );
		$this->assertStringContainsString( 'error', $result );
	}

	public function testValidReadQueryReturnsFormattedResult(): void {
		$mockResult = $this->createMock( SummarizedResult::class );
		$mockResult->method( 'toArray' )->willReturn( [
			[ 'name' => 'Alice', 'age' => 30 ],
			[ 'name' => 'Bob', 'age' => 25 ]
		] );

		$mockQueryEngine = $this->createMockQueryEngine();
		$mockQueryEngine
			->expects( $this->once() )
			->method( 'runReadQuery' )
			->with( 'MATCH (n:Person) RETURN n' )
			->willReturn( $mockResult );

		$parserFunction = new CypherRawParserFunction(
			$mockQueryEngine,
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n:Person) RETURN n' );

		$this->assertStringContainsString( '<pre><code class="json">', $result );
		$this->assertStringContainsString( 'Alice', $result );
		$this->assertStringContainsString( 'Bob', $result );
	}

	public function testQueryExecutionExceptionReturnsError(): void {
		$mockQueryEngine = $this->createMockQueryEngine();
		$mockQueryEngine
			->expects( $this->once() )
			->method( 'runReadQuery' )
			->willThrowException( new Exception( 'Connection failed' ) );

		$parserFunction = new CypherRawParserFunction(
			$mockQueryEngine,
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( 'Query execution failed', $result );
		$this->assertStringContainsString( 'Connection failed', $result );
		$this->assertStringContainsString( 'error', $result );
	}

	public function testTrimWhitespaceFromQuery(): void {
		$mockResult = $this->createMock( SummarizedResult::class );
		$mockResult->method( 'toArray' )->willReturn( [] );

		$mockQueryEngine = $this->createMockQueryEngine();
		$mockQueryEngine
			->expects( $this->once() )
			->method( 'runReadQuery' )
			->with( 'MATCH (n) RETURN n' )
			->willReturn( $mockResult );

		$parserFunction = new CypherRawParserFunction(
			$mockQueryEngine,
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), '  MATCH (n) RETURN n  ' );

		$this->assertStringContainsString( '<pre><code class="json">', $result );
	}

	public function testOutputIsHTMLEscaped(): void {
		$mockResult = $this->createMock( SummarizedResult::class );
		$mockResult->method( 'toArray' )->willReturn( [
			[ 'name' => '<script>alert("xss")</script>' ]
		] );

		$mockQueryEngine = $this->createMockQueryEngine();
		$mockQueryEngine
			->method( 'runReadQuery' )
			->willReturn( $mockResult );

		$parserFunction = new CypherRawParserFunction(
			$mockQueryEngine,
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringNotContainsString( '<script>alert', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

}
