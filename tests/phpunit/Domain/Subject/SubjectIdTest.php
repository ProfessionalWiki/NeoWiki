<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId
 */
class SubjectIdTest extends TestCase {

	/**
	 * @dataProvider validGuidProvider
	 */
	public function testInitialisationWithCorrectUuid( string $validGuid ): void {
		$subjectId = new SubjectId( $validGuid );
		$this->assertSame( $validGuid, $subjectId->text );
	}

	public static function validGuidProvider(): iterable {
		yield [ 's11111111111111' ];
		yield [ 's7uX6keGTokz16g' ];
		yield [ 's7uX6keGXDSjdVT' ];
		yield [ 'sStepAsideUU1D7' ];
		yield [ 'sZzZzZzZzZzZzZz' ];
	}

	/**
	 * @dataProvider validGuidProvider
	 */
	public function testEquals( string $validGuid ): void {
		$this->assertTrue( ( new SubjectId( $validGuid ) )->equals( new SubjectId( $validGuid ) ) );
		$this->assertFalse(
			( new SubjectId( $validGuid ) )->equals( new SubjectId( 'sNyanCatNyanCat' ) )
		);
	}

	/**
	 * @dataProvider invalidGuidProvider
	 */
	public function testInitialisationWithInvalidUuid( string $invalidGuid ): void {
		$this->expectException( \InvalidArgumentException::class );
		new SubjectId( $invalidGuid );
	}

	public static function invalidGuidProvider(): iterable {
		yield 'empty string' => [ '' ];
		yield 'too short' => [ 's1111111111111' ];
		yield 'too long' => [ 's111111111111111' ];
		yield 'missing prefix' => [ '11111111111111' ];
		yield 'wrong prefix' => [ 'r11111111111111' ];
		yield 'invalid char' => [ 's1111111_111111' ];
		yield 'base62 but not base58' => [ 's11111110111111' ];
		yield 'definitely invalid' => [ '~=[,,_,,]:3' ];
	}

	/**
	 * @dataProvider invalidGuidProvider
	 */
	public function testIsValidFails( string $invalidGuid ): void {
		$this->assertFalse( SubjectId::isValid( $invalidGuid ) );
	}

	/**
	 * @dataProvider validGuidProvider
	 */
	public function testIsValidPasses( string $validGuid ): void {
		$this->assertTrue( SubjectId::isValid( $validGuid ) );
	}

}
