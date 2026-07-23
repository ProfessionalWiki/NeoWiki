<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseLayoutNameLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseLayoutNameLookup
 * @group Database
 */
class DatabaseLayoutNameLookupTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	/**
	 * @var array<string, int>
	 */
	private array $pageIds = [];

	public function setUp(): void {
		$this->tablesUsed[] = 'page';
		$this->truncateTables( $this->tablesUsed, $this->db );

		foreach ( [ 'LayoutNameLookupTest1', 'LayoutNameLookupTest2', 'LayoutNameLookupTest3' ] as $name ) {
			$this->pageIds[$name] = $this->createLayout( $name )->getPageId();
		}
	}

	private function getLookup( ?Authority $authority = null ): DatabaseLayoutNameLookup {
		return new DatabaseLayoutNameLookup(
			db: $this->getDb(),
			readAuthorizer: new AuthorityBasedPageReadAuthorizer(
				$authority ?? $this->mockRegisteredUltimateAuthority(),
				$this->getServiceContainer()->getTitleFactory(),
				new NullLogger()
			),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
		);
	}

	public function testGetReadableLayoutNamesYieldsEveryLayoutKeyedByPageId(): void {
		$this->assertSame(
			[
				$this->pageIds['LayoutNameLookupTest1'] => 'LayoutNameLookupTest1',
				$this->pageIds['LayoutNameLookupTest2'] => 'LayoutNameLookupTest2',
				$this->pageIds['LayoutNameLookupTest3'] => 'LayoutNameLookupTest3',
			],
			array_map(
				static fn ( TitleValue $title ): string => $title->getText(),
				iterator_to_array( $this->getLookup()->getReadableLayoutNames() )
			)
		);
	}

	public function testGetReadableLayoutNamesStartsAfterTheGivenPageId(): void {
		$this->assertSame(
			[
				$this->pageIds['LayoutNameLookupTest3'] => 'LayoutNameLookupTest3',
			],
			array_map(
				static fn ( TitleValue $title ): string => $title->getText(),
				iterator_to_array(
					$this->getLookup()->getReadableLayoutNames( $this->pageIds['LayoutNameLookupTest2'] )
				)
			)
		);
	}

	public function testGetReadableLayoutNamesOmitsUnreadableLayouts(): void {
		// The denied Layout sits mid-list. It must not be yielded at all: the summaries endpoint
		// fills its page from this iterable and builds its cursor from the yielded keys, so a
		// skipped Layout neither takes page space nor is inferable from the pagination (#1062).
		$denyHidden = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'LayoutNameLookupTest2';

		$this->assertSame(
			[
				$this->pageIds['LayoutNameLookupTest1'] => 'LayoutNameLookupTest1',
				$this->pageIds['LayoutNameLookupTest3'] => 'LayoutNameLookupTest3',
			],
			array_map(
				static fn ( TitleValue $title ): string => $title->getText(),
				iterator_to_array(
					$this->getLookup( $this->mockRegisteredAuthority( $denyHidden ) )->getReadableLayoutNames()
				)
			)
		);
	}

	public function testGetReadableLayoutNamesDrainsPastTheBatchSize(): void {
		// The generator pages the namespace in 100-row keyset batches. With more rows than one batch,
		// it must keep querying past the first batch: every Layout is yielded, the lowest page ID
		// first and the highest last. A single truncated batch would drop the tail.
		$bulk = $this->createBarePages( NeoWikiExtension::NS_LAYOUT, 'BulkLayout', 120 );

		$drained = iterator_to_array( $this->getLookup()->getReadableLayoutNames() );

		$this->assertCount( count( $this->pageIds ) + count( $bulk ), $drained );
		$this->assertSame( $this->pageIds['LayoutNameLookupTest1'], array_key_first( $drained ) );
		$this->assertSame( max( $bulk ), array_key_last( $drained ) );
	}

}
