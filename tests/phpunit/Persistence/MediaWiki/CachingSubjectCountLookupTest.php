<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectCountLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSubjectCountLookup;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSubjectCountLookup
 */
class CachingSubjectCountLookupTest extends TestCase {

	private float $now = 1_700_000_000.0;

	public function testServesTheCountsItHoldsWithoutCountingAgain(): void {
		$lookup = $this->newCachingLookup( $this->newCounter( [ 'Computer' => 2 ], [ 'Computer' => 3 ] ) );
		$lookup->getSubjectCountsBySchema();

		$this->assertSame( [ 'Computer' => 2 ], $lookup->getSubjectCountsBySchema() );
	}

	public function testCountsAfreshAfterAMinute(): void {
		$lookup = $this->newCachingLookup( $this->newCounter( [ 'Computer' => 2 ], [ 'Computer' => 3 ] ) );
		$lookup->getSubjectCountsBySchema();

		$this->now += 61;

		$this->assertSame( [ 'Computer' => 3 ], $lookup->getSubjectCountsBySchema() );
	}

	/**
	 * @param array<string, int> ...$successiveCounts What each call returns, the first call first.
	 */
	private function newCounter( array ...$successiveCounts ): SubjectCountLookup {
		return new class( $successiveCounts ) implements SubjectCountLookup {

			/**
			 * @param array<int, array<string, int>> $successiveCounts
			 */
			public function __construct( private array $successiveCounts ) {
			}

			public function getSubjectCountsBySchema(): array {
				return array_shift( $this->successiveCounts ) ?? [];
			}

		};
	}

	private function newCachingLookup( SubjectCountLookup $counter ): CachingSubjectCountLookup {
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$cache->setMockTime( $this->now );

		return new CachingSubjectCountLookup( $counter, $cache );
	}

}
