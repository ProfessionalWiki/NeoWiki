<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\Literal
 */
class LiteralTest extends TestCase {

	private function xsd( string $local ): Iri {
		return new Iri( 'http://www.w3.org/2001/XMLSchema#' . $local );
	}

	public function testEqualsMatchesSameLexicalAndDatatype(): void {
		$this->assertTrue(
			( new Literal( '42', $this->xsd( 'integer' ) ) )->equals(
				new Literal( '42', $this->xsd( 'integer' ) )
			)
		);
	}

	public function testEqualsRejectsDifferentDatatype(): void {
		$this->assertFalse(
			( new Literal( '42', $this->xsd( 'integer' ) ) )->equals(
				new Literal( '42', $this->xsd( 'decimal' ) )
			)
		);
	}

	public function testEqualsRejectsDifferentLexicalForm(): void {
		$this->assertFalse(
			( new Literal( '42', $this->xsd( 'integer' ) ) )->equals(
				new Literal( '43', $this->xsd( 'integer' ) )
			)
		);
	}

	public function testLanguageTagDistinguishesLiterals(): void {
		$tagged = new Literal( 'Bonjour', $this->xsd( 'string' ), 'fr' );
		$plain = new Literal( 'Bonjour', $this->xsd( 'string' ) );

		$this->assertTrue( $tagged->isLanguageTagged() );
		$this->assertFalse( $plain->isLanguageTagged() );
		$this->assertFalse( $tagged->equals( $plain ) );
	}

	/**
	 * The tag is kept as written; a MonolingualText part lowercases its own.
	 */
	public function testKeepsTheCaseOfAValidLanguageTag(): void {
		$this->assertSame( 'en-US', ( new Literal( 'x', $this->xsd( 'string' ), 'en-US' ) )->languageTag );
	}

	/**
	 * A non-null language tag is a domain invariant: an out-of-shape tag (which could smuggle a `"` or a
	 * datatype into the serialized `"lexical"@tag` form) is rejected at construction. The full catalogue
	 * of shapes is MonolingualTextTest's; that this constructor applies the same rule is here.
	 *
	 * @dataProvider invalidLanguageTagProvider
	 */
	public function testRejectsAnInvalidLanguageTag( string $tag ): void {
		$this->expectException( InvalidArgumentException::class );
		new Literal( 'x', $this->xsd( 'string' ), $tag );
	}

	public static function invalidLanguageTagProvider(): iterable {
		yield 'underscore separator' => [ 'en_US' ];
		yield 'datatype injection' => [ 'en"^^xsd:evil' ];
	}

}
