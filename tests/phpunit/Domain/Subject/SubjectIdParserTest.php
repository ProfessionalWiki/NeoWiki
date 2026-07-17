<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId
 */
class SubjectIdParserTest extends TestCase {

	/**
	 * @dataProvider validVectorProvider
	 */
	public function testParsesValidVector( string $input, string $canonicalText, ?string $source, string $localId ): void {
		$id = $this->newParser()->parse( $input );

		$this->assertSame( $canonicalText, $id->text );
		$this->assertSame( $source, $id->getSource() );
		$this->assertSame( $localId, $id->getLocalId() );
	}

	/**
	 * @dataProvider validVectorProvider
	 */
	public function testCanonicalTextParsesToItself( string $input, string $canonicalText ): void {
		$this->assertSame( $canonicalText, $this->newParser()->parse( $canonicalText )->text );
	}

	/**
	 * @dataProvider invalidVectorProvider
	 */
	public function testRejectsInvalidVector( string $input ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->newParser()->parse( $input );
	}

	public function testAcceptsLocalSourceKeyOutsideTheKeyGrammar(): void {
		$parser = new SubjectIdParser( 'weird~key' );

		$this->assertSame( 's11111111111111', $parser->parse( 's11111111111111' )->text );
	}

	private function newParser(): SubjectIdParser {
		return new SubjectIdParser( self::vectors()['localSourceKey'] );
	}

	public static function validVectorProvider(): iterable {
		foreach ( self::vectors()['cases'] as $case ) {
			if ( $case['valid'] ) {
				yield $case['name'] => [ $case['input'], $case['canonicalText'], $case['source'], $case['localId'] ];
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
		return json_decode( file_get_contents( __DIR__ . '/../../../subject-id-vectors.json' ), true );
	}

}
