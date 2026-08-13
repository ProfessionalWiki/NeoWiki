<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Value;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Value\StringValue
 */
class StringValueTest extends TestCase {

	/**
	 * @dataProvider contentlessPartsProvider
	 */
	public function testPartsWithoutContentAreNotStored( StringValue $value ): void {
		$this->assertSame( [], $value->toScalars() );
	}

	/**
	 * @dataProvider contentlessPartsProvider
	 */
	public function testValueOfPartsWithoutContentIsEmpty( StringValue $value ): void {
		$this->assertTrue( $value->isEmpty() );
	}

	public static function contentlessPartsProvider(): iterable {
		yield 'empty part' => [ new StringValue( '' ) ];
		yield 'space' => [ new StringValue( ' ' ) ];
		yield 'tab and newline' => [ new StringValue( "\t\n " ) ];
		yield 'several blank parts' => [ new StringValue( '', ' ' ) ];
	}

	public function testValueWithoutPartsIsEmpty(): void {
		$this->assertTrue( ( new StringValue() )->isEmpty() );
	}

	public function testPartsWithContentAreStoredAsWritten(): void {
		$value = new StringValue( 'a', '', ' b ' );

		$this->assertSame( [ 'a', ' b ' ], $value->toScalars() );
	}

	public function testValueWithContentIsNotEmpty(): void {
		$this->assertFalse( ( new StringValue( '', 'a' ) )->isEmpty() );
	}

	public function testZeroIsContent(): void {
		$value = new StringValue( '0' );

		$this->assertSame( [ '0' ], $value->toScalars() );
		$this->assertFalse( $value->isEmpty() );
	}

}
