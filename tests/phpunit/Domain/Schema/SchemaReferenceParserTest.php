<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceParser;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceParser
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference
 */
class SchemaReferenceParserTest extends TestCase {

	/**
	 * @dataProvider validVectorProvider
	 *
	 * @param string|array<string, string> $input
	 * @param string|array<string, string> $serialized
	 */
	public function testParsesValidVector( string|array $input, string|array $serialized, ?string $source, string $schemaName ): void {
		$reference = $this->newParser()->parse( $input );

		$this->assertSame( $source, $reference->getSource() );
		$this->assertSame( $schemaName, $reference->getName()->getText() );
		$this->assertSame( $serialized, $reference->toSerializedValue() );
	}

	/**
	 * @dataProvider validVectorProvider
	 *
	 * @param string|array<string, string> $input
	 * @param string|array<string, string> $serialized
	 */
	public function testSerializedFormParsesToItself( string|array $input, string|array $serialized ): void {
		$this->assertSame( $serialized, $this->newParser()->parse( $serialized )->toSerializedValue() );
	}

	/**
	 * @dataProvider invalidVectorProvider
	 *
	 * @param string|array<string, string> $input
	 */
	public function testRejectsInvalidVector( string|array $input ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->newParser()->parse( $input );
	}

	public function testAcceptsLocalSourceKeyOutsideTheKeyGrammar(): void {
		$parser = new SchemaReferenceParser( 'weird~key' );

		$this->assertSame( 'Person', $parser->parse( 'Person' )->toSerializedValue() );
	}

	private function newParser(): SchemaReferenceParser {
		return new SchemaReferenceParser( self::vectors()['localSourceKey'] );
	}

	public static function validVectorProvider(): iterable {
		foreach ( self::vectors()['cases'] as $case ) {
			if ( $case['valid'] ) {
				yield $case['name'] => [ $case['input'], $case['serialized'], $case['source'], $case['schemaName'] ];
			}
		}
	}

	public static function invalidVectorProvider(): iterable {
		foreach ( self::vectors()['cases'] as $case ) {
			if ( !$case['valid'] ) {
				yield $case['name'] => [ $case['input'] ];
			}
		}
	}

	private static function vectors(): array {
		return json_decode( file_get_contents( __DIR__ . '/schema-reference-vectors.json' ), true );
	}

}
