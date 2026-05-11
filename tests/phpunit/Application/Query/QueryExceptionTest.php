<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Query;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\EmptyQueryException;
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
 */
class QueryExceptionTest extends TestCase {

	public function testEmptyQueryExceptionExtendsBase(): void {
		$exception = new EmptyQueryException( 'msg' );
		$this->assertInstanceOf( QueryException::class, $exception );
		$this->assertSame( 'emptyQuery', $exception->errorType() );
	}

	public function testWriteQueryRejectedExceptionExtendsBase(): void {
		$exception = new WriteQueryRejectedException( 'msg' );
		$this->assertInstanceOf( QueryException::class, $exception );
		$this->assertSame( 'writeQueryRejected', $exception->errorType() );
	}

	public function testCypherSyntaxExceptionExtendsBase(): void {
		$exception = new CypherSyntaxException( 'msg' );
		$this->assertInstanceOf( QueryException::class, $exception );
		$this->assertSame( 'cypherSyntaxError', $exception->errorType() );
	}

	public function testParameterMissingExceptionExtendsBase(): void {
		$exception = new ParameterMissingException( 'msg' );
		$this->assertInstanceOf( QueryException::class, $exception );
		$this->assertSame( 'parameterMissing', $exception->errorType() );
	}

	public function testQueryTimeoutExceptionExtendsBase(): void {
		$exception = new QueryTimeoutException( 'msg' );
		$this->assertInstanceOf( QueryException::class, $exception );
		$this->assertSame( 'queryTimeout', $exception->errorType() );
	}

	public function testBackendUnavailableExceptionExtendsBase(): void {
		$exception = new BackendUnavailableException( 'msg' );
		$this->assertInstanceOf( QueryException::class, $exception );
		$this->assertSame( 'backendUnavailable', $exception->errorType() );
	}

}
