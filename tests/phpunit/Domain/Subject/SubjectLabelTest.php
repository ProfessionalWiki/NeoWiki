<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel
 */
class SubjectLabelTest extends TestCase {

	public function testStoresText(): void {
		$label = new SubjectLabel( 'hello' );

		$this->assertSame( 'hello', $label->text );
	}

	public function testSurroundingWhitespaceIsPreserved(): void {
		$label = new SubjectLabel( '  foo  ' );

		$this->assertSame( '  foo  ', $label->text );
	}

	public function testAcceptsEmptyString(): void {
		$label = new SubjectLabel( '' );

		$this->assertSame( '', $label->text );
	}

	public function testAcceptsWhitespaceOnlyString(): void {
		$label = new SubjectLabel( "  \t\n  " );

		$this->assertSame( "  \t\n  ", $label->text );
	}

}
