<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction;

use Exception;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherMap;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jReadQueryEngine;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction
 */
class CypherRawParserFunctionTest extends TestCase {

	/**
	 * Returns a Parser whose msg() renders any key as "[key]" (params appended as
	 * "[key|p1|p2]"). This lets a test assert which message key the production code
	 * selected without a full MediaWiki message-resolution context.
	 */
	private function createParser(): Parser {
		$parser = $this->createMock( Parser::class );
		$parser->method( 'msg' )->willReturnCallback(
			function ( string $key, ...$params ): Message {
				$rendered = $params === [] ? "[$key]" : '[' . $key . '|' . implode( '|', $params ) . ']';
				$message = $this->createMock( Message::class );
				$message->method( 'text' )->willReturn( $rendered );
				return $message;
			}
		);
		return $parser;
	}

	private function createDummyQueryEngine(): Neo4jReadQueryEngine {
		// Create a simple mock that won't be called
		return $this->createMock( Neo4jReadQueryEngine::class );
	}

	private function createQueryEngineWithData( array $returnData ): Neo4jReadQueryEngine {
		$queryEngine = $this->createMock( Neo4jReadQueryEngine::class );

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

	private function createQueryEngineWithException( Exception $exception ): Neo4jReadQueryEngine {
		$queryEngine = $this->createMock( Neo4jReadQueryEngine::class );
		$queryEngine
			->method( 'runReadQuery' )
			->willThrowException( $exception );
		return $queryEngine;
	}

	private function createParserFunction(
		Neo4jReadQueryEngine $engine,
		?CypherQueryValidator $validator = null,
	): CypherRawParserFunction {
		$validator ??= new KeywordCypherQueryValidator();

		return new CypherRawParserFunction(
			new Neo4jQueryService(
				$engine,
				$validator,
				new Neo4jResultNormalizer(),
			)
		);
	}

	public function testEmptyQueryShowsLocalizedError(): void {
		$parserFunction = $this->createParserFunction( $this->createDummyQueryEngine() );

		$result = $parserFunction->handle( $this->createParser(), '' );

		$this->assertStringContainsString( '[neowiki-cypher-error-empty-query]', $result );
	}

	public function testWriteQueryShowsLocalizedError(): void {
		$parserFunction = $this->createParserFunction( $this->createDummyQueryEngine() );

		$result = $parserFunction->handle( $this->createParser(), "CREATE (n:Person {name: 'Alice'})" );

		$this->assertStringContainsString( '[neowiki-cypher-error-write-query]', $result );
	}

	public function testEngineFailureShowsLocalizedBackendError(): void {
		$parserFunction = $this->createParserFunction(
			$this->createQueryEngineWithException( new Exception( 'Connection failed' ) )
		);

		$result = $parserFunction->handle( $this->createParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( '[neowiki-cypher-error-backend-unavailable]', $result );
	}

	public function testSyntaxErrorForwardsNeo4jDetailToLocalizedMessage(): void {
		$neo4jException = new Neo4jException( [
			Neo4jError::fromMessageAndCode( 'Neo.ClientError.Statement.SyntaxError', 'Invalid input near RETURN' ),
		] );

		$parserFunction = $this->createParserFunction(
			$this->createQueryEngineWithException( $neo4jException )
		);

		$result = $parserFunction->handle( $this->createParser(), 'MATCH (n) RETURN x' );

		$this->assertStringContainsString( '[neowiki-cypher-error-syntax|', $result );
		$this->assertStringContainsString( 'Invalid input near RETURN', $result );
	}

	public function testValidatorFailureShowsLocalizedBackendError(): void {
		$throwingValidator = new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				throw new RuntimeException( 'Neo4j connection refused' );
			}
		};

		$parserFunction = $this->createParserFunction( $this->createDummyQueryEngine(), $throwingValidator );

		$result = $parserFunction->handle( $this->createParser(), 'MATCH (n) RETURN n' );

		$this->assertStringContainsString( '[neowiki-cypher-error-backend-unavailable]', $result );
	}

	public function testValidReadQueryReturnsFormattedResult(): void {
		$testData = [
			[ 'name' => 'Alice', 'age' => 30 ],
			[ 'name' => 'Bob', 'age' => 25 ]
		];

		$parserFunction = $this->createParserFunction( $this->createQueryEngineWithData( $testData ) );

		$result = $parserFunction->handle( $this->createParser(), 'MATCH (n:Person) RETURN n' );

		$this->assertStringContainsString( '<div class="mw-neowiki-cypher-result"><pre>', $result );
		$this->assertStringContainsString( 'Alice', $result );
		$this->assertStringContainsString( 'Bob', $result );
	}

	public function testTrimWhitespaceFromQuery(): void {
		$parserFunction = $this->createParserFunction( $this->createQueryEngineWithData( [] ) );

		$result = $parserFunction->handle( $this->createParser(), '  MATCH (n) RETURN n  ' );

		$this->assertStringContainsString( '<div class="mw-neowiki-cypher-result"><pre>', $result );
	}

	public function testOutputIsHTMLEscaped(): void {
		$testData = [
			[ 'name' => '<script>alert("xss")</script>' ]
		];

		$parserFunction = $this->createParserFunction( $this->createQueryEngineWithData( $testData ) );

		$result = $parserFunction->handle( $this->createParser(), 'MATCH (n) RETURN n' );

		$this->assertStringNotContainsString( '<script>alert', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

}
