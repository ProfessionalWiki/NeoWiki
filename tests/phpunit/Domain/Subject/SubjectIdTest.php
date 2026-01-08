<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Subject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

#[CoversClass( SubjectId::class )]
class SubjectIdTest extends TestCase {

	#[DataProvider( 'validGuidProvider' )]
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

	#[DataProvider( 'validGuidProvider' )]
	public function testEquals( string $validGuid ): void {
		$this->assertTrue( ( new SubjectId( $validGuid ) )->equals( new SubjectId( $validGuid ) ) );
		$this->assertFalse(
			( new SubjectId( $validGuid ) )->equals( new SubjectId( 'sNyanCatNyanCat' ) )
		);
	}

	#[DataProvider( 'invalidGuidProvider' )]
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

	#[DataProvider( 'invalidGuidProvider' )]
	public function testIsValidFails( string $invalidGuid ): void {
		$this->assertFalse( SubjectId::isValid( $invalidGuid ) );
	}

	#[DataProvider( 'validGuidProvider' )]
	public function testIsValidPasses( string $validGuid ): void {
		$this->assertTrue( SubjectId::isValid( $validGuid ) );
	}

}
