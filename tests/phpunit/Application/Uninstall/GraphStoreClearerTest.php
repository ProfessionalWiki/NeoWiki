<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Uninstall;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Uninstall\GraphStoreClearer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryDeletedPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Uninstall\GraphStoreClearer
 */
class GraphStoreClearerTest extends TestCase {

	public function testRemovesEveryPageAndEveryDeletedPageFromTheStore(): void {
		$store = new SpyGraphDatabasePlugin();

		iterator_to_array( $this->newClearer()->clear( $store ) );

		$this->assertEqualsCanonicalizing(
			[ new PageId( 1 ), new PageId( 2 ), new PageId( 3 ), new PageId( 7 ), new PageId( 8 ) ],
			$store->deletedPageIds
		);
	}

	public function testReportsHowManyPagesAreRemovedAfterEachBatch(): void {
		$this->assertSame(
			[ 2, 3, 5 ],
			iterator_to_array( $this->newClearer()->clear( new SpyGraphDatabasePlugin() ), false )
		);
	}

	public function testAStoreThatCannotBeReachedFailsTheClearing(): void {
		$clearing = $this->newClearer()->clear( new ThrowingGraphDatabasePlugin() );

		$this->expectException( RuntimeException::class );
		iterator_to_array( $clearing );
	}

	/**
	 * Pages 1 to 3 exist and pages 7 and 8 were deleted, in batches of two.
	 */
	private function newClearer(): GraphStoreClearer {
		return new GraphStoreClearer(
			pageIdsLookup: new InMemoryPageIdsLookup( 1, 2, 3 ),
			deletedPageIdsLookup: new InMemoryDeletedPageIdsLookup( 7, 8 ),
			batchSize: 2
		);
	}

}
