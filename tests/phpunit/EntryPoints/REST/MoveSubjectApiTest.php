<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\MoveSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\MoveSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestMoveSubjectPresenter
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject\MoveSubjectAction
 * @group Database
 */
class MoveSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private const string SCHEMA = 'MoveSubjectApiTestSchema';

	private const string MOVED_ID = 'sTestMove111ch1';
	private const string SOURCE_MAIN_ID = 'sTestMove111maa';
	private const string TARGET_MAIN_ID = 'sTestMove111taa';

	// A page id far above anything a fresh test database mints, so it resolves to no page.
	private const int NONEXISTENT_PAGE_ID = 999999;

	private int $sourcePageId;
	private int $targetPageId;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema( self::SCHEMA, '{"title":"' . self::SCHEMA . '","propertyDefinitions":{}}' );

		$this->sourcePageId = $this->createPageWithSubjects(
			'MoveSubjectApiTest_Source',
			mainSubject: $this->newSubject( self::SOURCE_MAIN_ID, 'source main' ),
			childSubjects: new SubjectMap( $this->newSubject( self::MOVED_ID, 'moved' ) )
		)->getPage()->getId();

		$this->targetPageId = $this->createPageWithSubjects(
			'MoveSubjectApiTest_Target',
			mainSubject: $this->newSubject( self::TARGET_MAIN_ID, 'target main' )
		)->getPage()->getId();
	}

	public function testMovesTheSubjectBetweenPages(): void {
		$response = $this->executeHandler( $this->newApi(), $this->newRequest() );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'changed', json_decode( $response->getBody()->getContents(), true )['status'] );

		$this->assertFalse( $this->subjectsOf( $this->sourcePageId )->getAllSubjects()->hasSubject( $this->movedId() ) );
		$this->assertTrue( $this->subjectsOf( $this->targetPageId )->getChildSubjects()->hasSubject( $this->movedId() ) );
	}

	public function testTheSubjectToPageIndexFollowsTheMove(): void {
		// The index is what makes relations targeting the moved Subject keep resolving, and what
		// every page-keyed write route resolves through. It is maintained by the revision hook, so
		// only the real stack shows whether a move updates it.
		$this->executeHandler( $this->newApi(), $this->newRequest() );

		$hostingPage = ( new DatabasePageIdentifiersLookup(
			$this->getDb(),
			$this->getServiceContainer()->getTitleFormatter()
		) )->getPageIdOfSubject( $this->movedId() );

		$this->assertSame( $this->targetPageId, $hostingPage?->getId()->id );
	}

	public function testTheGraphProjectionFollowsTheMove(): void {
		// The projection is page-scoped: each page write rewrites the nodes for the Subjects that page
		// holds, and tears down the ones it no longer does. So the page written LAST decides what the
		// moved Subject's node looks like, which is why the source page is written before the target.
		$this->executeHandler( $this->newApi(), $this->newRequest() );

		// Exactly one hosting page: a Subject left on both pages would also satisfy a first-row check.
		$this->assertSame( [ 'MoveSubjectApiTest Target' ], $this->hostingPageNodeNames( self::MOVED_ID ) );
	}

	public function testTheMovedSubjectKeepsItsSchemaLabelInTheGraph(): void {
		// A Subject torn down by the source-page write is left as a bare :Subject stub, which no
		// schema-scoped Cypher query would find again.
		$this->executeHandler( $this->newApi(), $this->newRequest() );

		$this->assertContains( self::SCHEMA, $this->subjectNodeLabels( self::MOVED_ID ) );
	}

	public function testMovedSubjectRemainsFetchableByItsUnchangedId(): void {
		$this->executeHandler( $this->newApi(), $this->newRequest() );

		$subject = NeoWikiExtension::getInstance()->getSubjectRepository()->getSubject( $this->movedId() );

		$this->assertNotNull( $subject );
		$this->assertSame( 'moved', $subject->label?->text );
	}

	public function testPromotionMakesTheSubjectMainAndDemotesTheTargetsPreviousMain(): void {
		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( body: [ 'targetPageId' => $this->targetPageId, 'makeMainSubject' => true ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$target = $this->subjectsOf( $this->targetPageId );
		$this->assertSame( self::MOVED_ID, $target->getMainSubject()?->id->text );
		$this->assertTrue( $target->getChildSubjects()->hasSubject( new SubjectId( self::TARGET_MAIN_ID ) ) );
	}

	public function testBothPagesCarryTheSuppliedEditSummary(): void {
		$this->executeHandler(
			$this->newApi(),
			$this->newRequest( body: [ 'targetPageId' => $this->targetPageId, 'comment' => 'Filed properly' ] )
		);

		$this->assertSame( 'Filed properly', $this->latestCommentOf( $this->sourcePageId ) );
		$this->assertSame( 'Filed properly', $this->latestCommentOf( $this->targetPageId ) );
	}

	public function testMovingToThePageTheSubjectIsAlreadyOnIsUnchanged(): void {
		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( body: [ 'targetPageId' => $this->sourcePageId ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'unchanged', json_decode( $response->getBody()->getContents(), true )['status'] );
	}

	public function testReturnsBadRequestWhenTargetPageIdMissing(): void {
		// targetPageId is required, so the framework's body validator refuses the request before
		// run() is entered.
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler( $this->newApi(), $this->newRequest( body: [] ) );
	}

	public function testReturnsNotFoundForUnknownSubjectId(): void {
		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( subjectId: 'sTestMove111zzz' )
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testUnreadableTargetPageIsIndistinguishableFromNonexistentTargetPage(): void {
		$unreadable = $this->executeHandler(
			$this->newApi(),
			$this->newRequest(),
			authority: $this->authorityThatCannotReadPageId( $this->targetPageId )
		);

		$nonexistent = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( body: [ 'targetPageId' => self::NONEXISTENT_PAGE_ID ] )
		);

		$this->assertSame( 404, $unreadable->getStatusCode() );
		$this->assertSame( 404, $nonexistent->getStatusCode() );
		// Byte-identical: a caller sweeping page ids cannot tell a hidden page from an absent one.
		$this->assertSame(
			$nonexistent->getBody()->getContents(),
			$unreadable->getBody()->getContents()
		);
	}

	public function testUnreadableTargetPageLeavesTheSourcePageUntouched(): void {
		$this->executeHandler(
			$this->newApi(),
			$this->newRequest(),
			authority: $this->authorityThatCannotReadPageId( $this->targetPageId )
		);

		$this->assertTrue( $this->subjectsOf( $this->sourcePageId )->getAllSubjects()->hasSubject( $this->movedId() ) );
	}

	public function testReadableButNotEditableTargetPageReturns403(): void {
		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest(),
			authority: $this->authorityThatCannotEditPageId( $this->targetPageId )
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertTrue( $this->subjectsOf( $this->sourcePageId )->getAllSubjects()->hasSubject( $this->movedId() ) );
		$this->assertFalse( $this->subjectsOf( $this->targetPageId )->getAllSubjects()->hasSubject( $this->movedId() ) );
	}

	public function testReadableButNotEditableSourcePageReturns403(): void {
		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest(),
			authority: $this->authorityThatCannotEditPageId( $this->sourcePageId )
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertFalse( $this->subjectsOf( $this->targetPageId )->getAllSubjects()->hasSubject( $this->movedId() ) );
	}

	/**
	 * @return string[]
	 */
	private function hostingPageNodeNames( string $subjectId ): array {
		$result = $this->readGraph(
			'MATCH (page:Page)-[:HasSubject]->(subject:Subject {id: $subjectId}) RETURN page.name AS name',
			[ 'subjectId' => $subjectId ]
		);

		$names = [];

		foreach ( $result as $record ) {
			$names[] = $record->toRecursiveArray()['name'];
		}

		return $names;
	}

	/**
	 * @return string[]
	 */
	private function subjectNodeLabels( string $subjectId ): array {
		$result = $this->readGraph(
			'MATCH (subject:Subject {id: $subjectId}) RETURN labels(subject) AS labels',
			[ 'subjectId' => $subjectId ]
		);

		return $result->count() === 0 ? [] : array_values( (array)( $result->first()->toRecursiveArray()['labels'] ?? [] ) );
	}

	private function newApi(): MoveSubjectApi {
		$csrfStub = $this->createStub( CsrfValidator::class );
		$csrfStub->method( 'verifyCsrfToken' )->willReturn( true );
		return new MoveSubjectApi( csrfValidator: $csrfStub );
	}

	private function newRequest( ?string $subjectId = null, ?array $body = null ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [ 'subjectId' => $subjectId ?? self::MOVED_ID ],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( $body ?? [ 'targetPageId' => $this->targetPageId ] ),
		] );
	}

	private function newSubject( string $id, string $label ): Subject {
		return TestSubject::build(
			id: $id,
			label: new SubjectLabel( $label ),
			schemaName: new SchemaName( self::SCHEMA )
		);
	}

	private function movedId(): SubjectId {
		return new SubjectId( self::MOVED_ID );
	}

	private function subjectsOf( int $pageId ): PageSubjects {
		return NeoWikiExtension::getInstance()->getSubjectRepository()
			->getSubjectsByPageId( new PageId( $pageId ) );
	}

	private function latestCommentOf( int $pageId ): ?string {
		return $this->getServiceContainer()->getRevisionLookup()
			->getRevisionByPageId( $pageId )?->getComment()?->text;
	}

}
