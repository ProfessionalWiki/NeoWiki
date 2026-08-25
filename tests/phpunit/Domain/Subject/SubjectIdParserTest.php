<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\NeoWiki\Tests\Data\SubjectIdVectors;

/**
 * The shared vectors in tests/vectors/subject-ids.json, which the TypeScript suite runs against its
 * own SubjectIdParser, so both implementations answer the same for every case.
 *
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId
 */
class SubjectIdParserTest extends TestCase {

	/**
	 * @dataProvider wellFormedIdProvider
	 */
	public function testParsesWellFormedId( string $input, string $text, ?string $source, string $localId ): void {
		$id = $this->newParser()->parseOrThrow( $input );

		$this->assertSame( $text, $id->text );
		$this->assertSame( $source, $id->source );
		$this->assertSame( $localId, $id->localId );
	}

	public static function wellFormedIdProvider(): iterable {
		foreach ( SubjectIdVectors::valid() as $vector ) {
			yield $vector['case'] => [ $vector['input'], $vector['text'], $vector['source'], $vector['localId'] ];
		}
	}

	/**
	 * @dataProvider malformedIdProvider
	 */
	public function testReturnsNullForMalformedId( string $input ): void {
		$this->assertNull( $this->newParser()->parse( $input ) );
	}

	/**
	 * @dataProvider malformedIdProvider
	 */
	public function testThrowsForMalformedId( string $input ): void {
		$this->expectException( InvalidArgumentException::class );

		$this->newParser()->parseOrThrow( $input );
	}

	public static function malformedIdProvider(): iterable {
		foreach ( SubjectIdVectors::invalid() as $vector ) {
			yield $vector['case'] => [ $vector['input'] ];
		}
	}

	public function testExplicitlyLocalIdEqualsItsBareForm(): void {
		$parser = $this->newParser();

		$this->assertTrue(
			$parser->parseOrThrow( SubjectIdVectors::localSourceKey() . ':s11111111111111' )
				->equals( new SubjectId( 's11111111111111' ) )
		);
	}

	public function testAnotherWikisKeyIsNotCanonicalizedAway(): void {
		$id = $this->newParser()->parseOrThrow( 'otherwiki:s11111111111111' );

		$this->assertSame( 'otherwiki', $id->source );
	}

	private function newParser(): SubjectIdParser {
		return new SubjectIdParser( SubjectIdVectors::localSourceKey() );
	}

}
