<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaSubjectsLookup;

/**
 * Needs the database for the published Subject index it reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaSubjectsLookup
 * @group Database
 */
class DatabaseSchemaSubjectsLookupTest extends MediaWikiIntegrationTestCase {

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string TURING = 's1cturing111111';
	private const string ACME = 's1dacme11111111';

	public function testListsTheSubjectsOfTheSchemaInIdOrderWithTheirPages(): void {
		$this->insertSubject( 12, self::GRACE, 'Person' );
		$this->insertSubject( 13, self::ACME, 'Company' );
		$this->insertSubject( 11, self::ADA, 'Person' );

		$this->assertSame(
			[ [ self::ADA, 11 ], [ self::GRACE, 12 ] ],
			$this->subjectsOfPersonAfter( '', 0, 10 )
		);
	}

	public function testListsNoMoreThanTheLimit(): void {
		$this->insertSubject( 11, self::ADA, 'Person' );
		$this->insertSubject( 12, self::GRACE, 'Person' );

		$this->assertSame( [ [ self::ADA, 11 ] ], $this->subjectsOfPersonAfter( '', 0, 1 ) );
	}

	public function testContinuesAfterTheGivenSubjectOnTheGivenPage(): void {
		$this->insertSubject( 11, self::ADA, 'Person' );
		$this->insertSubject( 12, self::GRACE, 'Person' );
		$this->insertSubject( 14, self::GRACE, 'Person' );
		$this->insertSubject( 13, self::TURING, 'Person' );

		$this->assertSame(
			[ [ self::GRACE, 14 ], [ self::TURING, 13 ] ],
			$this->subjectsOfPersonAfter( self::GRACE, 12, 10 )
		);
	}

	/**
	 * @return list<array{string, int}>
	 */
	private function subjectsOfPersonAfter( string $subjectId, int $pageId, int $limit ): array {
		return array_map(
			static fn ( array $subject ): array => [ $subject['subjectId'], $subject['pageId'] ],
			( new DatabaseSchemaSubjectsLookup( $this->getServiceContainer()->getConnectionProvider() ) )
				->getSubjectsAfter( 'Person', $subjectId, $pageId, $limit )
		);
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
