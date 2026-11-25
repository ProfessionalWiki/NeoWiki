<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\ReferringPagesLookup;
use ProfessionalWiki\NeoWiki\Application\Validation\ReferringPagesViolationChecker;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageViolationChecker;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyViolationIndex;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\ReferringPagesViolationChecker
 */
class ReferringPagesViolationCheckerTest extends TestCase {

	private const string ACME = 's1cacme11111111';

	private SpyViolationIndex $violationIndex;

	protected function setUp(): void {
		$this->violationIndex = new SpyViolationIndex();
	}

	public function testChecksEachPageReferringToTheTargets(): void {
		$this->newChecker( [ 11, 12 ] )->checkBatch( [ self::ACME ], 0, 10 );

		$this->assertSame( [ 11, 12 ], array_keys( $this->violationIndex->violationsByPageId ) );
	}

	public function testGivesTheLastPageOfAFullBatchToContinueAfter(): void {
		$this->assertSame( 12, $this->newChecker( [ 11, 12, 13 ] )->checkBatch( [ self::ACME ], 0, 2 ) );
	}

	public function testGivesNothingToContinueAfterOnceThePagesRunOut(): void {
		$this->assertNull( $this->newChecker( [ 11, 12 ] )->checkBatch( [ self::ACME ], 11, 2 ) );
	}

	/**
	 * @param int[] $referringPageIds
	 */
	private function newChecker( array $referringPageIds ): ReferringPagesViolationChecker {
		return new ReferringPagesViolationChecker(
			referringPagesLookup: new class( $referringPageIds ) implements ReferringPagesLookup {

				public function __construct( private readonly array $referringPageIds ) {
				}

				public function getPageIdsReferringTo( array $targetIds, int $afterPageId, int $limit ): array {
					return array_slice(
						array_values( array_filter( $this->referringPageIds, static fn ( int $id ): bool => $id > $afterPageId ) ),
						0,
						$limit
					);
				}

			},
			pageChecker: TestPageViolationChecker::build( $this->violationIndex ),
		);
	}

}
