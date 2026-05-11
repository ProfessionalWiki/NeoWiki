<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Query;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\WriteQueryRejectedException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryException
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\EmptyQueryException
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\WriteQueryRejectedException
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\CypherSyntaxException
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\ParameterMissingException
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryTimeoutException
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\BackendUnavailableException
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\Exception\InternalQueryException
 */
class QueryExceptionTest extends TestCase {

	public function testEmptyQueryExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new EmptyQueryException() );
	}

	public function testEmptyQueryExceptionErrorType(): void {
		$this->assertSame( 'emptyQuery', ( new EmptyQueryException() )->errorType() );
	}

	public function testWriteQueryRejectedExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new WriteQueryRejectedException() );
	}

	public function testWriteQueryRejectedExceptionErrorType(): void {
		$this->assertSame( 'writeQueryRejected', ( new WriteQueryRejectedException() )->errorType() );
	}

	public function testCypherSyntaxExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new CypherSyntaxException() );
	}

	public function testCypherSyntaxExceptionErrorType(): void {
		$this->assertSame( 'cypherSyntaxError', ( new CypherSyntaxException() )->errorType() );
	}

	public function testParameterMissingExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new ParameterMissingException() );
	}

	public function testParameterMissingExceptionErrorType(): void {
		$this->assertSame( 'parameterMissing', ( new ParameterMissingException() )->errorType() );
	}

	public function testQueryTimeoutExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new QueryTimeoutException() );
	}

	public function testQueryTimeoutExceptionErrorType(): void {
		$this->assertSame( 'queryTimeout', ( new QueryTimeoutException() )->errorType() );
	}

	public function testBackendUnavailableExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new BackendUnavailableException() );
	}

	public function testBackendUnavailableExceptionErrorType(): void {
		$this->assertSame( 'backendUnavailable', ( new BackendUnavailableException() )->errorType() );
	}

	public function testInternalQueryExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new InternalQueryException() );
	}

	public function testInternalQueryExceptionErrorType(): void {
		$this->assertSame( 'internalError', ( new InternalQueryException() )->errorType() );
	}

}
