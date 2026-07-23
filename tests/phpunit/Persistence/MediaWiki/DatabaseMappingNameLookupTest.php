<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseMappingNameLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseMappingNameLookup
 * @group Database
 */
class DatabaseMappingNameLookupTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	/**
	 * @var array<string, int>
	 */
	private array $pageIds = [];

	public function setUp(): void {
		$this->tablesUsed[] = 'page';
		$this->truncateTables( $this->tablesUsed, $this->db );

		foreach ( [ 'MappingLookupTest1', 'MappingLookupTest2', 'MappingLookupTest3' ] as $name ) {
			$this->pageIds[$name] = $this->createMapping( $name, '{"version":1,"schemas":{}}' )->getPageId();
		}
	}

	private function getLookup( ?Authority $authority = null ): DatabaseMappingNameLookup {
		return new DatabaseMappingNameLookup(
			db: $this->getDb(),
			readAuthorizer: new AuthorityBasedPageReadAuthorizer(
				$authority ?? $this->mockRegisteredUltimateAuthority(),
				$this->getServiceContainer()->getTitleFactory(),
				new NullLogger()
			),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
		);
	}

	public function testGetMappingNamesReturnsEveryMappingUnfiltered(): void {
		// getMappingNames stays unfiltered for the RDF-projection / DumpRdf path, which runs in a
		// system context and must see every Mapping regardless of a request user's read rights.
		$denyHidden = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'MappingLookupTest2';

		$names = array_map(
			static fn ( MappingName $name ): string => $name->getText(),
			$this->getLookup( $this->mockRegisteredAuthority( $denyHidden ) )->getMappingNames()
		);

		$this->assertSame(
			[ 'MappingLookupTest1', 'MappingLookupTest2', 'MappingLookupTest3' ],
			$names
		);
	}

	public function testGetReadableMappingNamesYieldsEveryMappingKeyedByPageId(): void {
		$this->assertSame(
			[
				$this->pageIds['MappingLookupTest1'] => 'MappingLookupTest1',
				$this->pageIds['MappingLookupTest2'] => 'MappingLookupTest2',
				$this->pageIds['MappingLookupTest3'] => 'MappingLookupTest3',
			],
			array_map(
				static fn ( MappingName $name ): string => $name->getText(),
				iterator_to_array( $this->getLookup()->getReadableMappingNames() )
			)
		);
	}

	public function testGetReadableMappingNamesStartsAfterTheGivenPageId(): void {
		$this->assertSame(
			[
				$this->pageIds['MappingLookupTest3'] => 'MappingLookupTest3',
			],
			array_map(
				static fn ( MappingName $name ): string => $name->getText(),
				iterator_to_array(
					$this->getLookup()->getReadableMappingNames( $this->pageIds['MappingLookupTest2'] )
				)
			)
		);
	}

	public function testGetReadableMappingNamesOmitsUnreadableMappings(): void {
		// The denied Mapping sits mid-list. It must not be yielded at all: the summaries endpoint
		// fills its page from this iterable and builds its cursor from the yielded keys, so a
		// skipped Mapping neither takes page space nor is inferable from the pagination (#1062).
		$denyHidden = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'MappingLookupTest2';

		$this->assertSame(
			[
				$this->pageIds['MappingLookupTest1'] => 'MappingLookupTest1',
				$this->pageIds['MappingLookupTest3'] => 'MappingLookupTest3',
			],
			array_map(
				static fn ( MappingName $name ): string => $name->getText(),
				iterator_to_array(
					$this->getLookup( $this->mockRegisteredAuthority( $denyHidden ) )->getReadableMappingNames()
				)
			)
		);
	}

	public function testGetReadableMappingNamesDrainsPastTheBatchSize(): void {
		// The generator pages the namespace in 100-row keyset batches. With more rows than one batch,
		// it must keep querying past the first batch: every Mapping is yielded, the lowest page ID
		// first and the highest last. A single truncated batch would drop the tail.
		$bulk = $this->createBarePages( NeoWikiExtension::NS_MAPPING, 'BulkMapping', 120 );

		$drained = iterator_to_array( $this->getLookup()->getReadableMappingNames() );

		$this->assertCount( count( $this->pageIds ) + count( $bulk ), $drained );
		$this->assertSame( $this->pageIds['MappingLookupTest1'], array_key_first( $drained ) );
		$this->assertSame( max( $bulk ), array_key_last( $drained ) );
	}

}
