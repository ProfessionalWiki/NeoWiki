<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectPageApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\MoveSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ReplaceSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetMainSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetStatementApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetSubjectsOrderingApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Goes through real pages: the rules for Dependent Subjects are held by the Subject repository all write
 * endpoints share.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository
 * @covers \ProfessionalWiki\NeoWiki\Application\DependentSubjectGuard
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectPageApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ReplaceSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SetStatementApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SetMainSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SetSubjectsOrderingApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\MoveSubjectApi
 * @group Database
 */
class DependentSubjectRestWritesTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const string PERSON = 'sDSRWPerson1111';
	private const string BIRTH = 'sDSRWBirth11111';
	private const string NEW_BIRTH = 'sDSRWBirth22222';
	private const string OTHER_PERSON = 'sDSRWPerson2222';
	private const string PLACE = 'sDSRWSpain11111';

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema( 'Person', '{"propertyDefinitions":{'
			. '"Birth":{"type":"relation","targetSchemas":["Birth"],"multiple":true},'
			. '"Friend":{"type":"relation","targetSchemas":["Person"]}'
			. '}}' );
		$this->createSchema( 'Birth', '{"subjectsAreDependent":true,"propertyDefinitions":{'
			. '"Date":{"type":"text"}'
			. '}}' );
		$this->createSchema( 'Place', '{"propertyDefinitions":{}}' );
	}

	private function csrfValidator(): CsrfValidator {
		$validator = $this->createStub( CsrfValidator::class );
		$validator->method( 'verifyCsrfToken' )->willReturn( true );
		return $validator;
	}

	/**
	 * @param array<string, mixed> $pathParams
	 * @param array<string, mixed> $body
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private function call( Handler $handler, string $method, array $pathParams, array $body = [] ): array {
		$response = $this->executeHandler( $handler, new RequestData( [
			'method' => $method,
			'pathParams' => $pathParams,
			'bodyContents' => json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] ) );

		return [ $response->getStatusCode(), json_decode( $response->getBody()->getContents() ?: 'null', true ) ?? [] ];
	}

	private function person( Subject ...$dependentSubjects ): Subject {
		return TestSubject::build(
			id: self::PERSON,
			label: 'Pablo Picasso',
			schemaName: TestSchema::reference( 'Person' ),
			statements: new StatementList( $dependentSubjects === [] ? [] : [
				TestStatement::buildRelation(
					'Birth',
					array_map( static fn ( Subject $s ) => TestRelation::build( targetId: $s->id->text ), $dependentSubjects )
				),
			] )
		);
	}

	private function birth( string $id = self::BIRTH ): Subject {
		return TestSubject::build( id: $id, label: null, schemaName: TestSchema::reference( 'Birth' ) );
	}

	private function pageWithPersonAndBirth( string $pageName = 'Pablo Picasso' ): int {
		return $this->createPageWithSubjects( $pageName, $this->person( $this->birth() ), new SubjectMap( $this->birth() ) )
			->getPageId();
	}

	private function emptyPage( string $pageName ): int {
		return $this->createPageWithSubjects( $pageName )->getPageId();
	}

	private function subjectsOn( int $pageId ): PageSubjects {
		return NeoWikiExtension::getInstance()->newSubjectRepository()->getSubjectsByPageId( new PageId( $pageId ) );
	}

	/**
	 * @return string[]
	 */
	private function idsOn( int $pageId ): array {
		return $this->subjectsOn( $pageId )->getAllSubjects()->getIdsAsTextArray();
	}

	/**
	 * @param array<string, mixed> $responseBody
	 * @return string[]
	 */
	private function codesIn( array $responseBody ): array {
		return array_column( $responseBody['violations'] ?? [], 'code' );
	}

	private function birthItem( string $id = self::NEW_BIRTH ): array {
		return [ 'id' => $id, 'schema' => 'Birth', 'statements' => [ 'Date' => [ 'propertyType' => 'text', 'value' => [ '1881-10-25' ] ] ] ];
	}

	private function birthRelation( string $targetId = self::NEW_BIRTH ): array {
		return [ 'Birth' => [ 'propertyType' => 'relation', 'value' => [ [ 'target' => $targetId ] ] ] ];
	}

	public function testCreatesAHostSubjectAndItsDependentSubjectInOneRevision(): void {
		$pageId = $this->emptyPage( 'Pablo Picasso' );
		$revisionsBefore = $this->revisionCountOf( 'Pablo Picasso' );

		[ $status, $body ] = $this->call( new CreateSubjectApi( true, $this->csrfValidator() ), 'POST', [ 'pageId' => $pageId ], [
			'label' => 'Pablo Picasso',
			'schema' => 'Person',
			'statements' => $this->birthRelation(),
			'dependentSubjects' => [ $this->birthItem() ],
		] );

		$this->assertSame( 201, $status );
		$this->assertSame( [ $body['subjectId'], self::NEW_BIRTH ], $this->idsOn( $pageId ) );
		$this->assertSame( $revisionsBefore + 1, $this->revisionCountOf( 'Pablo Picasso' ) );
	}

	private function revisionCountOf( string $pageName ): int {
		return $this->getServiceContainer()->getRevisionStore()->countRevisionsByPageId(
			$this->getDb(),
			Title::newFromText( $pageName )->getArticleID()
		);
	}

	public function testCreatingADependentSubjectWithoutItsHostSubjectIsRefused(): void {
		$pageId = $this->emptyPage( 'Pablo Picasso' );

		[ $status, $body ] = $this->call( new CreateSubjectApi( false, $this->csrfValidator() ), 'POST', [ 'pageId' => $pageId ], [
			'schema' => 'Birth',
			'statements' => [],
		] );

		$this->assertSame( 422, $status );
		$this->assertSame( [ 'dependent-subject-without-host' ], $this->codesIn( $body ) );
	}

	public function testADependentSubjectIdAnotherPageHoldsIsRefused(): void {
		$this->pageWithPersonAndBirth( 'Holds the Birth' );
		$pageId = $this->emptyPage( 'Another person' );

		[ $status ] = $this->call( new CreateSubjectApi( true, $this->csrfValidator() ), 'POST', [ 'pageId' => $pageId ], [
			'schema' => 'Person',
			'statements' => $this->birthRelation( self::BIRTH ),
			'dependentSubjects' => [ $this->birthItem( self::BIRTH ) ],
		] );

		$this->assertSame( 409, $status );
	}

	public function testCreatingASubjectPageWritesItsDependentSubjects(): void {
		[ $status, $body ] = $this->call( new CreateSubjectPageApi( $this->csrfValidator() ), 'POST', [], [
			'label' => 'Pablo Picasso',
			'schema' => 'Person',
			'statements' => $this->birthRelation(),
			'dependentSubjects' => [ $this->birthItem() ],
		] );

		$this->assertSame( 201, $status );
		$this->assertSame( [ $body['subjectId'], self::NEW_BIRTH ], $this->idsOn( $body['pageId'] ) );
	}

	public function testABlockingViolationOfADependentSubjectRefusesASubjectPageUnderEnforcement(): void {
		$this->overrideConfigValue( 'NeoWikiEnforceValidation', true );
		NeoWikiExtension::resetInstance();
		$this->createSchema( 'Baptism', '{"subjectsAreDependent":true,"propertyDefinitions":{'
			. '"Date":{"type":"text","required":{"severity":"error"}}'
			. '}}' );
		$this->createSchema( 'Child', '{"propertyDefinitions":{'
			. '"Baptism":{"type":"relation","targetSchemas":["Baptism"]}'
			. '}}' );

		[ $status, $body ] = $this->call( new CreateSubjectPageApi( $this->csrfValidator() ), 'POST', [], [
			'label' => 'Pablo Picasso',
			'schema' => 'Child',
			'statements' => [ 'Baptism' => [ 'propertyType' => 'relation', 'value' => [ [ 'target' => self::NEW_BIRTH ] ] ] ],
			'dependentSubjects' => [ [ 'id' => self::NEW_BIRTH, 'schema' => 'Baptism', 'statements' => [] ] ],
		] );

		$this->assertSame( 422, $status );
		$this->assertSame( [ 'required' ], $this->codesIn( $body ) );
	}

	public function testCreatingAPageForADependentSubjectIsRefused(): void {
		[ $status, $body ] = $this->call( new CreateSubjectPageApi( $this->csrfValidator() ), 'POST', [], [
			'schema' => 'Birth',
			'statements' => [],
		] );

		$this->assertSame( 422, $status );
		$this->assertEqualsCanonicalizing(
			[ 'dependent-subject-is-main', 'dependent-subject-without-host' ],
			$this->codesIn( $body )
		);
	}

	public function testReplacingAHostSubjectWritesItsDependentSubjects(): void {
		$pageId = $this->pageWithPersonAndBirth();

		[ $status ] = $this->call( new ReplaceSubjectApi( $this->csrfValidator() ), 'PUT', [ 'subjectId' => self::PERSON ], [
			'label' => 'Pablo Picasso',
			'statements' => [ 'Birth' => [ 'propertyType' => 'relation', 'value' => [ [ 'target' => self::BIRTH ], [ 'target' => self::NEW_BIRTH ] ] ] ],
			'dependentSubjects' => [ $this->birthItem() ],
		] );

		$this->assertSame( 200, $status );
		$this->assertSame( [ self::PERSON, self::BIRTH, self::NEW_BIRTH ], $this->idsOn( $pageId ) );
	}

	public function testReplacingAHostSubjectWithoutTheRelationRemovesTheDependentSubject(): void {
		$pageId = $this->pageWithPersonAndBirth();

		$this->call( new ReplaceSubjectApi( $this->csrfValidator() ), 'PUT', [ 'subjectId' => self::PERSON ], [
			'label' => 'Pablo Picasso',
			'statements' => [],
		] );

		$this->assertSame( [ self::PERSON ], $this->idsOn( $pageId ) );
	}

	public function testSettingARelationToADependentSubjectOnAnotherPageIsRefused(): void {
		$this->pageWithPersonAndBirth( 'Holds the Birth' );
		$this->createPageWithSubjects(
			'Another person',
			TestSubject::build( id: self::OTHER_PERSON, schemaName: TestSchema::reference( 'Person' ) )
		);

		[ $status, $body ] = $this->call(
			new SetStatementApi( $this->csrfValidator() ),
			'PUT',
			[ 'subjectId' => self::OTHER_PERSON, 'propertyName' => 'Friend' ],
			[ 'statement' => [ 'propertyType' => 'relation', 'value' => [ [ 'target' => self::BIRTH ] ] ] ]
		);

		$this->assertSame( 422, $status );
		$this->assertContains( 'relation-to-dependent-subject', $this->codesIn( $body ) );
	}

	public function testDeletingADependentSubjectRemovesTheRelationToIt(): void {
		$pageId = $this->pageWithPersonAndBirth();

		[ $status ] = $this->call( new DeleteSubjectApi( $this->csrfValidator() ), 'DELETE', [ 'subjectId' => self::BIRTH ] );

		$this->assertSame( 200, $status );
		$this->assertSame(
			[],
			$this->subjectsOn( $pageId )->getMainSubject()->getReferencedSubjects()->asStringArray()
		);
	}

	public function testDeletingAHostSubjectRemovesItsDependentSubjects(): void {
		$pageId = $this->pageWithPersonAndBirth();

		$this->call( new DeleteSubjectApi( $this->csrfValidator() ), 'DELETE', [ 'subjectId' => self::PERSON ] );

		$this->assertSame( [], $this->idsOn( $pageId ) );
	}

	private function pageWherePersonsBirthPointsAtAPlace(): int {
		return $this->createPageWithSubjects(
			'Pablo Picasso',
			TestSubject::build(
				id: self::PERSON,
				schemaName: TestSchema::reference( 'Person' ),
				statements: new StatementList( [ TestStatement::buildRelation( 'Birth', [ TestRelation::build( targetId: self::PLACE ) ] ) ] )
			),
			new SubjectMap( TestSubject::build( id: self::PLACE, schemaName: TestSchema::reference( 'Place' ) ) )
		)->getPageId();
	}

	public function testDeletingAStandaloneSubjectABirthRelationPointsAtLeavesARedLink(): void {
		$pageId = $this->pageWherePersonsBirthPointsAtAPlace();

		[ $status ] = $this->call( new DeleteSubjectApi( $this->csrfValidator() ), 'DELETE', [ 'subjectId' => self::PLACE ] );

		$this->assertSame( 200, $status );
		$this->assertSame( [ self::PLACE ], $this->subjectsOn( $pageId )->getMainSubject()->getReferencedSubjects()->asStringArray() );
	}

	public function testMovingAStandaloneSubjectABirthRelationPointsAtLeavesARedLink(): void {
		$sourcePageId = $this->pageWherePersonsBirthPointsAtAPlace();
		$targetPageId = $this->emptyPage( 'Target page' );

		[ $status ] = $this->call( new MoveSubjectApi( $this->csrfValidator() ), 'POST', [ 'subjectId' => self::PLACE ], [
			'targetPageId' => $targetPageId,
		] );

		$this->assertSame( 200, $status );
		$this->assertSame( [ self::PLACE ], $this->subjectsOn( $sourcePageId )->getMainSubject()->getReferencedSubjects()->asStringArray() );
	}

	public function testAWriteLeavesADependentSubjectThatWasAlreadyUnreachedInPlace(): void {
		$pageId = $this->createPageWithSubjects( 'Pablo Picasso', $this->person(), new SubjectMap( $this->birth() ) )->getPageId();

		$this->call( new ReplaceSubjectApi( $this->csrfValidator() ), 'PUT', [ 'subjectId' => self::PERSON ], [
			'label' => 'Picasso',
			'statements' => [],
		] );

		$this->assertSame( [ self::PERSON, self::BIRTH ], $this->idsOn( $pageId ) );
	}

	public function testReplacingAHostSubjectWithItsStoredDependentSubjectSucceeds(): void {
		$this->pageWithPersonAndBirth();

		[ $status ] = $this->call( new ReplaceSubjectApi( $this->csrfValidator() ), 'PUT', [ 'subjectId' => self::PERSON ], [
			'label' => 'Pablo Picasso',
			'statements' => $this->birthRelation( self::BIRTH ),
			'dependentSubjects' => [ $this->birthItem( self::BIRTH ) ],
		] );

		$this->assertSame( 200, $status );
	}

	public function testReplacingADependentSubjectOfAnotherHostSubjectIsRefused(): void {
		$this->createPageWithSubjects(
			'Pablo Picasso',
			$this->person( $this->birth() ),
			new SubjectMap(
				$this->birth(),
				TestSubject::build(
					id: self::OTHER_PERSON,
					schemaName: TestSchema::reference( 'Person' ),
					statements: new StatementList( [ TestStatement::buildRelation( 'Birth', [ TestRelation::build( targetId: self::NEW_BIRTH ) ] ) ] )
				),
				$this->birth( self::NEW_BIRTH )
			)
		);

		[ $status ] = $this->call( new ReplaceSubjectApi( $this->csrfValidator() ), 'PUT', [ 'subjectId' => self::PERSON ], [
			'label' => 'Pablo Picasso',
			'statements' => $this->birthRelation( self::BIRTH ),
			'dependentSubjects' => [ $this->birthItem( self::NEW_BIRTH ) ],
		] );

		$this->assertSame( 409, $status );
	}

	public function testSupplyingADependentSubjectTheWriteLeavesUnreachedIsRefused(): void {
		$this->pageWithPersonAndBirth();

		[ $status, $body ] = $this->call( new ReplaceSubjectApi( $this->csrfValidator() ), 'PUT', [ 'subjectId' => self::PERSON ], [
			'label' => 'Pablo Picasso',
			'statements' => [],
			'dependentSubjects' => [ $this->birthItem( self::BIRTH ) ],
		] );

		$this->assertSame( 422, $status );
		$this->assertSame( [ 'dependent-subject-without-host' ], $this->codesIn( $body ) );
	}

	public function testMakingADependentSubjectTheMainSubjectIsRefused(): void {
		$pageId = $this->pageWithPersonAndBirth();

		[ $status, $body ] = $this->call( new SetMainSubjectApi( $this->csrfValidator() ), 'PUT', [ 'pageId' => $pageId ], [
			'subjectId' => self::BIRTH,
		] );

		$this->assertSame( 422, $status );
		$this->assertSame( [ 'dependent-subject-is-main' ], $this->codesIn( $body ) );
	}

	public function testOrderingADependentSubjectFirstAsMainSubjectIsRefused(): void {
		$pageId = $this->pageWithPersonAndBirth();

		[ $status, $body ] = $this->call( new SetSubjectsOrderingApi( $this->csrfValidator() ), 'PUT', [ 'pageId' => $pageId ], [
			'mainSubjectId' => self::BIRTH,
			'otherSubjectIds' => [ self::PERSON ],
		] );

		$this->assertSame( 422, $status );
		$this->assertSame( [ 'dependent-subject-is-main' ], $this->codesIn( $body ) );
	}

	public function testMovingAHostSubjectTakesItsDependentSubjectAlong(): void {
		$this->pageWithPersonAndBirth();
		$targetPageId = $this->emptyPage( 'Target page' );

		[ $status ] = $this->call( new MoveSubjectApi( $this->csrfValidator() ), 'POST', [ 'subjectId' => self::PERSON ], [
			'targetPageId' => $targetPageId,
		] );

		$this->assertSame( 200, $status );
		$this->assertSame( [ self::PERSON, self::BIRTH ], $this->idsOn( $targetPageId ) );
	}

	public function testAMoveTheTargetPageRefusesLeavesTheSubjectAndItsDependentSubjectOnTheSourcePage(): void {
		$sourcePageId = $this->pageWithPersonAndBirth();
		$targetPageId = $this->emptyPage( 'Target page' );
		$this->createSchema( 'Person', '{"propertyDefinitions":{'
			. '"Birth":{"type":"relation","targetSchemas":["Place"],"multiple":true}'
			. '}}' );

		[ $status, $body ] = $this->call( new MoveSubjectApi( $this->csrfValidator() ), 'POST', [ 'subjectId' => self::PERSON ], [
			'targetPageId' => $targetPageId,
		] );

		$this->assertSame( 422, $status );
		$this->assertSame( [ 'relation-to-dependent-subject' ], $this->codesIn( $body ) );
		$this->assertSame( [ self::PERSON, self::BIRTH ], $this->idsOn( $sourcePageId ) );
	}

	public function testMovingADependentSubjectOnItsOwnIsRefused(): void {
		$this->pageWithPersonAndBirth();
		$targetPageId = $this->emptyPage( 'Target page' );

		[ $status, $body ] = $this->call( new MoveSubjectApi( $this->csrfValidator() ), 'POST', [ 'subjectId' => self::BIRTH ], [
			'targetPageId' => $targetPageId,
		] );

		$this->assertSame( 422, $status );
		$this->assertSame( [ 'dependent-subject-without-host' ], $this->codesIn( $body ) );
	}

}
