<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction
 * @group Database
 */
class CreateSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	// A page id far above anything a fresh test database mints, so it resolves to no page.
	private const int NONEXISTENT_PAGE_ID = 999999;

	protected function setUp(): void {
		parent::setUp();
		// Clear the graph so the client-supplied-id tests, which use fixed ids, start from a clean
		// subject -> page index rather than nodes projected by earlier tests or runs.
		$this->setUpNeo4j();
	}

	public function testCreatesSubject(): void {
		$this->createSchema( 'Employee' );

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createValidRequestData()
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'created', $responseData['status'] );
		$this->assertArrayHasKey( 'violations', $responseData );
		$this->assertSame( [], $responseData['violations'] );

		$subject = NeoWikiExtension::getInstance()->newSubjectRepository()->getSubject( new SubjectId( $responseData['subjectId'] ) );

		$this->assertSame( 'Test subject', $subject->label->text );
		$this->assertSame( 'Employee', $subject->getSchemaName()->getText() );
		$this->assertEquals(
			new StatementList( [
				TestStatement::build( property: 'animal', value: 'bunny' ),
				TestStatement::build( property: 'fluff', value: new NumberValue( 9001 ), propertyType: 'number' ),
			] ),
			$subject->getStatements()
		);
	}

	private function newCreateSubjectApi( bool $isMainSubject = true ): CreateSubjectApi {
		$csrfValidatorstub = $this->createStub( CsrfValidator::class );
		$csrfValidatorstub->method( 'verifyCsrfToken' )->willReturn( true );

		return new CreateSubjectApi(
			isMainSubject: $isMainSubject,
			csrfValidator: $csrfValidatorstub
		);
	}

	private function createValidRequestData(): RequestData {
		return $this->createRequestData( $this->validBody() );
	}

	private function createRequestData( array $body ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [
				'pageId' => $this->getIdOfExistingPage()
			],
			'bodyContents' => json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	private function validBody(): array {
		return [
			'label' => 'Test subject',
			'schema' => 'Employee',
			'statements' => [
				'animal' => [
					'propertyType' => 'text',
					'value' => 'bunny'
				],
				'fluff' => [
					'propertyType' => 'number',
					'value' => 9001
				],
			]
		];
	}

	private function getIdOfExistingPage(): int {
		$title = Title::newFromText( 'CreateSubjectApiTest' );

		$this->editPage( $title, 'Whatever wikitext' );
		return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )->getId();
	}

	public function testReadableButNotEditablePageReturns403(): void {
		$this->createSchema( 'Employee' );

		// The caller can read the page - so its existence is already public - but cannot edit it.
		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createValidRequestData(),
			authority: $this->authorityWithGlobalEditButNoPageEdit()
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'You do not have the necessary permissions to create this subject', $responseData['message'] );
		$this->assertArrayNotHasKey( 'violations', $responseData );
	}

	public function testUnreadablePageIsIndistinguishableFromNonexistentPage(): void {
		$this->createSchema( 'Employee' );

		// A real page the caller may not read: a write to it must not reveal that it exists.
		$unreadable = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createValidRequestData(),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		// A page id that resolves to no page at all.
		$nonexistent = $this->executeHandler(
			$this->newCreateSubjectApi(),
			new RequestData( [
				'method' => 'POST',
				'pathParams' => [ 'pageId' => self::NONEXISTENT_PAGE_ID ],
				'bodyContents' => json_encode( $this->validBody() ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] ),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$this->assertSame( 404, $unreadable->getStatusCode() );
		$this->assertSame( 404, $nonexistent->getStatusCode() );
		// Byte-identical: a caller sweeping page ids cannot tell a hidden page from an absent one.
		$this->assertSame(
			$nonexistent->getBody()->getContents(),
			$unreadable->getBody()->getContents()
		);
	}

	public function testResponseIncludesViolationsWhenRequiredPropertyMissing(): void {
		$this->createSchema(
			'CreateViolationSchema',
			'{"title":"CreateViolationSchema","propertyDefinitions":{"Status":{"type":"text","required":true}}}'
		);

		$body = [
			'label' => 'Subject with missing required',
			'schema' => 'CreateViolationSchema',
			'statements' => [],
		];

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'created', $responseData['status'] );
		$this->assertNotEmpty( $responseData['violations'] );
		$this->assertSame( 'required', $responseData['violations'][0]['code'] );
		$this->assertSame( 'Status', $responseData['violations'][0]['propertyName'] );
	}

	public function testResponseIncludesSchemaNotFoundWhenSchemaMissing(): void {
		$body = [
			'label' => 'Subject with missing schema',
			'schema' => 'NeverCreatedSchema',
			'statements' => [],
		];

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'created', $responseData['status'] );
		$this->assertCount( 1, $responseData['violations'] );
		$this->assertSame( 'schema-not-found', $responseData['violations'][0]['code'] );
		$this->assertNull( $responseData['violations'][0]['propertyName'] );
		$this->assertSame( [ 'NeverCreatedSchema' ], $responseData['violations'][0]['args'] );
	}

	public function testAlreadyExistingMainSubjectResponseOmitsViolations(): void {
		$this->createSchema( 'Employee' );

		$pageTitle = 'CreateSubjectApiTestAlreadyExists';
		$this->createPageWithSubjects(
			$pageTitle,
			mainSubject: TestSubject::build(
				id: 'sTestCSA11111A1',
				label: new SubjectLabel( 'First main subject' ),
			)
		);
		$pageId = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageTitle ) )->getId();

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			new RequestData( [
				'method' => 'POST',
				'pathParams' => [ 'pageId' => $pageId ],
				'bodyContents' => json_encode( $this->validBody() ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 409, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'Subject already exists', $responseData['message'] );
		$this->assertArrayNotHasKey( 'violations', $responseData );
	}

	public function testRejectsBodyMissingLabel(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$body = $this->validBody();
		unset( $body['label'] );

		$this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);
	}

	public function testEmptyLabelReturns400(): void {
		$this->createSchema( 'Employee' );

		$body = $this->validBody();
		$body['label'] = '   ';

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testEnforcementBlockedReturns422(): void {
		$this->setMwGlobals( 'wgNeoWikiEnforceValidation', true );

		$this->createSchema(
			'EnforcementSchema',
			'{"title":"EnforcementSchema","propertyDefinitions":{"Required":{"type":"text","required":true}}}'
		);

		$body = $this->validBody();
		$body['schema'] = 'EnforcementSchema';
		$body['statements'] = [];

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'Validation failed', $responseData['message'] );
		$this->assertSame( 'Required', $responseData['violations'][0]['propertyName'] );
		$this->assertSame( 'required', $responseData['violations'][0]['code'] );
	}

	public function testCreatesChildSubjectWithSuppliedId(): void {
		$this->createSchema( 'Employee' );
		$suppliedId = 'sMintAAAAAAAAA1';

		$body = $this->validBody();
		$body['id'] = $suppliedId;

		$response = $this->executeCreate( $this->getIdOfExistingPage(), $body, isMainSubject: false );

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( $suppliedId, $responseData['subjectId'] );

		$subject = NeoWikiExtension::getInstance()->newSubjectRepository()->getSubject( new SubjectId( $suppliedId ) );
		$this->assertSame( $suppliedId, $subject->getId()->text );
	}

	public function testCreatesMainSubjectWithSuppliedId(): void {
		$this->createSchema( 'Employee' );
		$suppliedId = 'sMintEEEEEEEEE5';

		$body = $this->validBody();
		$body['id'] = $suppliedId;

		$response = $this->executeCreate( $this->getIdOfExistingPage(), $body, isMainSubject: true );

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( $suppliedId, $responseData['subjectId'] );
	}

	public function testMalformedSuppliedIdReturns400(): void {
		$this->createSchema( 'Employee' );

		$body = $this->validBody();
		$body['id'] = 'not-a-valid-subject-id';

		$response = $this->executeCreate( $this->getIdOfExistingPage(), $body, isMainSubject: false );

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testSuppliedIdAlreadyUsedOnAnotherPageReturns409(): void {
		$this->createSchema( 'Employee' );
		$suppliedId = 'sMintBBBBBBBBB2';

		// Create the Subject on one page through the API, so it is projected into the graph.
		$firstBody = $this->validBody();
		$firstBody['id'] = $suppliedId;
		$firstResponse = $this->executeCreate( $this->pageIdOfNewPage( 'CreateSubjectApiTestOtherPage' ), $firstBody, isMainSubject: false );
		$this->assertSame( 201, $firstResponse->getStatusCode() );

		// Reusing it on a different page is rejected via the graph's subject -> page index.
		$secondBody = $this->validBody();
		$secondBody['id'] = $suppliedId;
		$secondResponse = $this->executeCreate( $this->getIdOfExistingPage(), $secondBody, isMainSubject: false );

		$responseData = json_decode( $secondResponse->getBody()->getContents(), true );

		$this->assertSame( 409, $secondResponse->getStatusCode() );
		$this->assertSame( 'Subject already exists', $responseData['message'] );
	}

	public function testSuppliedIdMatchingAnExistingChildReturns409(): void {
		$this->createSchema( 'Employee' );
		$suppliedId = 'sMintCCCCCCCCC3';
		$pageTitle = 'CreateSubjectApiTestExistingChild';

		$this->createPageWithSubjects(
			$pageTitle,
			childSubjects: new SubjectMap( TestSubject::build( id: $suppliedId ) )
		);

		$body = $this->validBody();
		$body['id'] = $suppliedId;

		$response = $this->executeCreate( $this->pageIdOf( $pageTitle ), $body, isMainSubject: false );

		$this->assertSame( 409, $response->getStatusCode() );
	}

	public function testSuppliedIdMatchingThePageMainSubjectReturns409(): void {
		$this->createSchema( 'Employee' );
		$suppliedId = 'sMintDDDDDDDDD4';
		$pageTitle = 'CreateSubjectApiTestMainCollision';

		$this->createPageWithSubjects(
			$pageTitle,
			mainSubject: TestSubject::build( id: $suppliedId )
		);

		$body = $this->validBody();
		$body['id'] = $suppliedId;

		// Creating a child Subject whose id equals the page's main Subject must be rejected
		// (regression: the child guard previously ignored the main Subject).
		$response = $this->executeCreate( $this->pageIdOf( $pageTitle ), $body, isMainSubject: false );

		$this->assertSame( 409, $response->getStatusCode() );
	}

	public function testInterlinkedSubjectsCanBeCreatedInAnyOrder(): void {
		$this->createSchema( 'Employee' );
		$idA = 'sMintFFFFFFFFF6';
		$idB = 'sMintGGGGGGGGG7';
		$pageId = $this->getIdOfExistingPage();

		// Subject B references A before A exists (relation targets are not validated on create).
		$responseB = $this->executeCreate( $pageId, [
			'label' => 'Subject B',
			'schema' => 'Employee',
			'id' => $idB,
			'statements' => [
				'Has colleague' => [
					'propertyType' => 'relation',
					'value' => [ [ 'target' => $idA ] ],
				],
			],
		], isMainSubject: false );
		$this->assertSame( 201, $responseB->getStatusCode() );

		// Now create A with its pre-chosen id.
		$responseA = $this->executeCreate( $pageId, [
			'label' => 'Subject A',
			'schema' => 'Employee',
			'id' => $idA,
			'statements' => [],
		], isMainSubject: false );
		$this->assertSame( 201, $responseA->getStatusCode() );

		$repository = NeoWikiExtension::getInstance()->newSubjectRepository();
		$this->assertSame( $idA, $repository->getSubject( new SubjectId( $idA ) )->getId()->text );
		$this->assertSame( $idB, $repository->getSubject( new SubjectId( $idB ) )->getId()->text );
	}

	private function executeCreate( int $pageId, array $body, bool $isMainSubject = false ): Response {
		return $this->executeHandler(
			$this->newCreateSubjectApi( $isMainSubject ),
			new RequestData( [
				'method' => 'POST',
				'pathParams' => [ 'pageId' => $pageId ],
				'bodyContents' => json_encode( $body ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);
	}

	private function pageIdOf( string $title ): int {
		return MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $title ) )->getId();
	}

	private function pageIdOfNewPage( string $title ): int {
		$this->editPage( Title::newFromText( $title ), 'Whatever wikitext' );
		return $this->pageIdOf( $title );
	}

}
