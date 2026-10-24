<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType
 */
class UrlTypeValidateTest extends TestCase {

	private UrlType $type;

	protected function setUp(): void {
		$this->type = new UrlType();
	}

	public function testOptionalAndEmptyReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue(),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndEmptyReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue(),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testNonStringValueReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new NumberValue( 42 ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( [], $violations );
	}

	/**
	 * @dataProvider webAddressProvider
	 */
	public function testValidUrlReturnsNoViolations( string $url ): void {
		$violations = $this->type->validate(
			new StringValue( $url ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public static function webAddressProvider(): iterable {
		yield 'https basic' => [ 'https://example.com' ];
		yield 'http basic' => [ 'http://example.com' ];
		yield 'domain without protocol' => [ 'example.com' ];
		yield 'www without protocol' => [ 'www.example.com' ];
		yield 'localhost with port' => [ 'http://localhost:8080' ];
		yield 'ip address' => [ 'http://192.168.1.1' ];
		yield 'https with path' => [ 'https://example.com/path' ];
		yield 'https with trailing slash' => [ 'https://example.com/' ];
		yield 'https with query' => [ 'https://example.com?query=value' ];
		yield 'https with fragment' => [ 'https://example.com#fragment' ];
		yield 'https with path, query, and fragment' => [ 'https://example.com/path?query=value#fragment' ];
		yield 'Wikimedia Commons file' => [ 'https://commons.wikimedia.org/wiki/File:Entrada_da_Ba%C3%ADa_de_Guanabara_vista_do_alto_do_Corcovado,_Acervo_do_Instituto_Moreira_Salles.jpg' ];
		yield 'Wikimedia Commons file with a non-ASCII letter' => [ 'https://commons.wikimedia.org/wiki/File:Entrada_da_Baía_de_Guanabara_vista_do_alto_do_Corcovado,_Acervo_do_Instituto_Moreira_Salles.jpg' ];
		yield 'parentheses in path' => [ 'https://en.wikipedia.org/wiki/Mercury_(planet)' ];
		yield 'commas and a url in query' => [ 'https://example.com/search?q=a,b&redirect=https://x.org/' ];
		yield 'dot in fragment' => [ 'https://en.wikipedia.org/wiki/Foo#Section.2C_bar' ];
		yield 'at sign in path' => [ 'https://example.com/@user' ];
	}

	/**
	 * @dataProvider nonWebAddressProvider
	 */
	public function testInvalidUrlReturnsInvalidUrlViolation( string $value ): void {
		$violations = $this->type->validate(
			new StringValue( $value ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-url', $violations[0]->code );
		$this->assertSame( 0, $violations[0]->valuePartIndex );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public static function nonWebAddressProvider(): iterable {
		yield 'hyphenated word' => [ 'not-a-url' ];
		yield 'bare word example' => [ 'example' ];
		yield 'alpha only' => [ 'abc' ];
		yield 'digits only' => [ '123' ];
		yield 'underscore url' => [ 'invalid_url' ];
		yield 'http with underscore' => [ 'http://invalid_url' ];
		yield 'ftp protocol' => [ 'ftp://example.com' ];
		yield 'file protocol' => [ 'file:///path/to/file' ];
		yield 'https with spaces in path' => [ 'https://example.com/path with spaces' ];
		yield 'tab in query' => [ "https://example.com/?a=1\tb=2" ];
		yield 'less-than sign in path' => [ 'https://example.com/a<b' ];
		yield 'backslash in path' => [ 'https://example.com/a\\b' ];
		yield 'user info before the host' => [ 'https://example.org@example.com' ];
	}

	public function testEachInvalidPartReturnsIndexedViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'https://example1.com', 'invalid-1', 'https://example2.com', 'invalid-2', 'https://example3.com' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 2, $violations );
		$this->assertSame( 'invalid-url', $violations[0]->code );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
		$this->assertSame( 'invalid-url', $violations[1]->code );
		$this->assertSame( 3, $violations[1]->valuePartIndex );
	}

	public function testUniqueItemsWithDuplicatesReturnsUniqueViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'https://foo.com', 'https://example.com', 'https://bar.com', 'https://example.com', 'https://baz.com' ),
			$this->newProperty( required: false, uniqueItems: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'unique', $violations[0]->code );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testUniqueItemsWithAllDistinctReturnsNoUniqueViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'https://example1.com', 'https://example2.com', 'https://example3.com' ),
			$this->newProperty( required: false, uniqueItems: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndSingleEmptyStringPartReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue( '' ),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testRequiredAndWhitespaceOnlyPartReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue( '   ' ),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testEmptyStringPartIsSkippedWithNoViolation(): void {
		$violations = $this->type->validate(
			new StringValue( '' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	private function newProperty( bool $required, bool $uniqueItems = false ): UrlProperty {
		return UrlProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'multiple' => true, 'uniqueItems' => $uniqueItems ],
		);
	}

}
