<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Value;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText
 */
class MonolingualTextTest extends TestCase {

	public function testTextIsTrimmed(): void {
		$this->assertSame( 'Zinema', ( new MonolingualText( "  Zinema\n", 'eu' ) )->text );
	}

	public function testLanguageIsLowercased(): void {
		$this->assertSame( 'pt-br', ( new MonolingualText( 'Cinema', 'pt-BR' ) )->language );
	}

	public function testToScalarsGivesTextAndLanguage(): void {
		$this->assertSame(
			[ 'text' => 'Zinema', 'language' => 'eu' ],
			( new MonolingualText( 'Zinema', 'eu' ) )->toScalars()
		);
	}

	/**
	 * @dataProvider validLanguageProvider
	 */
	public function testAcceptsAWellFormedLanguageTag( string $language, string $expected ): void {
		$this->assertSame( $expected, ( new MonolingualText( 'x', $language ) )->language );
	}

	public static function validLanguageProvider(): iterable {
		yield 'primary subtag only' => [ 'eu', 'eu' ];
		yield 'three-letter subtag' => [ 'und', 'und' ];
		yield 'region subtag' => [ 'pt-BR', 'pt-br' ];
		yield 'script and region' => [ 'zh-Hant-TW', 'zh-hant-tw' ];
	}

	/**
	 * @dataProvider invalidLanguageProvider
	 */
	public function testRejectsAMalformedLanguageTag( string $language ): void {
		$this->expectException( InvalidArgumentException::class );
		new MonolingualText( 'x', $language );
	}

	public static function invalidLanguageProvider(): iterable {
		yield 'empty' => [ '' ];
		yield 'underscore separator' => [ 'pt_BR' ];
		yield 'trailing space' => [ 'eu ' ];
		yield 'trailing newline' => [ "eu\n" ];
		yield 'empty trailing subtag' => [ 'eu-' ];
		yield 'subtag over eight characters' => [ 'esperantoo' ];
		yield 'digits in the primary subtag' => [ '1u' ];
		yield 'turtle injection' => [ 'eu"@es' ];
	}

}
