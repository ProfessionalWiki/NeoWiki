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
	 * @dataProvider vectorProvider
	 *
	 * @param string|array<string, string> $input
	 * @param string|array<string, string> $serialized
	 */
	public function testParsesVector( string|array $input, string|array $serialized, ?string $source, string $schemaName ): void {
		$reference = $this->newParser()->parse( $input );

		$this->assertSame( $source, $reference->getSource() );
		$this->assertSame( $schemaName, $reference->getName()->getText() );
		$this->assertSame( $serialized, $reference->toSerializedValue() );
	}

	/**
	 * @dataProvider vectorProvider
	 *
	 * @param string|array<string, string> $input
	 * @param string|array<string, string> $serialized
	 */
	public function testSerializedFormParsesToItself( string|array $input, string|array $serialized ): void {
		$this->assertSame( $serialized, $this->newParser()->parse( $serialized )->toSerializedValue() );
	}

	public function testAcceptsLocalSourceKeyOutsideTheSchemaNameGrammar(): void {
		$parser = new SchemaReferenceParser( 'weird~key' );

		$reference = $parser->parse( [ 'source' => 'weird~key', 'name' => 'Person' ] );

		$this->assertNull( $reference->getSource() );
		$this->assertSame( 'Person', $reference->getName()->getText() );
	}

	private function newParser(): SchemaReferenceParser {
		return new SchemaReferenceParser( self::vectors()['localSourceKey'] );
	}

	public static function vectorProvider(): iterable {
		foreach ( self::vectors()['cases'] as $case ) {
			yield $case['name'] => [ $case['input'], $case['serialized'], $case['source'], $case['schemaName'] ];
		}
	}

	private static function vectors(): array {
		return json_decode( file_get_contents( __DIR__ . '/schema-reference-vectors.json' ), true );
	}

}
