<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectSchemaNamesLookup;
use ProfessionalWiki\NeoWiki\Application\Validation\ViolationCheckingPublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Application\Validation\ViolationCheckQueue;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPublishedSubjectIndex;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\ViolationCheckingPublishedSubjectIndex
 */
class ViolationCheckingPublishedSubjectIndexTest extends TestCase {

	private const int PAGE = 42;

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string TURING = 's1cturing111111';

	/**
	 * @var string[][] The targets of each referring-pages check queued
	 */
	private array $queuedReferringPagesChecks = [];

	/**
	 * @var string[][] The targets of each check of the pages that did not find them, queued
	 */
	private array $queuedNotFoundTargetChecks = [];

	/**
	 * @var int[]
	 */
	private array $queuedPageChecks = [];

	public function testRecordingThePageQueuesACheckOfThePage(): void {
		$this->newIndexWithPagePublishing( [] )->setSubjectsOfPage( new PageId( self::PAGE ), 'Page', $this->subjects( [ self::ADA => 'Person' ] ), '20300107090000' );

		$this->assertSame( [ self::PAGE ], $this->queuedPageChecks );
	}

	public function testQueuesACheckOfThePagesRelatingToSubjectsThatWentAwayOrChangedSchema(): void {
		$this->newIndexWithPagePublishing( [ self::ADA => 'Person', self::GRACE => 'Person' ] )->setSubjectsOfPage(
			new PageId( self::PAGE ),
			'Page',
			$this->subjects( [ self::GRACE => 'Engineer', self::TURING => 'Person' ] ),
			'20300107090000'
		);

		$this->assertEqualsCanonicalizing( [ self::ADA, self::GRACE ], $this->queuedReferringPagesChecks[0] ?? [] );
	}

	public function testQueuesACheckOfOnlyThePagesThatDidNotFindSubjectsThatAppeared(): void {
		$this->newIndexWithPagePublishing( [ self::ADA => 'Person' ] )->setSubjectsOfPage(
			new PageId( self::PAGE ),
			'Page',
			$this->subjects( [ self::ADA => 'Person', self::TURING => 'Person' ] ),
			'20300107090000'
		);

		$this->assertSame( [ [ [ self::TURING ] ], [] ], [ $this->queuedNotFoundTargetChecks, $this->queuedReferringPagesChecks ] );
	}

	public function testQueuesNoCheckOfRelatingPagesWhenTheSubjectsKeepTheirSchemas(): void {
		$this->newIndexWithPagePublishing( [ self::ADA => 'Person' ] )->setSubjectsOfPage(
			new PageId( self::PAGE ),
			'Page',
			$this->subjects( [ self::ADA => 'Person' ] ),
			'20300107090000'
		);

		$this->assertSame( [ [], [] ], [ $this->queuedReferringPagesChecks, $this->queuedNotFoundTargetChecks ] );
	}

	public function testRemovingThePageQueuesACheckOfThePagesRelatingToItsSubjects(): void {
		$this->newIndexWithPagePublishing( [ self::ADA => 'Person' ] )->removePage( new PageId( self::PAGE ) );

		$this->assertSame( [ [ self::ADA ] ], $this->queuedReferringPagesChecks );
	}

	/**
	 * @param array<string, string> $schemaNamesBySubjectId What the page published before
	 */
	private function newIndexWithPagePublishing( array $schemaNamesBySubjectId ): ViolationCheckingPublishedSubjectIndex {
		return new ViolationCheckingPublishedSubjectIndex(
			index: new SpyPublishedSubjectIndex(),
			schemaNamesLookup: new class( $schemaNamesBySubjectId ) implements SubjectSchemaNamesLookup {

				public function __construct( private readonly array $schemaNamesBySubjectId ) {
				}

				public function getSchemaNamesOfSubjectsOn( PageId $pageId ): array {
					return $this->schemaNamesBySubjectId;
				}

			},
			checkQueue: new class(
				$this->queuedPageChecks,
				$this->queuedReferringPagesChecks,
				$this->queuedNotFoundTargetChecks
			) implements ViolationCheckQueue {

				public function __construct(
					private array &$pageChecks,
					private array &$referringPagesChecks,
					private array &$notFoundTargetChecks
				) {
				}

				public function queuePageCheck( PageId $pageId ): void {
					$this->pageChecks[] = $pageId->id;
				}

				public function queueSchemaCheck( string $schemaName ): void {
				}

				public function queueReferringPagesCheck( string ...$targetIds ): void {
					$this->referringPagesChecks[] = $targetIds;
				}

				public function queueNotFoundTargetCheck( string ...$targetIds ): void {
					$this->notFoundTargetChecks[] = $targetIds;
				}

			},
		);
	}

	/**
	 * @param array<string, string> $schemaNamesBySubjectId
	 */
	private function subjects( array $schemaNamesBySubjectId ): PageSubjects {
		$subjects = [];

		foreach ( $schemaNamesBySubjectId as $subjectId => $schemaName ) {
			$subjects[] = TestSubject::build( id: $subjectId, schemaName: new SchemaName( $schemaName ) );
		}

		return new PageSubjects( null, new SubjectMap( ...$subjects ) );
	}

}
