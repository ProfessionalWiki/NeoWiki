<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\InvalidFieldException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\InvalidFieldException
 */
class InvalidFieldExceptionTest extends TestCase {

	public function testReasonQuotesTheValueAsWritten(): void {
		$exception = InvalidFieldException::mustBe( 'minLength', 'a whole number', 'fünf/5' );

		$this->assertSame( 'must be a whole number, not "fünf/5"', $exception->reason );
	}

}
