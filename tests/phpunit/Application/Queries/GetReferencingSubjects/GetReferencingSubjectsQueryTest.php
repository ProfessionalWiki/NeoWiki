<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetReferencingSubjects;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects\GetReferencingSubjectsPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects\GetReferencingSubjectsQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects\GetReferencingSubjectsResponse;
use ProfessionalWiki\NeoWiki\Application\ReferencingSubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects\GetReferencingSubjectsQuery
 */
class GetReferencingSubjectsQueryTest extends TestCase {

	private const string TARGET_ID = 's11111111111ttt';

	/**
	 * Every candidate the graph names costs a Subject read, and the graph is asked for several times
	 * the limit so that dropped candidates still fill it. Reading that whole window up front is what
	 * turns a Subject with many referrers into as many page reads as it has referrers.
	 */
	public function testReadsNoMoreSubjectsThanFillingTheLimitNeeds(): void {
		$candidates = $this->referrerIds( 8 );
		$subjectLookup = $this->lookupHolding( ...array_map( $this->referrer( ... ), $candidates ) );

		$this->newQuery( $subjectLookup, $candidates )->execute( subjectId: self::TARGET_ID, limit: 1 );

		$this->assertSame( [ array_slice( $candidates, 0, 2 ) ], $subjectLookup->requestedIdBatches );
	}

	public function testReadsTheRestWhenTheFirstReadsFillNothing(): void {
		$candidates = $this->referrerIds( 4 );
		// Holds none of the candidates, so no batch yields a referrer and every batch has to be read.
		$subjectLookup = $this->lookupHolding();

		$this->newQuery( $subjectLookup, $candidates )->execute( subjectId: self::TARGET_ID, limit: 1 );

		$this->assertSame(
			[ array_slice( $candidates, 0, 2 ), array_slice( $candidates, 2, 2 ) ],
			$subjectLookup->requestedIdBatches
		);
	}

	public function testDoesNotReadTheSubjectOfACandidateTheCallerMayNotSee(): void {
		$candidates = $this->referrerIds( 2 );
		$subjectLookup = $this->lookupHolding( ...array_map( $this->referrer( ... ), $candidates ) );

		$this->newQuery( $subjectLookup, $candidates, unreadableIds: [ $candidates[0] ] )
			->execute( subjectId: self::TARGET_ID, limit: 2 );

		$this->assertSame( [ [ $candidates[1] ] ], $subjectLookup->requestedIdBatches );
	}

	/**
	 * Holds the target too: a target the wiki does not publish is answered like an absent one, before
	 * any candidate is read.
	 */
	private function lookupHolding( Subject ...$referrers ): InMemorySubjectLookup {
		return new InMemorySubjectLookup( TestSubject::build( id: self::TARGET_ID ), ...$referrers );
	}

	/**
	 * @return string[]
	 */
	private function referrerIds( int $count ): array {
		return array_map( static fn ( int $i ): string => 's1111111111rrr' . $i, range( 1, $count ) );
	}

	private function referrer( string $id ): Subject {
		return TestSubject::build(
			id: $id,
			statements: new StatementList( [
				TestStatement::buildRelation( 'Made in', [
					TestRelation::build( targetId: self::TARGET_ID ),
				] ),
			] )
		);
	}

	/**
	 * @param string[] $candidateIds
	 * @param string[] $unreadableIds Candidates whose hosting page the caller may not read
	 */
	private function newQuery(
		InMemorySubjectLookup $subjectLookup,
		array $candidateIds,
		array $unreadableIds = []
	): GetReferencingSubjectsQuery {
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageIdentifiersLookup->addIdentifiers(
			new SubjectId( self::TARGET_ID ),
			new PageIdentifiers( new PageId( 1 ), 'Target', 0 )
		);

		$deniedPageIds = [];

		foreach ( $candidateIds as $index => $id ) {
			$pageId = $index + 2;
			$pageIdentifiersLookup->addIdentifiers(
				new SubjectId( $id ),
				new PageIdentifiers( new PageId( $pageId ), 'Referrer ' . $index, 0 )
			);

			if ( in_array( $id, $unreadableIds, true ) ) {
				$deniedPageIds[] = $pageId;
			}
		}

		return new GetReferencingSubjectsQuery(
			presenter: $this->newNullPresenter(),
			referencingSubjectLookup: $this->newLookupNaming( $candidateIds ),
			subjectLookup: $subjectLookup,
			hostingPageResolver: new SubjectHostingPageResolver(
				$pageIdentifiersLookup,
				new SelectivePageReadAuthorizer( $deniedPageIds )
			),
			responseItemFactory: new SubjectResponseItemFactory( new PageSubjectsLookup( new InMemorySubjectRepository() ), TestSources::newSubjectNamer() ),
			subjectIdParser: TestSubjectIds::newParser(),
		);
	}

	/**
	 * @param string[] $candidateIds
	 */
	private function newLookupNaming( array $candidateIds ): ReferencingSubjectLookup {
		return new class( $candidateIds ) implements ReferencingSubjectLookup {

			/**
			 * @param string[] $candidateIds
			 */
			public function __construct( private readonly array $candidateIds ) {
			}

			public function getIdsOfSubjectsReferencing( SubjectId $target, int $limit ): array {
				return array_map(
					static fn ( string $id ): SubjectId => new SubjectId( $id ),
					array_slice( $this->candidateIds, 0, $limit )
				);
			}

		};
	}

	private function newNullPresenter(): GetReferencingSubjectsPresenter {
		return new class() implements GetReferencingSubjectsPresenter {

			public function presentReferencingSubjects( GetReferencingSubjectsResponse $response ): void {
			}

		};
	}

}
