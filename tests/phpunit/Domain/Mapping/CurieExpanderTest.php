<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Mapping;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Mapping\CurieExpander;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Mapping\CurieExpander
 */
class CurieExpanderTest extends TestCase {

	private function expander(): CurieExpander {
		return new CurieExpander( [
			'edm' => 'http://www.europeana.eu/schemas/edm/',
			'dc' => 'http://purl.org/dc/elements/1.1/',
		] );
	}

	public function testExpandsADeclaredCurie(): void {
		$this->assertSame(
			'http://www.europeana.eu/schemas/edm/ProvidedCHO',
			$this->expander()->expand( 'edm:ProvidedCHO' )?->value
		);
	}

	public function testAcceptsAnAbsoluteIriWithAnAuthority(): void {
		$this->assertSame(
			'http://example.org/ns/creator',
			$this->expander()->expand( 'http://example.org/ns/creator' )?->value
		);
	}

	public function testRejectsAnUndeclaredCuriePrefix(): void {
		// A typo'd prefix must not be silently reinterpreted as the absolute IRI "crm:E12".
		$this->assertNull( $this->expander()->expand( 'crm:E12_Production' ) );
	}

	public function testRejectsATermWithoutAColon(): void {
		$this->assertNull( $this->expander()->expand( 'title' ) );
	}

	/**
	 * The heart of the #1029 lesson: a malicious term must never break out of its IRI to forge
	 * triples or prefix declarations. Every one of these expands to something containing an
	 * IRIREF-illegal character, so the expander must reject it rather than emit it.
	 *
	 * @dataProvider provideInjectionTerms
	 */
	public function testRejectsTermsThatWouldBreakOutOfTheIri( string $term ): void {
		$this->assertNull( $this->expander()->expand( $term ) );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideInjectionTerms(): iterable {
		yield 'CURIE local part closes the IRIREF' => [ 'dc:title> <s> <p> <o> .# ' ];
		yield 'CURIE local part with a space' => [ 'edm:is Shown At' ];
		yield 'CURIE local part with a double quote' => [ 'dc:tit"le' ];
		yield 'absolute IRI closing the IRIREF' => [ 'http://evil.example/> <s> <p> <o' ];
		yield 'absolute IRI with a space' => [ 'http://evil.example/a b' ];
		yield 'absolute IRI with a backtick' => [ 'http://evil.example/a`b' ];
		yield 'absolute IRI with a control character' => [ "http://evil.example/a\x01b" ];
		yield 'absolute IRI with a brace' => [ 'http://evil.example/a{b}' ];
	}

	public function testKeepsUnicodeRawInAnAbsoluteIri(): void {
		$this->assertSame(
			'http://example.org/persoon/Naïve',
			$this->expander()->expand( 'http://example.org/persoon/Naïve' )?->value
		);
	}

	public function testIsSafeAbsoluteIriRejectsARelativeIri(): void {
		$this->assertFalse( CurieExpander::isSafeAbsoluteIri( '/relative/path' ) );
	}

	public function testIsSafeAbsoluteIriRejectsAnIriThatBreaksOutOfThePrefixTable(): void {
		$this->assertFalse( CurieExpander::isSafeAbsoluteIri( 'http://evil.example/"> .# ' ) );
	}

	public function testIsSafeAbsoluteIriAcceptsAcleanNamespaceIri(): void {
		$this->assertTrue( CurieExpander::isSafeAbsoluteIri( 'http://www.europeana.eu/schemas/edm/' ) );
	}

	public function testIsValidPrefixLabelAcceptsALabelWithLettersDigitsUnderscoreAndHyphen(): void {
		$this->assertTrue( CurieExpander::isValidPrefixLabel( 'edm' ) );
		$this->assertTrue( CurieExpander::isValidPrefixLabel( 'rdaGr2' ) );
		$this->assertTrue( CurieExpander::isValidPrefixLabel( 'a_b-c9' ) );
	}

	/**
	 * @dataProvider unsafePrefixLabelProvider
	 */
	public function testIsValidPrefixLabelRejectsAnUnsafeLabel( string $label ): void {
		$this->assertFalse( CurieExpander::isValidPrefixLabel( $label ) );
	}

	public static function unsafePrefixLabelProvider(): iterable {
		yield 'empty' => [ '' ];
		yield 'leading digit' => [ '1edm' ];
		yield 'contains a space' => [ 'a b' ];
		yield 'contains a colon' => [ 'ex:local' ];
		yield 'contains an angle bracket' => [ 'a<b' ];
		yield 'breaks out of the @prefix line with a newline and a triple' => [ "x\nedm:injected a edm:Pwned .\n#" ];
	}

}
