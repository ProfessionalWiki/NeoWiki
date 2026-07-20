<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectIdMinter;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionIdGenerator;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\IncrementalIdGenerator;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SequenceIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectIdMinter
 */
class SubjectIdMinterTest extends TestCase {

	public function testMintsTheRequestedNumberOfIds(): void {
		$ids = $this->newMinter( new IncrementalIdGenerator() )->mint( 5 );

		$this->assertCount( 5, $ids );
	}

	public function testMintedIdsAreWellFormedSubjectIds(): void {
		$ids = $this->newMinter( new ProductionIdGenerator() )->mint( 20 );

		foreach ( $ids as $id ) {
			$this->assertTrue( SubjectId::isValid( $id->text ), "Minted id '{$id->text}' is not a valid Subject ID" );
		}
	}

	public function testMintedIdsAreDistinct(): void {
		$ids = $this->newMinter( new ProductionIdGenerator() )->mint( 500 );

		$texts = array_map( static fn ( SubjectId $id ): string => $id->text, $ids );

		$this->assertCount( 500, array_unique( $texts ) );
	}

	public function testRegeneratesOnCollisionSoOutputStaysDistinct(): void {
		// The generator hands out 'aa…' twice before 'bb…'; minting two ids must discard the
		// duplicate and keep generating until two distinct ids exist.
		$minter = $this->newMinter(
			new SequenceIdGenerator( 'aaaaaaaaaaaaaa', 'aaaaaaaaaaaaaa', 'bbbbbbbbbbbbbb' )
		);

		$ids = $minter->mint( 2 );

		$texts = array_map( static fn ( SubjectId $id ): string => $id->text, $ids );

		$this->assertSame( [ 'saaaaaaaaaaaaaa', 'sbbbbbbbbbbbbbb' ], $texts );
	}

	private function newMinter( IdGenerator $idGenerator ): SubjectIdMinter {
		return new SubjectIdMinter( $idGenerator );
	}

}
