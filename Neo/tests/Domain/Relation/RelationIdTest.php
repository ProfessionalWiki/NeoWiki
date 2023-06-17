<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Relation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Relation\RelationId
 */
class RelationIdTest extends TestCase {

	/**
	 * @dataProvider validGuidProvider
	 */
	public function testInitialisationWithCorrectUuid( string $validGuid ): void {
		$RelationId = new RelationId( $validGuid );
		$this->assertSame( $validGuid, $RelationId->asString() );
	}

	public static function validGuidProvider(): iterable {
		yield [ '00000000-0000-0000-0000-000000000000' ];
		yield [ '00000000-0000-0000-0000-000000000001' ];
		yield [ '01833ce0-3486-7bfd-84a1-ad157cf64005' ];
	}

	/**
	 * @dataProvider validGuidProvider
	 */
	public function testEquals( string $validGuid ): void {
		$this->assertTrue( ( new RelationId( $validGuid ) )->equals( new RelationId( $validGuid ) ) );
		$this->assertFalse( ( new RelationId( $validGuid ) )->equals( new RelationId( '40400000-0000-0000-0000-000000000000' ) ) );
	}

	/**
	 * @dataProvider invalidGuidProvider
	 */
	public function testInitialisationWithInvalidUuid( string $invalidGuid ): void {
		$this->expectException( \InvalidArgumentException::class );
		new RelationId( $invalidGuid );
	}

	public static function invalidGuidProvider(): iterable {
		yield 'empty string' => [ '' ];
		yield 'invalid character' => [ '00000000-0000-0000-0000-00000000000z' ];
		yield 'missing character' => [ '00000000-0000-0000-0000-00000000000' ];
		yield 'extra character' => [ '00000000-0000-0000-0000-0000000000000' ];
		yield 'definitely invalid' => [ '~=[,,_,,]:3' ];
	}

}
