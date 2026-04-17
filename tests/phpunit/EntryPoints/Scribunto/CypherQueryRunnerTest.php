<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\CypherQueryRunner;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\CypherResultConverter;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\QueryEngine;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\CypherQueryRunner
 */
class CypherQueryRunnerTest extends TestCase {

	private function newRunner(
		QueryEngine $engine,
		?CypherQueryValidator $validator = null
	): CypherQueryRunner {
		$validator ??= new class implements CypherQueryValidator {

			public function queryIsAllowed( string $cypher ): bool {
				return true;
			}

		};
		return new CypherQueryRunner( $engine, $validator, new CypherResultConverter() );
	}

	private function stubEngine( SummarizedResult $result ): QueryEngine {
		return new class( $result ) implements QueryEngine {
			public string $lastCypher = '';
			public array $lastParams = [];

			public function __construct( private readonly SummarizedResult $result ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [] ): SummarizedResult {
				$this->lastCypher = $cypher;
				$this->lastParams = $parameters;
				return $this->result;
			}
		};
	}

	private function emptyResult(): SummarizedResult {
		$summary = null;
		return new SummarizedResult( $summary, new CypherList( [] ) );
	}

	private function resultWithRows( array $rows ): SummarizedResult {
		$summary = null;
		$cypherMaps = new CypherList( array_map(
			fn( array $row ) => new CypherMap( $row ),
			$rows
		) );
		return new SummarizedResult( $summary, $cypherMaps );
	}

	public function testReturnsConvertedRows(): void {
		$engine = $this->stubEngine(
			$this->resultWithRows( [
				[ 'name' => 'Ada' ],
				[ 'name' => 'Grace' ],
			] )
		);

		$this->assertSame(
			[
				1 => [ 'name' => 'Ada' ],
				2 => [ 'name' => 'Grace' ],
			],
			$this->newRunner( $engine )->run( 'MATCH (n) RETURN n.name', [] )
		);
	}

	public function testEmptyQueryThrowsWithEmptyQueryMessage(): void {
		$runner = $this->newRunner( $this->stubEngine( $this->emptyResult() ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/empty/i' );
		$runner->run( '   ', [] );
	}

	public function testDisallowedQueryThrowsWithReadOnlyMessage(): void {
		$engine = new class implements QueryEngine {
			public function runReadQuery( string $cypher, array $parameters = [] ): SummarizedResult {
				throw new \LogicException( 'engine must not be called for a rejected query' );
			}
		};
		$runner = new CypherQueryRunner(
			$engine,
			new class implements CypherQueryValidator {

				public function queryIsAllowed( string $cypher ): bool {
					return false;
				}

			},
			new CypherResultConverter()
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/read-only/i' );
		$runner->run( 'CREATE (n)', [] );
	}

	public function testTrimsCypherBeforeValidationAndExecution(): void {
		$engine = $this->stubEngine( $this->emptyResult() );
		$runner = $this->newRunner( $engine );

		$runner->run( "  MATCH (n) RETURN n  \n", [] );

		$this->assertSame( 'MATCH (n) RETURN n', $engine->lastCypher );
	}

	public function testPassesParametersThrough(): void {
		$engine = $this->stubEngine( $this->emptyResult() );
		$runner = $this->newRunner( $engine );

		$runner->run( 'RETURN $x', [ 'x' => 42, 'y' => 'foo' ] );

		$this->assertSame( [ 'x' => 42, 'y' => 'foo' ], $engine->lastParams );
	}

	public function testEngineExceptionsPropagate(): void {
		$engine = new class implements QueryEngine {
			public function runReadQuery( string $cypher, array $parameters = [] ): SummarizedResult {
				throw new RuntimeException( 'connection refused' );
			}
		};
		$runner = $this->newRunner( $engine );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'connection refused' );
		$runner->run( 'MATCH (n) RETURN n', [] );
	}

}
