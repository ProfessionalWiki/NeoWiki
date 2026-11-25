<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SchemaSubjectsLookup;
use ProfessionalWiki\NeoWiki\Application\Validation\SchemaViolationChecker;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageViolationChecker;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyViolationIndex;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\SchemaViolationChecker
 */
class SchemaViolationCheckerTest extends TestCase {

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string TURING = 's1cturing111111';

	private SpyViolationIndex $violationIndex;

	protected function setUp(): void {
		$this->violationIndex = new SpyViolationIndex();
	}

	public function testChecksEachPageHoldingSubjectsOfTheSchemaOnce(): void {
		$this->newChecker( [ self::ADA => 11, self::GRACE => 11, self::TURING => 12 ] )
			->checkBatch( 'Person', '', 0, 10 );

		$this->assertSame( [ 11, 12 ], array_keys( $this->violationIndex->violationsByPageId ) );
	}

	public function testGivesTheLastSubjectOfAFullBatchToContinueAfter(): void {
		$next = $this->newChecker( [ self::ADA => 11, self::GRACE => 12, self::TURING => 13 ] )
			->checkBatch( 'Person', '', 0, 2 );

		$this->assertSame( [ 'subjectId' => self::GRACE, 'pageId' => 12 ], $next );
	}

	public function testGivesNothingToContinueAfterOnceTheSubjectsRunOut(): void {
		$next = $this->newChecker( [ self::ADA => 11, self::GRACE => 12 ] )
			->checkBatch( 'Person', self::ADA, 11, 2 );

		$this->assertNull( $next );
	}

	/**
	 * @param array<string, int> $pageIdsBySubjectId The Subjects of the Schema, in id order
	 */
	private function newChecker( array $pageIdsBySubjectId ): SchemaViolationChecker {
		return new SchemaViolationChecker(
			schemaSubjectsLookup: new class( $pageIdsBySubjectId ) implements SchemaSubjectsLookup {

				public function __construct( private readonly array $pageIdsBySubjectId ) {
				}

				public function getSubjectsAfter( string $schemaName, string $afterSubjectId, int $afterPageId, int $limit ): array {
					$subjects = [];

					foreach ( $this->pageIdsBySubjectId as $subjectId => $pageId ) {
						if ( $subjectId > $afterSubjectId ) {
							$subjects[] = [ 'subjectId' => $subjectId, 'pageId' => $pageId ];
						}
					}

					return array_slice( $subjects, 0, $limit );
				}

			},
			pageChecker: TestPageViolationChecker::build( $this->violationIndex ),
		);
	}

}
