<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationTargetPagesLookup;

/**
 * Needs the database for the Violation index it reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationTargetPagesLookup
 * @group Database
 */
class DatabaseViolationTargetPagesLookupTest extends MediaWikiIntegrationTestCase {

	private const string ADA = 's1aada111111111';
	private const string ACME = 's1cacme11111111';
	private const string INITECH = 's1dinitech11111';
	private const string GLOBEX = 's1egobex1111111';

	public function testListsEachPageWithViolationsAboutTheTargetsOnceInPageIdOrder(): void {
		$this->recordNotFound( 13, self::INITECH );
		$this->recordNotFound( 11, self::ACME, self::INITECH );
		$this->recordNotFound( 12, self::GLOBEX );

		$this->assertSame( [ 11, 13 ], $this->pagesAbout( [ self::ACME, self::INITECH ], 0, 10 ) );
	}

	public function testListsTheNextPagesUpToTheLimit(): void {
		$this->recordNotFound( 11, self::ACME );
		$this->recordNotFound( 12, self::ACME );
		$this->recordNotFound( 13, self::ACME );

		$this->assertSame( [ 12 ], $this->pagesAbout( [ self::ACME ], 11, 1 ) );
	}

	/**
	 * @param string[] $targetIds
	 * @return int[]
	 */
	private function pagesAbout( array $targetIds, int $afterPageId, int $limit ): array {
		return ( new DatabaseViolationTargetPagesLookup( $this->getServiceContainer()->getConnectionProvider() ) )
			->getPageIdsReferringTo( $targetIds, $afterPageId, $limit );
	}

	private function recordNotFound( int $pageId, string ...$targetIds ): void {
		( new DatabaseViolationIndex( $this->getDb() ) )->setViolationsOfPage(
			new PageId( $pageId ),
			...array_map(
				static fn ( string $targetId ): SubjectViolation => new SubjectViolation(
					new SubjectId( self::ADA ),
					'Person',
					new Violation( new PropertyName( 'Employer' ), 'relation-target-not-found', [ $targetId ], 0 ),
					new SubjectId( $targetId )
				),
				$targetIds
			)
		);
	}

}
