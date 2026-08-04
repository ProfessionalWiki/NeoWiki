<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageIdsLookup
 * @group Database
 */
class DatabasePageIdsLookupTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Registers the subject slot role. Without it, a lookup narrowed back to slot-bearing pages would
		// yield nothing at all rather than only the subject pages, which is a weaker thing to catch.
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testYieldsPagesWithAndWithoutSubjectsInPageIdOrder(): void {
		$subjectPage = $this->createPageWithSubjects( 'Page with subjects', TestSubject::build() )->getPageId();
		$plainPage = $this->insertPage( 'Plain page', 'No subjects here.' )['id'];
		$namespacedPlainPage = $this->insertPage( 'Help:Plain page', 'No subjects here either.' )['id'];
		// A page outside the content namespaces counts too: an interface page's metadata is queryable like
		// any other page's.
		$interfacePage = $this->insertPage( 'MediaWiki:Plain-interface-page', 'No subjects here either.' )['id'];

		$this->assertSame(
			[ $subjectPage, $plainPage, $namespacedPlainPage, $interfacePage ],
			$this->getPageIdsAfter( $subjectPage - 1 )
		);
	}

	public function testDrainsEveryBatchInPageIdOrder(): void {
		// The generator walks the wiki in fixed-size keyset batches. With more pages than one batch, it
		// must keep querying past the first batch and yield every page exactly once, in strictly ascending
		// page-id order — a single truncated batch would drop the tail. The real pages around the bulk
		// rows put a page holding Subjects and a page without on both sides of the batch boundary.
		$firstSubjectPage = $this->createPageWithSubjects( 'Bulk first page', TestSubject::build() )->getPageId();
		$firstPlainPage = $this->insertPage( 'Bulk first plain page', 'No subjects here.' )['id'];
		$bulk = $this->createBarePages( NS_HELP, 'BulkPage', DatabasePageIdsLookup::BATCH_SIZE );
		$lastSubjectPage = $this->createPageWithSubjects( 'Bulk last page', TestSubject::build() )->getPageId();
		$lastPlainPage = $this->insertPage( 'Bulk last plain page', 'No subjects here.' )['id'];

		$this->assertSame(
			[ $firstSubjectPage, $firstPlainPage, ...array_values( $bulk ), $lastSubjectPage, $lastPlainPage ],
			$this->getPageIdsAfter( $firstSubjectPage - 1 )
		);
	}

	/**
	 * A whole-wiki rebuild is long enough that being able to pick it back up matters, so the walk can
	 * start past a page id already handled.
	 */
	public function testStartsPastTheGivenPageId(): void {
		$first = $this->insertPage( 'Resume first page', 'One.' )['id'];
		$second = $this->insertPage( 'Resume second page', 'Two.' )['id'];
		$third = $this->insertPage( 'Resume third page', 'Three.' )['id'];

		$pageIds = iterator_to_array(
			( new DatabasePageIdsLookup( $this->getDb() ) )->getPageIds( $first ),
			false
		);

		$this->assertNotContains( $first, $pageIds );
		$this->assertSame( [ $second, $third ], array_values( array_filter(
			$pageIds,
			static fn ( int $pageId ): bool => $pageId > $first
		) ) );
	}

	/**
	 * The lookup yields every page on the wiki, so a test asserting a full list has to bound it: this
	 * drops the pages that exist before the ones the test creates, such as its Schema page.
	 *
	 * @return int[]
	 */
	private function getPageIdsAfter( int $firstPageId ): array {
		$pageIds = iterator_to_array( ( new DatabasePageIdsLookup( $this->getDb() ) )->getPageIds(), false );

		return array_values( array_filter( $pageIds, static fn ( int $pageId ): bool => $pageId > $firstPageId ) );
	}

}
