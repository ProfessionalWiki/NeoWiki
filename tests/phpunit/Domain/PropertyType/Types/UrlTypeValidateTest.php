<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
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

	public function testValidUrlReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( 'https://example.com' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testInvalidUrlReturnsInvalidUrlViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'not-a-url' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-url', $violations[0]->code );
		$this->assertSame( 0, $violations[0]->valuePartIndex );
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

	/**
	 * @dataProvider isValidUrlProvider
	 */
	public function testIsValidUrlMatchesTsBehavior( string $url, bool $expected ): void {
		$violations = $this->type->validate(
			new StringValue( $url ),
			$this->newProperty( required: false ),
		);

		if ( $expected ) {
			$this->assertSame( [], $violations, "Expected '$url' to be valid" );
		} else {
			$this->assertCount( 1, $violations, "Expected '$url' to fail" );
			$this->assertSame( 'invalid-url', $violations[0]->code );
		}
	}

	public static function isValidUrlProvider(): array {
		return [
			// Empty string: validate() skips empty trimmed parts, so no violation — tested separately below
			'https with query' => [ 'https://example.com?query=value', true ],
			'https with fragment' => [ 'https://example.com#fragment', true ],
			'https with path, query, and fragment' => [ 'https://example.com/path?query=value#fragment', true ],
			'https with spaces in path' => [ 'https://example.com/path with spaces', false ],
			'ftp protocol' => [ 'ftp://example.com', false ],
			'www without protocol' => [ 'www.example.com', true ],
			'localhost with port' => [ 'http://localhost:8080', true ],
			'ip address' => [ 'http://192.168.1.1', true ],
			'file protocol' => [ 'file:///path/to/file', false ],
			'https basic' => [ 'https://example.com', true ],
			'http basic' => [ 'http://example.com', true ],
			'https with path' => [ 'https://example.com/path', true ],
			'bare word example' => [ 'example', false ],
			'domain without protocol' => [ 'example.com', true ],
			'underscore url' => [ 'invalid_url', false ],
			'http with underscore' => [ 'http://invalid_url', false ],
			'digits only' => [ '123', false ],
			'alpha only' => [ 'abc', false ],
		];
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
