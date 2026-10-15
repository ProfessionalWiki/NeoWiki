<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\RowChanges;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\RowChanges
 */
class RowChangesTest extends TestCase {

	private const array ADA = [ 'name' => 'Ada' ];
	private const array GRACE = [ 'name' => 'Grace' ];
	private const array ALAN = [ 'name' => 'Alan' ];

	public function testRowsThatAreRightAlreadyNeedNoChange(): void {
		$rows = [ 'a' => self::ADA, 'g' => self::GRACE ];

		$this->assertTrue( RowChanges::between( $rows, $rows )->isEmpty() );
	}

	public function testOnlyTheRowWhoseValuesChangedIsRewritten(): void {
		$renamed = [ 'name' => 'Grace Hopper' ];

		$changes = RowChanges::between(
			[ 'a' => self::ADA, 'g' => self::GRACE, 't' => self::ALAN ],
			[ 'a' => self::ADA, 'g' => $renamed, 't' => self::ALAN ]
		);

		$this->assertSame( [ 'g' => self::GRACE ], $changes->deletions );
		$this->assertSame( [ 'g' => $renamed ], $changes->insertions );
	}

	public function testARowNoLongerWantedIsOnlyDeleted(): void {
		$changes = RowChanges::between(
			[ 'a' => self::ADA, 'g' => self::GRACE, 't' => self::ALAN ],
			[ 'a' => self::ADA, 't' => self::ALAN ]
		);

		$this->assertSame( [ 'g' => self::GRACE ], $changes->deletions );
		$this->assertSame( [], $changes->insertions );
		$this->assertFalse( $changes->isEmpty() );
	}

	public function testANewRowIsOnlyInserted(): void {
		$changes = RowChanges::between(
			[ 'a' => self::ADA, 't' => self::ALAN ],
			[ 'a' => self::ADA, 'g' => self::GRACE, 't' => self::ALAN ]
		);

		$this->assertSame( [], $changes->deletions );
		$this->assertSame( [ 'g' => self::GRACE ], $changes->insertions );
		$this->assertFalse( $changes->isEmpty() );
	}

	public function testValuesThatReadAsTheSameNumberAreStillDifferent(): void {
		$changes = RowChanges::between( [ 'a' => [ 'name' => '1e3' ] ], [ 'a' => [ 'name' => '1000' ] ] );

		$this->assertFalse( $changes->isEmpty() );
	}

}
