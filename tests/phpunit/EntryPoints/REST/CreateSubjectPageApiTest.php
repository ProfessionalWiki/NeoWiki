<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectPageApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectPageApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage\CreateSubjectPageAction
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestCreateSubjectPagePresenter
 * @group Database
 */
class CreateSubjectPageApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private const string SCHEMA = 'Employee';

	protected function setUp(): void {
		parent::setUp();

		$this->createSchema( self::SCHEMA );
	}

	public function testCreatesThePageTheLabelTitlesWithTheSubjectAsItsMainSubject(): void {
		$response = $this->create( [ 'label' => 'Amsterdam' ] );
		$body = $this->bodyOf( $response );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'Amsterdam', $body['pageTitle'] );
		$this->assertSame(
			$body['subjectId'],
			$this->mainSubjectIdOf( 'Amsterdam' )
		);
	}

	public function testTitlesThePageAfterTheSubjectWhenNoLabelTitlesIt(): void {
		$body = $this->bodyOf( $this->create( [] ) );

		$this->assertSame( $this->titleOfSubjectId( $body['subjectId'] ), $body['pageTitle'] );
		$this->assertSame( $body['subjectId'], $this->mainSubjectIdOf( $body['pageTitle'] ) );
	}

	/**
	 * A label naming another namespace or no valid title titles no page, and nothing is invented
	 * from it: the Subject gets a page of its own under its id.
	 */
	public function testTitlesThePageAfterTheSubjectWhenTheLabelNamesAnotherNamespace(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Help:Getting started' ] ) );

		$this->assertSame( $this->titleOfSubjectId( $body['subjectId'] ), $body['pageTitle'] );
		$this->assertSame( 'Help:Getting started', $this->storedLabelOf( $body['subjectId'] ) );
	}

	public function testTitlesThePageAfterTheSubjectWhenTheLabelIsNotAValidTitle(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Bad|title' ] ) );

		$this->assertSame( $this->titleOfSubjectId( $body['subjectId'] ), $body['pageTitle'] );
	}

	public function testTitlesThePageWithThePageTitleGivenRatherThanTheLabel(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam', 'pageTitle' => 'Delft' ] ) );

		$this->assertSame( 'Delft', $body['pageTitle'] );
		$this->assertSame( $body['subjectId'], $this->mainSubjectIdOf( 'Delft' ) );
		$this->assertSame( 'Amsterdam', $this->storedLabelOf( $body['subjectId'] ) );
	}

	public function testTitlesThePageByTheLabelWhenThePageTitleIsEmpty(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam', 'pageTitle' => '' ] ) );

		$this->assertSame( 'Amsterdam', $body['pageTitle'] );
	}

	/**
	 * A title the caller typed is not silently swapped for another: what they asked for either
	 * titles the page or comes back to them, since only they know what they meant.
	 */
	public function testAnswersBadRequestForAPageTitleThatTitlesNoPageHere(): void {
		$response = $this->create( [ 'label' => 'Amsterdam', 'pageTitle' => 'Help:Getting started' ] );
		$body = $this->bodyOf( $response );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'error', $body['status'] );
		$this->assertStringContainsString( 'Help:Getting started', $body['message'] );
		$this->assertFalse( Title::newFromText( 'Amsterdam' )->exists() );
	}

	public function testAnswersTheTitleTakenWhenAPageHoldsThePageTitleGiven(): void {
		$this->createPageWithSubjects( 'Delft', TestSubject::build( id: 's11111111111maa' ) );

		$response = $this->create( [ 'label' => 'Amsterdam', 'pageTitle' => 'Delft' ] );

		$this->assertSame( 409, $response->getStatusCode() );
		$this->assertSame( 'Delft', $this->bodyOf( $response )['pageTitle'] );
	}

	public function testStoresTheLabelThatTitledThePage(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam' ] ) );

		$this->assertSame( 'Amsterdam', $this->storedLabelOf( $body['subjectId'] ) );
	}

	public function testWritesThePageAndItsSubjectInOneRevision(): void {
		$this->create( [ 'label' => 'Amsterdam' ] );

		$revision = $this->getServiceContainer()->getRevisionStore()
			->getRevisionByTitle( Title::newFromText( 'Amsterdam' ) );

		$this->assertNotNull( $revision );
		$this->assertSame( 0, $revision->getParentId(), 'The page should hold exactly one revision' );
		$this->assertTrue( $revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) );
		$this->assertSame( '', $revision->getContent( SlotRecord::MAIN )?->serialize() );
	}

	public function testIndexesTheCreatedSubjectAgainstItsPage(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam' ] ) );

		$this->assertSame(
			(string)Title::newFromText( 'Amsterdam' )->getId(),
			(string)$this->getDb()->newSelectQueryBuilder()
				->select( 'nwsp_page_id' )
				->from( DatabaseSubjectPageIndex::TABLE )
				->where( [ 'nwsp_subject_id' => $body['subjectId'] ] )
				->caller( __METHOD__ )
				->fetchField()
		);
	}

	public function testShowsASubjectOnAPageTitledAfterItUnderItsSchema(): void {
		$body = $this->bodyOf( $this->create( [] ) );

		$this->assertSame( self::SCHEMA, $body['subject']['displayName'] );
		$this->assertTrue( $body['subject']['displayNameIsGenerated'] );
	}

	public function testShowsASubjectOnAPageTitledByItsLabelUnderThatLabel(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam' ] ) );

		$this->assertSame( 'Amsterdam', $body['subject']['displayName'] );
		$this->assertFalse( $body['subject']['displayNameIsGenerated'] );
	}

	/**
	 * The title the caller supplied goes back and nothing else: which page holds it, and what that
	 * page carries, are not theirs to learn from a title they typed.
	 */
	public function testAnswersTheTitleTakenWithoutDescribingThePageHoldingIt(): void {
		$this->createPageWithSubjects( 'Amsterdam', TestSubject::build( id: 's11111111111maa' ) );

		$response = $this->create( [ 'label' => 'Amsterdam' ] );
		$body = $this->bodyOf( $response );

		$this->assertSame( 409, $response->getStatusCode() );
		$this->assertSame( 'error', $body['status'] );
		$this->assertSame( 'Amsterdam', $body['pageTitle'] );
		$this->assertSame( [ 'status', 'message', 'pageTitle' ], array_keys( $body ) );
	}

	public function testCreatesNoSubjectWhenTheTitleIsTaken(): void {
		$this->editPage( Title::newFromText( 'Amsterdam' ), 'Plain wikitext' );

		$this->create( [ 'label' => 'Amsterdam' ] );

		$this->assertNull( $this->mainSubjectIdOf( 'Amsterdam' ) );
	}

	/**
	 * The existence check reads a replica, so a page can be there by the time the write runs.
	 * EDIT_NEW is what refuses it then, and looking again is what turns that refusal into the
	 * answer the caller can act on. A link cache saying the page is absent is what a replica
	 * behind the primary looks like.
	 */
	public function testAnswersTheTitleTakenWhenThePageAppearsUnderTheWrite(): void {
		$this->editPage( Title::newFromText( 'Amsterdam' ), 'Someone else got here first' );

		Title::clearCaches();
		$this->getServiceContainer()->getLinkCache()->addBadLinkObj( Title::newFromText( 'Amsterdam' ) );

		$response = $this->create( [ 'label' => 'Amsterdam' ] );

		$this->assertSame( 409, $response->getStatusCode() );
		$this->assertSame( 'Amsterdam', $this->bodyOf( $response )['pageTitle'] );
	}

	/**
	 * A write that failed for a reason of its own, rather than for a page in the way: the reason
	 * goes to the caller, since the status code alone says only that something went wrong.
	 */
	public function testCarriesTheReasonAWriteThatFailedGave(): void {
		$this->setTemporaryHook(
			'MultiContentSave',
			static function ( $renderedRevision, $user, $summary, $flags, $hookStatus ): bool {
				$hookStatus->fatal( 'neowiki-test-save-refused' );
				return false;
			}
		);

		$response = $this->create( [ 'label' => 'Amsterdam' ] );

		$this->assertSame( 500, $response->getStatusCode() );
		$this->assertStringContainsString( 'neowiki-test-save-refused', $this->bodyOf( $response )['message'] );
	}

	public function testRefusesACallerWhoMayNotCreatePages(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$response = $this->create(
			[ 'label' => 'Amsterdam' ],
			$this->getTestUser()->getAuthority()
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $this->bodyOf( $response )['status'] );
		$this->assertFalse( Title::newFromText( 'Amsterdam' )->exists() );
	}

	/**
	 * Authorization comes before the page is looked for, so a title the caller may not write
	 * answers the same whether or not a page holds it. Answering 409 there would report a page
	 * they may not touch.
	 */
	public function testRefusesACallerWhoMayNotWriteTheTitleEvenWhenAPageHoldsIt(): void {
		$this->createPageWithSubjects( 'Amsterdam', TestSubject::build( id: 's11111111111maa' ) );
		$this->setGroupPermissions( '*', 'edit', false );
		$this->setGroupPermissions( 'user', 'edit', false );

		$response = $this->create( [ 'label' => 'Amsterdam' ], $this->getTestUser()->getAuthority() );

		$this->assertSame( 403, $response->getStatusCode() );
	}

	/**
	 * The same before validation: a caller who may not write the title learns nothing about the
	 * Subject they proposed either.
	 */
	public function testRefusesACallerWhoMayNotCreatePagesBeforeValidating(): void {
		$this->setMwGlobals( 'wgNeoWikiEnforceValidation', true );
		$this->createSchema(
			'EnforcementSchema',
			'{"title":"EnforcementSchema","propertyDefinitions":{"Required":{"type":"text","required":{"severity":"error"}}}}'
		);
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$response = $this->create(
			[ 'label' => 'Amsterdam', 'schema' => 'EnforcementSchema', 'statements' => [] ],
			$this->getTestUser()->getAuthority()
		);

		$this->assertSame( 403, $response->getStatusCode() );
	}

	public function testAnswersBadRequestForAStatementThatDeclaresNoPropertyType(): void {
		$response = $this->create( [
			'label' => 'Amsterdam',
			'statements' => [ 'animal' => [ 'propertyType' => 42, 'value' => 'bunny' ] ],
		] );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'error', $this->bodyOf( $response )['status'] );
		$this->assertFalse( Title::newFromText( 'Amsterdam' )->exists() );
	}

	public function testReportsValidationFailuresAsTheOtherCreateEndpointsDo(): void {
		$this->setMwGlobals( 'wgNeoWikiEnforceValidation', true );
		$this->createSchema(
			'EnforcementSchema',
			'{"title":"EnforcementSchema","propertyDefinitions":{"Required":{"type":"text","required":{"severity":"error"}}}}'
		);

		$response = $this->create( [
			'label' => 'Amsterdam',
			'schema' => 'EnforcementSchema',
			'statements' => [],
		] );
		$body = $this->bodyOf( $response );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'error', $body['status'] );
		$this->assertSame( 'Validation failed', $body['message'] );
		$this->assertSame( 'Required', $body['violations'][0]['propertyName'] );
		$this->assertSame( 'required', $body['violations'][0]['code'] );
		$this->assertFalse( Title::newFromText( 'Amsterdam' )->exists() );
	}

	public function testUsesTheCommentAsTheEditSummary(): void {
		$this->create( [ 'label' => 'Amsterdam', 'comment' => 'Because I said so' ] );

		$this->assertSame(
			'Because I said so',
			$this->getServiceContainer()->getRevisionStore()
				->getRevisionByTitle( Title::newFromText( 'Amsterdam' ) )?->getComment()?->text
		);
	}

	public function testSavesTheStatementsGiven(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam' ] ) );

		$this->assertSame( [ 'bunny' ], $this->storedStatementValueOf( $body['subjectId'], 'animal' ) );
	}

	public function testCreatesTheSubjectUnderTheIdSupplied(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam', 'id' => 'sPreMintedAAAA1' ] ) );

		$this->assertSame( 'sPreMintedAAAA1', $body['subjectId'] );
		$this->assertSame( 'sPreMintedAAAA1', $this->mainSubjectIdOf( 'Amsterdam' ) );
	}

	public function testAnswersBadRequestForAMalformedSuppliedId(): void {
		$response = $this->create( [ 'label' => 'Amsterdam', 'id' => 'not-a-subject-id' ] );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertFalse( Title::newFromText( 'Amsterdam' )->exists() );
	}

	public function testAnswersBadRequestForASuppliedIdFromAnotherSource(): void {
		$response = $this->create( [ 'label' => 'Amsterdam', 'id' => 'otherwiki:sPreMintedAAAA2' ] );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertFalse( Title::newFromText( 'Amsterdam' )->exists() );
	}

	/**
	 * Only a caller that minted the id up front can meet this, and it means their create already
	 * landed, so the conflict names the Subject rather than the title it would have taken.
	 */
	public function testAnswersConflictForASuppliedIdAlreadyInUse(): void {
		$this->createPageWithSubjects(
			'CreateSubjectPageApiTest_Taken',
			TestSubject::build( id: 'sPreMintedAAAA3' )
		);

		$response = $this->create( [ 'label' => 'Amsterdam', 'id' => 'sPreMintedAAAA3' ] );

		$this->assertSame( 409, $response->getStatusCode() );
		$this->assertSame( 'Subject already exists', $this->bodyOf( $response )['message'] );
		$this->assertFalse( Title::newFromText( 'Amsterdam' )->exists() );
	}

	/**
	 * A retry of a create that landed meets both conflicts: its Subject holds the id, and the page
	 * it made holds the title. The id is the answer that lets the caller carry on, so it comes
	 * first; the title conflict would send them looking for a page they made themselves.
	 */
	public function testAnswersTheIdConflictRatherThanTheTitleOneWhenBothStand(): void {
		$this->createPageWithSubjects( 'Amsterdam', TestSubject::build( id: 'sPreMintedAAAA4' ) );

		$response = $this->create( [ 'label' => 'Amsterdam', 'id' => 'sPreMintedAAAA4' ] );

		$this->assertSame( 409, $response->getStatusCode() );
		$this->assertSame( 'Subject already exists', $this->bodyOf( $response )['message'] );
	}

	public function testAnswersTheSchemaTheSubjectInstantiates(): void {
		$body = $this->bodyOf( $this->create( [ 'label' => 'Amsterdam' ] ) );

		$this->assertArrayHasKey( 'schema', $body );
	}

	/**
	 * @param array<string, mixed> $bodyOverrides
	 */
	private function create( array $bodyOverrides, mixed $authority = null ): Response {
		return $this->executeHandler(
			$this->newHandler(),
			new RequestData( [
				'method' => 'POST',
				'bodyContents' => json_encode( $bodyOverrides + [
					'schema' => self::SCHEMA,
					'statements' => [
						'animal' => [ 'propertyType' => 'text', 'value' => 'bunny' ],
					],
				] ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] ),
			authority: $authority
		);
	}

	private function newHandler(): CreateSubjectPageApi {
		$csrfValidator = $this->createStub( CsrfValidator::class );
		$csrfValidator->method( 'verifyCsrfToken' )->willReturn( true );

		return new CreateSubjectPageApi( csrfValidator: $csrfValidator );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function bodyOf( Response $response ): array {
		return json_decode( $response->getBody()->getContents(), true );
	}

	/**
	 * The page a Subject id titles, as the wiki stores it: capitalizing the first letter of a
	 * main-namespace title is the default, and off it is the id unchanged.
	 */
	private function titleOfSubjectId( string $subjectId ): string {
		return Title::newFromText( $subjectId )->getPrefixedText();
	}

	private function mainSubjectIdOf( string $pageName ): ?string {
		$title = Title::newFromText( $pageName );

		if ( !$title->exists() ) {
			return null;
		}

		return NeoWikiExtension::getInstance()->newSubjectRepository()
			->getSubjectsByPageId( new PageId( $title->getId() ) )
			->getMainSubject()?->getId()->text;
	}

	private function storedStatementValueOf( string $subjectId, string $propertyName ): mixed {
		return $this->storedSubject( $subjectId )?->getStatements()
			->getStatement( new PropertyName( $propertyName ) )?->getValue()->toScalars();
	}

	private function storedLabelOf( string $subjectId ): ?string {
		$label = $this->storedSubject( $subjectId )?->getLabel();

		return $label instanceof SubjectLabel ? $label->text : null;
	}

	private function storedSubject( string $subjectId ): ?Subject {
		return NeoWikiExtension::getInstance()->newSubjectRepository()->getSubject( new SubjectId( $subjectId ) );
	}

}
