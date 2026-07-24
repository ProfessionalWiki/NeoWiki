<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\Iri
 */
class IriTest extends TestCase {

	public function testRejectsEmptyValue(): void {
		$this->expectException( InvalidArgumentException::class );
		new Iri( '   ' );
	}

	public function testEqualsMatchesSameValue(): void {
		$this->assertTrue(
			( new Iri( 'http://example.org/a' ) )->equals( new Iri( 'http://example.org/a' ) )
		);
	}

	public function testEqualsRejectsDifferentValue(): void {
		$this->assertFalse(
			( new Iri( 'http://example.org/a' ) )->equals( new Iri( 'http://example.org/b' ) )
		);
	}

	public function testIriDoesNotEqualLiteralWithSameString(): void {
		$this->assertFalse(
			( new Iri( 'http://example.org/a' ) )->equals(
				new Literal( 'http://example.org/a', new Iri( 'http://www.w3.org/2001/XMLSchema#string' ) )
			)
		);
	}

	public function testIsSafeAbsoluteAcceptsAnAbsoluteIri(): void {
		$this->assertTrue( Iri::isSafeAbsolute( 'https://example.org/a' ) );
	}

	public function testIsSafeAbsoluteRejectsAStringWithoutAScheme(): void {
		$this->assertFalse( Iri::isSafeAbsolute( 'example.org/a' ) );
	}

	public function testIsSafeAbsoluteRejectsAnIriContainingIllegalCharacters(): void {
		$this->assertFalse( Iri::isSafeAbsolute( 'http://evil.example/"> .# ' ) );
	}

}
