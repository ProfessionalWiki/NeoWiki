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

	/**
	 * @dataProvider validIriProvider
	 */
	public function testIsValidAcceptsAnIri( string $iri ): void {
		$this->assertTrue( Iri::isValid( $iri ) );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function validIriProvider(): iterable {
		yield 'http URL' => [ 'https://example.org/a' ];
		yield 'host without a path' => [ 'https://example.org' ];
		yield 'percent-encoded and raw non-ASCII characters' => [ 'https://example.org/caf%c3%a9/café' ];
		yield 'colon in the path' => [ 'https://example.org/wiki/Category:Museums' ];
		yield 'query with several parameters' => [ 'https://example.org/~jan/login?next=/search?q=a&page=2' ];
		yield 'fragment' => [ 'http://www.w3.org/2001/XMLSchema#string' ];
		yield 'slash and question mark in the fragment' => [ 'https://example.org/app#/search?q=a' ];
		yield 'private-use character in the query' => [ "https://example.org/?q=\u{E000}" ];
		yield 'user in the authority' => [ 'https://user@example.org/' ];
		yield 'IPv6 host and port' => [ 'http://[::1]:8080/a' ];
		yield 'no authority' => [ 'mailto:info@example.org' ];
	}

	/**
	 * @dataProvider invalidIriProvider
	 */
	public function testIsValidRejectsAnInvalidIri( string $iri ): void {
		$this->assertFalse( Iri::isValid( $iri ) );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function invalidIriProvider(): iterable {
		yield 'no scheme' => [ 'example.org/a' ];
		yield 'empty scheme' => [ '://example.org/a' ];
		yield 'IRIREF-illegal characters' => [ 'http://evil.example/"> .# ' ];
		yield 'IRIREF-illegal characters before the scheme' => [ '"> <http://evil.example/a' ];
		yield 'trailing newline' => [ "https://example.org/a\n" ];
		yield 'square brackets outside the host' => [ 'https://example.org/?filter[type]=book' ];
		yield 'second number sign' => [ 'https://example.org/#/a#b' ];
		yield 'percent sign not followed by two hex digits' => [ 'https://example.org/caf%c' ];
		yield 'C1 control character' => [ "https://example.org/a\u{0085}b" ];
		yield 'replacement character' => [ "https://example.org/a\u{FFFD}b" ];
		yield 'private-use character in the path' => [ "https://example.org/a\u{E000}b" ];
		yield 'private-use character in the fragment' => [ "https://example.org/#\u{E000}" ];
		yield 'variation selector from the supplement' => [ "https://example.org/a\u{E0100}b" ];
		yield 'non-numeric port' => [ 'http://example.org:80a/' ];
		yield 'second at sign' => [ 'http://a@b@example.org/' ];
		yield 'malformed IPv6 host' => [ 'http://[1:2:3]/' ];
	}

}
