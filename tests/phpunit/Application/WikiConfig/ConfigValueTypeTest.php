<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\WikiConfig;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigValueType;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigValueType
 */
class ConfigValueTypeTest extends TestCase {

	public function testBooleanAcceptsTrueAndFalse(): void {
		$this->assertTrue( ConfigValueType::Boolean->isValidValue( true ) );
		$this->assertTrue( ConfigValueType::Boolean->isValidValue( false ) );
	}

	/**
	 * @dataProvider notABooleanProvider
	 */
	public function testBooleanRejectsAValueThatIsNoBoolean( mixed $value ): void {
		$this->assertFalse( ConfigValueType::Boolean->isValidValue( $value ) );
	}

	public static function notABooleanProvider(): iterable {
		yield 'the string true' => [ 'true' ];
		yield 'one' => [ 1 ];
		yield 'null' => [ null ];
	}

	public function testLanguageListAcceptsLanguageTags(): void {
		$this->assertTrue( ConfigValueType::LanguageList->isValidValue( [ 'de', 'fr-CH', 'it' ] ) );
	}

	public function testLanguageListAcceptsAnEmptyList(): void {
		$this->assertTrue( ConfigValueType::LanguageList->isValidValue( [] ) );
	}

	/**
	 * @dataProvider notALanguageListProvider
	 */
	public function testLanguageListRejectsAValueThatIsNoListOfTags( mixed $value ): void {
		$this->assertFalse( ConfigValueType::LanguageList->isValidValue( $value ) );
	}

	public static function notALanguageListProvider(): iterable {
		yield 'a single tag' => [ 'de' ];
		yield 'an object' => [ [ 'main' => 'de' ] ];
		yield 'a list holding a number' => [ [ 'de', 42 ] ];
		yield 'a list holding a malformed tag' => [ [ 'de', 'fr_CH', 'it' ] ];
	}

}
