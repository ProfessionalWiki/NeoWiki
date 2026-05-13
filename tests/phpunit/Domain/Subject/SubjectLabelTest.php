<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel
 */
class SubjectLabelTest extends TestCase {

	public function testEmptyStringIsRejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new SubjectLabel( '' );
	}

	public function testWhitespaceOnlyStringIsRejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new SubjectLabel( "  \t\n  " );
	}

	public function testNonEmptyStringIsAccepted(): void {
		$label = new SubjectLabel( 'foo' );

		$this->assertSame( 'foo', $label->text );
	}

	public function testSurroundingWhitespaceIsPreserved(): void {
		$label = new SubjectLabel( '  foo  ' );

		$this->assertSame( '  foo  ', $label->text );
	}

}
