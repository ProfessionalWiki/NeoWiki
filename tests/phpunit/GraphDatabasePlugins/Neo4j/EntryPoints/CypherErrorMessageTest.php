<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\CypherErrorMessage;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\CypherErrorMessage
 */
class CypherErrorMessageTest extends TestCase {

	public function testEmptyQueryMapsToParamlessKey(): void {
		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-empty-query', [] ),
			CypherErrorMessage::for( new EmptyQueryException( 'Query is empty.' ) )
		);
	}

	public function testWriteQueryRejectedMapsToParamlessKey(): void {
		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-write-query', [] ),
			CypherErrorMessage::for( new WriteQueryRejectedException( 'Query is not read-only.' ) )
		);
	}

	public function testBackendUnavailableMapsToParamlessKey(): void {
		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-backend-unavailable', [] ),
			CypherErrorMessage::for( new BackendUnavailableException( 'Connection to bolt://neo4j:7687 refused' ) )
		);
	}

	public function testSyntaxErrorEmbedsNeo4jDetail(): void {
		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-syntax', [ "Invalid input 'X': expected an expression" ] ),
			CypherErrorMessage::for( new CypherSyntaxException( "Invalid input 'X': expected an expression" ) )
		);
	}

	public function testParameterMissingEmbedsNeo4jDetail(): void {
		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-parameter-missing', [ 'Expected parameter(s): minYear' ] ),
			CypherErrorMessage::for( new ParameterMissingException( 'Expected parameter(s): minYear' ) )
		);
	}

	public function testTimeoutEmbedsNeo4jDetail(): void {
		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-timeout', [ 'The transaction has been terminated.' ] ),
			CypherErrorMessage::for( new QueryTimeoutException( 'The transaction has been terminated.' ) )
		);
	}

	public function testInternalErrorEmbedsNeo4jDetail(): void {
		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-internal', [ 'Unexpected server failure' ] ),
			CypherErrorMessage::for( new InternalQueryException( 'Unexpected server failure' ) )
		);
	}

	public function testUnmappedErrorTypeFallsBackToInternalKey(): void {
		$unknown = new class( 'Some new failure' ) extends QueryException {
			public function errorType(): string {
				return 'someFutureErrorType';
			}
		};

		$this->assertEquals(
			new CypherErrorMessage( 'neowiki-cypher-error-internal', [ 'Some new failure' ] ),
			CypherErrorMessage::for( $unknown )
		);
	}

}
