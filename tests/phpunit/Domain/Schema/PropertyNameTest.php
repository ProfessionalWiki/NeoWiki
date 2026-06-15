<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName
 */
class PropertyNameTest extends TestCase {

	public function testTrimsSurroundingWhitespace(): void {
		$this->assertSame( 'Age', ( new PropertyName( '  Age  ' ) )->text );
	}

	public function testRejectsWhitespaceOnlyName(): void {
		$this->expectException( InvalidArgumentException::class );
		new PropertyName( '   ' );
	}

	public function testRejectsEmptyName(): void {
		$this->expectException( InvalidArgumentException::class );
		new PropertyName( '' );
	}

}
