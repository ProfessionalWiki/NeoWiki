<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\RejectedValueException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\RejectedValueException
 */
class RejectedValueExceptionTest extends TestCase {

	public function testIsAnInvalidArgumentException(): void {
		$exception = new RejectedValueException( new Violation( null, 'invalid-option', [], null, Severity::Error ) );

		$this->assertInstanceOf( InvalidArgumentException::class, $exception );
	}

	public function testExposesTheViolation(): void {
		$violation = new Violation( new PropertyName( 'Status' ), 'invalid-option', [ 'a' ], null, Severity::Error );

		$this->assertSame( $violation, ( new RejectedValueException( $violation ) )->violation );
	}

	public function testMessageNamesCodeArgsPropertyAndPart(): void {
		$violation = new Violation( new PropertyName( 'Status' ), 'invalid-option', [ 'a', 2 ], 1, Severity::Error );

		$this->assertSame( 'invalid-option: a, 2 on Status[1]', ( new RejectedValueException( $violation ) )->getMessage() );
	}

	public function testMessageOmitsWhatIsAbsent(): void {
		$violation = new Violation( null, 'select-object-without-id-or-label', [], null, Severity::Error );

		$this->assertSame( 'select-object-without-id-or-label', ( new RejectedValueException( $violation ) )->getMessage() );
	}

}
