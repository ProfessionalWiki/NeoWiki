<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndex;

/**
 * Needs the database for the table it writes.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndex
 * @group Database
 */
class DatabaseViolationIndexTest extends MediaWikiIntegrationTestCase {

	private const int PAGE = 42;
	private const int OTHER_PAGE = 43;

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string ACME = 's1cacme11111111';

	public function testRecordsEachViolationOfThePage(): void {
		$this->newIndex()->setViolationsOfPage(
			new PageId( self::PAGE ),
			$this->missingName( self::ADA ),
			new SubjectViolation(
				new SubjectId( self::GRACE ),
				'Person',
				new Violation(
					propertyName: new PropertyName( 'Employer' ),
					code: 'relation-target-not-found',
					args: [ self::ACME ],
					valuePartIndex: 1,
					severity: Severity::Warning
				),
				new SubjectId( self::ACME )
			)
		);

		$this->assertSame(
			[
				[ self::ADA, 'Person', 'Name', 'required', 'error', null, null, [] ],
				[ self::GRACE, 'Person', 'Employer', 'relation-target-not-found', 'warning', 1, self::ACME, [ self::ACME ] ],
			],
			$this->rowsOf( self::PAGE )
		);
	}

	public function testRecordsAViolationOfTheWholeSubjectWithoutAProperty(): void {
		$this->newIndex()->setViolationsOfPage(
			new PageId( self::PAGE ),
			new SubjectViolation(
				new SubjectId( self::ADA ),
				'Person',
				new Violation( propertyName: null, code: 'schema-not-found', args: [ 'Person' ] ),
				null
			)
		);

		$this->assertSame(
			[ [ self::ADA, 'Person', null, 'schema-not-found', 'warning', null, null, [ 'Person' ] ] ],
			$this->rowsOf( self::PAGE )
		);
	}

	public function testReplacingTheViolationsOfAPageDropsTheOnesItHadBefore(): void {
		$index = $this->newIndex();
		$index->setViolationsOfPage( new PageId( self::PAGE ), $this->missingName( self::ADA ) );

		$index->setViolationsOfPage( new PageId( self::PAGE ), $this->missingName( self::GRACE ) );

		$this->assertSame( [ self::GRACE ], $this->subjectIdsOf( self::PAGE ) );
	}

	public function testLeavesTheViolationsOfOtherPagesAlone(): void {
		$index = $this->newIndex();
		$index->setViolationsOfPage( new PageId( self::OTHER_PAGE ), $this->missingName( self::ACME ) );

		$index->setViolationsOfPage( new PageId( self::PAGE ), $this->missingName( self::ADA ) );
		$index->removePage( new PageId( self::PAGE ) );

		$this->assertSame( [ self::ACME ], $this->subjectIdsOf( self::OTHER_PAGE ) );
	}

	public function testRemovingAPageDropsItsViolations(): void {
		$index = $this->newIndex();
		$index->setViolationsOfPage( new PageId( self::PAGE ), $this->missingName( self::ADA ) );

		$index->removePage( new PageId( self::PAGE ) );

		$this->assertSame( [], $this->subjectIdsOf( self::PAGE ) );
	}

	public function testCutsANameLongerThanItsColumnOnACharacterBoundary(): void {
		$this->newIndex()->setViolationsOfPage(
			new PageId( self::PAGE ),
			new SubjectViolation(
				new SubjectId( self::ADA ),
				'Person',
				new Violation( propertyName: new PropertyName( str_repeat( 'ä', 200 ) ), code: 'required' ),
				null
			)
		);

		$this->assertSame( str_repeat( 'ä', 127 ), $this->rowsOf( self::PAGE )[0][2] );
	}

	private function newIndex(): DatabaseViolationIndex {
		return new DatabaseViolationIndex( $this->getDb() );
	}

	private function missingName( string $subjectId ): SubjectViolation {
		return new SubjectViolation(
			new SubjectId( $subjectId ),
			'Person',
			new Violation( propertyName: new PropertyName( 'Name' ), code: 'required', severity: Severity::Error ),
			null
		);
	}

	/**
	 * @return list<array{string, string, ?string, string, string, ?int, ?string, array}> In the order recorded
	 */
	private function rowsOf( int $pageId ): array {
		$rows = [];

		foreach ( $this->selectRows( $pageId ) as $row ) {
			$rows[] = [
				$row->nwv_subject_id,
				$row->nwv_schema,
				$row->nwv_property,
				$row->nwv_code,
				$row->nwv_severity,
				$row->nwv_value_part === null ? null : (int)$row->nwv_value_part,
				$row->nwv_target_id,
				json_decode( $row->nwv_args, true ),
			];
		}

		return $rows;
	}

	/**
	 * @return string[]
	 */
	private function subjectIdsOf( int $pageId ): array {
		return array_column( $this->rowsOf( $pageId ), 0 );
	}

	private function selectRows( int $pageId ): iterable {
		return $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'neowiki_violation' )
			->where( [ 'nwv_page_id' => $pageId ] )
			->orderBy( 'nwv_id' )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

}
