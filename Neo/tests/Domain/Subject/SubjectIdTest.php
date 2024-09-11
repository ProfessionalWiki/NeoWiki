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
		yield [ '00000000-0000-0000-0000-000000000000' ];
		yield [ '00000000-0000-0000-0000-000000000001' ];
		yield [ '01833ce0-3486-7bfd-84a1-ad157cf64005' ];
	}

	#[DataProvider( 'validGuidProvider' )]
	public function testEquals( string $validGuid ): void {
		$this->assertTrue( ( new SubjectId( $validGuid ) )->equals( new SubjectId( $validGuid ) ) );
		$this->assertFalse(
			( new SubjectId( $validGuid ) )->equals( new SubjectId( '40400000-0000-0000-0000-000000000000' ) )
		);
	}

	#[DataProvider( 'invalidGuidProvider' )]
	public function testInitialisationWithInvalidUuid( string $invalidGuid ): void {
		$this->expectException( \InvalidArgumentException::class );
		new SubjectId( $invalidGuid );
	}

	public static function invalidGuidProvider(): iterable {
		yield 'empty string' => [ '' ];
		yield 'invalid character' => [ '00000000-0000-0000-0000-00000000000z' ];
		yield 'missing character' => [ '00000000-0000-0000-0000-00000000000' ];
		yield 'extra character' => [ '00000000-0000-0000-0000-0000000000000' ];
		yield 'definitely invalid' => [ '~=[,,_,,]:3' ];
	}

}
