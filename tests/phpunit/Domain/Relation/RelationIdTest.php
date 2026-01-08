<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;

#[CoversClass( RelationId::class )]
class RelationIdTest extends TestCase {

	#[DataProvider( 'validGuidProvider' )]
	public function testInitialisationWithCorrectUuid( string $validGuid ): void {
		$RelationId = new RelationId( $validGuid );
		$this->assertSame( $validGuid, $RelationId->asString() );
	}

	public static function validGuidProvider(): iterable {
		yield [ 'r11111111111111' ];
		yield [ 'r7uX6keGTokz16g' ];
		yield [ 'r7uX6keGXDSjdVT' ];
		yield [ 'rStepAsideUU1D7' ];
		yield [ 'rZzZzZzZzZzZzZz' ];
	}

	#[DataProvider( 'validGuidProvider' )]
	public function testEquals( string $validGuid ): void {
		$this->assertTrue( ( new RelationId( $validGuid ) )->equals( new RelationId( $validGuid ) ) );
		$this->assertFalse( ( new RelationId( $validGuid ) )->equals( new RelationId( 'rNyanCatNyanCat' ) ) );
	}

	#[DataProvider( 'invalidGuidProvider' )]
	public function testInitialisationWithInvalidUuid( string $invalidGuid ): void {
		$this->expectException( \InvalidArgumentException::class );
		new RelationId( $invalidGuid );
	}

	public static function invalidGuidProvider(): iterable {
		yield 'empty string' => [ '' ];
		yield 'too short' => [ 'r1111111111111' ];
		yield 'too long' => [ 'r111111111111111' ];
		yield 'missing prefix' => [ '11111111111111' ];
		yield 'wrong prefix' => [ 's11111111111111' ];
		yield 'invalid char' => [ 'r1111111_111111' ];
		yield 'base62 but not base58' => [ 'r11111110111111' ];
		yield 'definitely invalid' => [ '~=[,,_,,]:3' ];
	}

	#[DataProvider( 'invalidGuidProvider' )]
	public function testIsValidFails( string $invalidGuid ): void {
		$this->assertFalse( RelationId::isValid( $invalidGuid ) );
	}

	#[DataProvider( 'validGuidProvider' )]
	public function testIsValidPasses( string $validGuid ): void {
		$this->assertTrue( RelationId::isValid( $validGuid ) );
	}

}
