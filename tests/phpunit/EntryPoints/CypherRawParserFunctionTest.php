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

	private function createStubQueryEngine( array $returnData = [], Exception $exception = null ): QueryEngine {
		$stub = $this->createStub( QueryEngine::class );
		
		if ( $exception !== null ) {
			$stub->method( 'runReadQuery' )->willThrowException( $exception );
		} else {
			$resultStub = $this->createStub( SummarizedResult::class );
			$resultStub->method( 'toArray' )->willReturn( $returnData );
			$stub->method( 'runReadQuery' )->willReturn( $resultStub );
		}
		
		return $stub;
	}

	public function testEmptyQueryReturnsError(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createStubQueryEngine(),
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), '' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testWriteQueryIsRejected(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createStubQueryEngine(),
			new CypherQueryFilter()
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
			$this->createStubQueryEngine( $testData ),
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n:Person) RETURN n' );

		$this->assertStringContainsString( '<pre><code class="json">', $result );
		$this->assertStringContainsString( 'Alice', $result );
		$this->assertStringContainsString( 'Bob', $result );
	}

	public function testQueryExecutionExceptionReturnsError(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createStubQueryEngine( [], new Exception( 'Connection failed' ) ),
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( 'error', $result );
	}

	public function testTrimWhitespaceFromQuery(): void {
		$parserFunction = new CypherRawParserFunction(
			$this->createStubQueryEngine( [] ),
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), '  MATCH (n) RETURN n  ' );

		$this->assertStringContainsString( '<pre><code class="json">', $result );
	}

	public function testOutputIsHTMLEscaped(): void {
		$testData = [
			[ 'name' => '<script>alert("xss")</script>' ]
		];

		$parserFunction = new CypherRawParserFunction(
			$this->createStubQueryEngine( $testData ),
			new CypherQueryFilter()
		);

		$result = $parserFunction->handle( $this->createMockParser(), 'MATCH (n) RETURN n' );

		$this->assertStringNotContainsString( '<script>alert', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

}
