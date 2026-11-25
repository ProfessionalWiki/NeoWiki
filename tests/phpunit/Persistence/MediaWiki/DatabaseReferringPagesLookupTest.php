<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseReferringPagesLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectSchemaNamesLookup;

/**
 * Needs the database for the published Subject index it reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseReferringPagesLookup
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectSchemaNamesLookup
 * @group Database
 */
class DatabaseReferringPagesLookupTest extends MediaWikiIntegrationTestCase {

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string ACME = 's1cacme11111111';
	private const string INITECH = 's1dinitech11111';

	public function testListsEachPageReferringToTheTargetsOnceInPageIdOrder(): void {
		$this->insertRelation( 13, self::GRACE, 'Employer', self::INITECH );
		$this->insertRelation( 11, self::ADA, 'Employer', self::ACME );
		$this->insertRelation( 11, self::ADA, 'Client', self::INITECH );
		$this->insertRelation( 12, self::GRACE, 'Employer', self::GRACE );

		$this->assertSame( [ 11, 13 ], $this->pagesReferringTo( [ self::ACME, self::INITECH ], 0, 10 ) );
	}

	public function testListsTheNextPagesUpToTheLimit(): void {
		$this->insertRelation( 11, self::ADA, 'Employer', self::ACME );
		$this->insertRelation( 12, self::ADA, 'Employer', self::ACME );
		$this->insertRelation( 13, self::ADA, 'Employer', self::ACME );

		$this->assertSame( [ 12 ], $this->pagesReferringTo( [ self::ACME ], 11, 1 ) );
	}

	public function testListsTheSchemaOfEachSubjectAPagePublishes(): void {
		$this->insertSubject( 11, self::ADA, 'Person' );
		$this->insertSubject( 11, self::ACME, 'Company' );
		$this->insertSubject( 12, self::GRACE, 'Person' );

		$this->assertSame(
			[ self::ADA => 'Person', self::ACME => 'Company' ],
			( new DatabaseSubjectSchemaNamesLookup( $this->getDb() ) )->getSchemaNamesOfSubjectsOn( new PageId( 11 ) )
		);
	}

	/**
	 * @param string[] $targetIds
	 * @return int[]
	 */
	private function pagesReferringTo( array $targetIds, int $afterPageId, int $limit ): array {
		return ( new DatabaseReferringPagesLookup( $this->getServiceContainer()->getConnectionProvider() ) )
			->getPageIdsReferringTo( $targetIds, $afterPageId, $limit );
	}

	private function insertRelation( int $pageId, string $subjectId, string $property, string $targetId ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( DatabasePublishedSubjectIndex::RELATION_TABLE )
			->row( [
				'nwr_page_id' => $pageId,
				'nwr_subject_id' => $subjectId,
				'nwr_property' => $property,
				'nwr_target_id' => $targetId,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function insertSubject( int $pageId, string $subjectId, string $schemaName ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( DatabasePublishedSubjectIndex::SUBJECT_TABLE )
			->row( [
				'nws_page_id' => $pageId,
				'nws_subject_id' => $subjectId,
				'nws_schema' => $schemaName,
				'nws_display_name' => null,
				'nws_display_name_key' => null,
				'nws_page_key' => 'page ' . $pageId,
				'nws_is_main' => 0,
				'nws_dependent' => 0,
				'nws_edited' => $this->getDb()->timestamp( '20300107090000' ),
			] )
			->caller( __METHOD__ )
			->execute();
	}

}
