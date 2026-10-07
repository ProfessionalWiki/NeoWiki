<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Tests\JsonSchemaAssertions;
use ProfessionalWiki\RedHerb\ColorProperty;
use ProfessionalWiki\RedHerb\ColorType;

/**
 * @covers \ProfessionalWiki\RedHerb\ColorProperty
 * @covers \ProfessionalWiki\RedHerb\ColorType
 */
class ColorPropertyTest extends TestCase {

	use JsonSchemaAssertions;

	public function testPropertyTypeIsColor(): void {
		$property = $this->buildProperty();

		$this->assertSame( 'color', $property->getPropertyType() );
	}

	public function testAllowedColorsEmptyByDefault(): void {
		$property = $this->buildProperty();

		$this->assertSame( [], $property->getAllowedColors() );
		$this->assertFalse( $property->hasAllowedColors() );
	}

	public function testAllowedColorsFromJson(): void {
		$property = ColorProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'allowedColors' => [ '#ff0000', '#00ff00', '#0000ff' ] ]
		);

		$this->assertSame( [ '#ff0000', '#00ff00', '#0000ff' ], $property->getAllowedColors() );
		$this->assertTrue( $property->hasAllowedColors() );
	}

	public function testSerializationRoundTrip(): void {
		$property = ColorProperty::fromPartialJson(
			new PropertyCore( description: 'A colour', required: true, default: '#abcdef' ),
			[ 'allowedColors' => [ '#abcdef', '#123456' ] ]
		);

		$json = $property->toJson();

		$this->assertSame( 'color', $json['type'] );
		$this->assertSame( 'A colour', $json['description'] );
		$this->assertTrue( $json['required'] );
		$this->assertSame( '#abcdef', $json['default'] );
		$this->assertSame( [ '#abcdef', '#123456' ], $json['allowedColors'] );
	}

	public function testBuildPropertyDefinitionFromJsonViaType(): void {
		$type = new ColorType();
		$core = new PropertyCore( description: '', required: false, default: null );

		$property = $type->buildPropertyDefinitionFromJson( $core, [
			'allowedColors' => [ '#112233' ],
		] );

		$this->assertInstanceOf( ColorProperty::class, $property );
		$this->assertSame( [ '#112233' ], $property->getAllowedColors() );
	}

	public function testFromPartialJsonWithoutAllowedColorsReturnsEmpty(): void {
		$property = ColorProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[]
		);

		$this->assertSame( [], $property->getAllowedColors() );
	}

	/**
	 * @dataProvider malformedAllowedColorsProvider
	 */
	public function testConstructorRejectsMalformedAllowedColors( array $malformed ): void {
		$this->expectException( \InvalidArgumentException::class );

		new ColorProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			allowedColors: $malformed,
		);
	}

	public static function malformedAllowedColorsProvider(): iterable {
		yield 'missing hash' => [ [ 'ff0000' ] ];
		yield 'too short' => [ [ '#fff' ] ];
		yield 'too long' => [ [ '#ff00000' ] ];
		yield 'invalid char' => [ [ '#gggggg' ] ];
		yield 'uppercase mixed' => [ [ '#FF00zz' ] ];
		yield 'empty string' => [ [ '' ] ];
		yield 'not a string' => [ [ 123 ] ];
	}

	public function testConstructorAcceptsUppercaseHex(): void {
		$property = new ColorProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			allowedColors: [ '#ABCDEF' ],
		);

		$this->assertSame( [ '#ABCDEF' ], $property->getAllowedColors() );
	}

	public function testConstructorAcceptsValidHexDefault(): void {
		$property = new ColorProperty(
			core: new PropertyCore( description: '', required: false, default: '#abcdef' ),
			allowedColors: [],
		);

		$this->assertSame( '#abcdef', $property->getDefault() );
	}

	/**
	 * @dataProvider malformedDefaultProvider
	 */
	public function testConstructorRejectsMalformedDefault( mixed $malformed ): void {
		$this->expectException( \InvalidArgumentException::class );

		new ColorProperty(
			core: new PropertyCore( description: '', required: false, default: $malformed ),
			allowedColors: [],
		);
	}

	public static function malformedDefaultProvider(): iterable {
		yield 'missing hash' => [ 'ff0000' ];
		yield 'too short' => [ '#fff' ];
		yield 'too long' => [ '#ff00000' ];
		yield 'invalid char' => [ '#gggggg' ];
		yield 'empty string' => [ '' ];
		yield 'not a string' => [ 42 ];
	}

	public function testValuePartsAreHexColors(): void {
		$this->assertSame(
			[
				'type' => 'array',
				'items' => [ 'type' => 'string', 'pattern' => '^#[0-9a-fA-F]{6}$' ],
				'maxItems' => 1,
			],
			$this->buildProperty()->toJsonSchema()
		);
	}

	public function testAllowedColorsBecomeAnEnum(): void {
		$value = $this->buildProperty( [ '#aabbcc', '#ddeeff' ] )->toJsonSchema();

		$this->assertSame( [ '#aabbcc', '#ddeeff' ], $value['items']['enum'] );
	}

	/**
	 * @dataProvider colorProvider
	 */
	public function testAColorIsJudgedAsValidateJudgesIt( string $color, bool $valid ): void {
		$definition = $this->buildProperty( [ '#aabbcc' ] );

		$this->assertSame(
			$valid,
			$this->jsonSchemaAccepts( $definition->toJsonSchema(), [ $color ] ),
			'the JSON Schema'
		);
		$this->assertSame(
			$valid,
			( new ColorType() )->validate( new StringValue( $color ), $definition ) === [],
			'validate()'
		);
	}

	public static function colorProvider(): iterable {
		yield 'an allowed color' => [ '#aabbcc', true ];
		yield 'a color outside the allow list' => [ '#112233', false ];
		yield 'not a color at all' => [ 'red', false ];
	}

	/**
	 * @param list<string> $allowedColors
	 */
	private function buildProperty( array $allowedColors = [] ): ColorProperty {
		return new ColorProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			allowedColors: $allowedColors,
		);
	}

}
