<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement\UpdateStatementAction;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\UpdateStatement\UpdateStatementAction
 */
class UpdateStatementActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111127';
	private const string SCHEMA_NAME = 'TestSchema';

	private InMemorySubjectRepository $subjectRepository;
	private InMemorySchemaLookup $schemaLookup;
	private UpdateStatementPresenterSpy $presenterSpy;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->schemaLookup = new InMemorySchemaLookup();
		$this->presenterSpy = new UpdateStatementPresenterSpy();
	}

	private function newAction(
		?SubjectWriteAuthorizer $authorizer = null,
		bool $validationEnforced = false,
		?PageReadAuthorizer $readAuthorizer = null,
	): UpdateStatementAction {
		$registry = PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY );

		return new UpdateStatementAction(
			subjectRepository: $this->subjectRepository,
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: $authorizer ?? new SpySubjectWriteAuthorizer( allowed: true ),
			statementListBuilder: new StatementListBuilder(
				propertyTypeLookup: $registry,
				idGenerator: new StubIdGenerator( '11111111111127' ),
				subjectIdParser: TestSubjectIds::newParser()
			),
			schemaLookup: $this->schemaLookup,
			selectStatementResolver: new SelectStatementResolver( new SelectValueResolver() ),
			proposedSubjectValidator: new ProposedSubjectValidator(
				schemaResolver: TestSources::newSchemaResolver( $this->schemaLookup ),
				subjectValidator: new SubjectValidator(
					propertyTypeLookup: $registry,
					subjectLookup: new InMemorySubjectLookup(),
					sourceRegistry: TestSources::newRegistry(),
				),
			),
			presenter: $this->presenterSpy,
			validationEnforced: $validationEnforced,
			// The Subject under test sits on a namespaced page between two others, so neither a
			// hardcoded main-namespace id nor an implementation answering with some other seeded
			// page passes.
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111126' ), new PageIdentifiers( new PageId( 6 ), 'Earlier page', 0 ) ],
				[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 7 ), 'Help:Test page', 12 ) ],
				[ new SubjectId( 's11111111111128' ), new PageIdentifiers( new PageId( 8 ), 'Talk:Later page', 1 ) ],
			] ),
		);
	}

	/**
	 * `required` at Error severity, so that missing it blocks a write under enforcement. The
	 * shorthand form TestProperty builds defaults to Warning, which never blocks (ADR 26).
	 */
	private function newRequiredTextProperty(): TextProperty {
		return new TextProperty(
			core: new PropertyCore(
				description: '',
				required: true,
				default: null,
				constraintSeverities: [ 'required' => Severity::Error ],
			),
			multiple: false,
			uniqueItems: false,
			minLength: null,
			maxLength: null,
		);
	}

	private function registerSchema( PropertyDefinitions $properties ): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SCHEMA_NAME ),
			description: '',
			properties: $properties
		) );
	}

	private function storeSubject( ?StatementList $statements = null, ?SchemaName $schemaName = null ): void {
		$this->subjectRepository->updateSubject( TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Original Label' ),
			schemaName: $schemaName ?? new SchemaName( self::SCHEMA_NAME ),
			statements: $statements,
		) );
	}

	private function getStoredSubject(): Subject {
		return $this->subjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );
	}

	/**
	 * @return array<int, mixed>
	 */
	private function getStoredValue( string $propertyName ): array {
		return (array)$this->getStoredSubject()
			->getStatements()
			->getStatement( new PropertyName( $propertyName ) )
			?->getValue()
			->toScalars();
	}

	private function setStatement(
		string $propertyName,
		?string $propertyType,
		mixed $value,
		?string $comment = null
	): void {
		$this->newAction()->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( $propertyName ),
			$propertyType,
			$value,
			$comment
		);
	}

	public function testSetStatementStoresTheValue(): void {
		$this->storeSubject();

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertSame( [ 'https://pro.wiki' ], $this->getStoredValue( 'Website' ) );
	}

	public function testSetStatementReplacesTheValueOfAnExistingStatement(): void {
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Website', value: 'https://old.example', propertyType: 'url' ),
		] ) );

		$this->setStatement( 'Website', 'url', [ 'https://new.example' ] );

		$this->assertSame( [ 'https://new.example' ], $this->getStoredValue( 'Website' ) );
	}

	public function testSetStatementLeavesTheOtherStatementsAlone(): void {
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Before', value: 'kept before' ),
			TestStatement::build( property: 'Target', value: 'replaced' ),
			TestStatement::build( property: 'After', value: 'kept after' ),
		] ) );

		$this->setStatement( 'Target', 'text', [ 'new' ] );

		$this->assertSame( [ 'kept before' ], $this->getStoredValue( 'Before' ) );
		$this->assertSame( [ 'kept after' ], $this->getStoredValue( 'After' ) );
	}

	public function testSetStatementLeavesTheLabelAlone(): void {
		$this->storeSubject();

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertSame( 'Original Label', $this->getStoredSubject()->getLabel()->text );
	}

	public function testSetStatementNamesALabellessMainSubjectAfterItsPage(): void {
		$this->subjectRepository->savePageSubjects(
			new PageSubjects( $this->labellessSubject(), new SubjectMap() ),
			new PageId( 7 )
		);

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertNull( $this->presenterSpy->subject?->label );
		$this->assertSame( 'Help:Test page', $this->presenterSpy->subject?->displayName );
	}

	public function testSetStatementNamesALabellessChildSubjectAfterItsSchema(): void {
		$this->subjectRepository->savePageSubjects(
			new PageSubjects( null, new SubjectMap( $this->labellessSubject() ) ),
			new PageId( 7 )
		);

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertSame( self::SCHEMA_NAME, $this->presenterSpy->subject?->displayName );
	}

	private function labellessSubject(): Subject {
		return TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: null,
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
	}

	public function testPropertyTypeFallsBackToTheSchemaType(): void {
		$this->registerSchema( new PropertyDefinitions( [ 'Website' => TestProperty::buildUrl() ] ) );
		$this->storeSubject();

		$this->setStatement( 'Website', null, [ 'https://pro.wiki' ] );

		$this->assertSame(
			'url',
			$this->getStoredSubject()->getStatements()->getStatement( new PropertyName( 'Website' ) )->getPropertyType()
		);
	}

	public function testGivenPropertyTypeWinsOverTheSchemaType(): void {
		$this->registerSchema( new PropertyDefinitions( [ 'Website' => TestProperty::buildUrl() ] ) );
		$this->storeSubject();

		$this->setStatement( 'Website', 'text', [ 'not a url' ] );

		$this->assertSame(
			'text',
			$this->getStoredSubject()->getStatements()->getStatement( new PropertyName( 'Website' ) )->getPropertyType()
		);
	}

	public function testOmittedPropertyTypeThrowsWhenTheSchemaDoesNotDefineTheProperty(): void {
		$this->registerSchema( new PropertyDefinitions( [ 'Website' => TestProperty::buildUrl() ] ) );
		$this->storeSubject();

		$action = $this->newAction();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unlisted' );

		$action->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Unlisted' ),
			null,
			[ 'a value' ],
			null
		);
	}

	public function testSetStatementWithEmptyValueRemovesTheStatement(): void {
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Website', value: 'https://pro.wiki', propertyType: 'url' ),
		] ) );

		$this->setStatement( 'Website', 'url', [] );

		$this->assertNull(
			$this->getStoredSubject()->getStatements()->getStatement( new PropertyName( 'Website' ) )
		);
	}

	public function testSelectValueIsResolvedToItsOptionId(): void {
		$this->registerSchema( new PropertyDefinitions( [
			'Status' => new SelectProperty(
				core: new PropertyCore( description: '', required: false, default: null ),
				options: [
					new SelectOption( id: 'opt_draft', label: 'Draft' ),
					new SelectOption( id: 'opt_approved', label: 'Approved' ),
				],
				multiple: false,
			),
		] ) );
		$this->storeSubject();

		$this->setStatement( 'Status', 'select', 'Approved' );

		$this->assertSame( [ 'opt_approved' ], $this->getStoredValue( 'Status' ) );
	}

	public function testUnresolvableSelectValueThrows(): void {
		$this->registerSchema( new PropertyDefinitions( [
			'Status' => new SelectProperty(
				core: new PropertyCore( description: '', required: false, default: null ),
				options: [ new SelectOption( id: 'opt_draft', label: 'Draft' ) ],
				multiple: false,
			),
		] ) );
		$this->storeSubject();

		$action = $this->newAction();

		$this->expectException( InvalidArgumentException::class );

		$action->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Status' ),
			'select',
			'No such option',
			null
		);
	}

	public function testCommentIsForwarded(): void {
		$this->storeSubject();

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ], 'My edit summary' );

		$this->assertSame( 'My edit summary', $this->subjectRepository->comments[self::SUBJECT_ID] );
	}

	public function testSetStatementAuthorizesAgainstTheSubjectsResolvedPage(): void {
		$spy = new SpySubjectWriteAuthorizer( allowed: true );
		$this->storeSubject();

		$this->newAction( $spy )->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Website' ),
			'url',
			[ 'https://pro.wiki' ],
			null
		);

		$this->assertEquals( new PageId( 7 ), $spy->authorizedPageId );
	}

	public function testUnauthorizedSetThrows(): void {
		$this->storeSubject();

		$action = $this->newAction( new SpySubjectWriteAuthorizer( allowed: false ) );

		$this->expectException( SubjectEditNotAuthorizedException::class );

		$action->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Website' ),
			'url',
			[ 'https://pro.wiki' ],
			null
		);
	}

	public function testSetOnNonExistentSubjectThrows(): void {
		$action = $this->newAction();

		$this->expectException( SubjectNotFoundException::class );

		$action->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Website' ),
			'url',
			[ 'https://pro.wiki' ],
			null
		);
	}

	public function testUnreadablePageAnswersNotFound(): void {
		// The caller may edit the page but may not read it. Answering anything other than not-found
		// would confirm the Subject exists, and hand out the page title and namespace with it.
		$this->storeSubject();

		$action = $this->newAction( readAuthorizer: new StubPageReadAuthorizer( allowed: false ) );

		$this->expectException( SubjectNotFoundException::class );
		$this->expectExceptionMessage( 'Subject not found: ' . self::SUBJECT_ID );

		$action->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Website' ),
			'url',
			[ 'https://pro.wiki' ],
			null
		);
	}

	public function testUnreadablePageIsRejectedBeforeTheWrite(): void {
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Website', value: 'https://pro.wiki', propertyType: 'url' ),
		] ) );

		try {
			$this->newAction( readAuthorizer: new StubPageReadAuthorizer( allowed: false ) )->setStatement(
				new SubjectId( self::SUBJECT_ID ),
				new PropertyName( 'Website' ),
				'url',
				[ 'https://overwritten.example' ],
				null
			);
		} catch ( SubjectNotFoundException ) {
		}

		$this->assertSame( [ 'https://pro.wiki' ], $this->getStoredValue( 'Website' ) );
	}

	public function testRemoveOnAnUnreadablePageAnswersNotFound(): void {
		$this->storeSubject();

		$action = $this->newAction( readAuthorizer: new StubPageReadAuthorizer( allowed: false ) );

		$this->expectException( SubjectNotFoundException::class );

		$action->removeStatement( new SubjectId( self::SUBJECT_ID ), new PropertyName( 'Website' ), null );
	}

	public function testPresentedSubjectCarriesThePersistedStatements(): void {
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Kept', value: 'kept' ),
		] ) );

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertSame(
			[
				'Kept' => [ 'propertyType' => 'text', 'value' => [ 'kept' ] ],
				'Website' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ],
			],
			$this->presenterSpy->subject?->statements
		);
	}

	public function testPresentedSubjectCarriesThePageItSitsOn(): void {
		$this->storeSubject();

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertSame( 7, $this->presenterSpy->subject?->pageId );
		$this->assertSame( 'Help:Test page', $this->presenterSpy->subject?->pageTitle );
		$this->assertSame( 12, $this->presenterSpy->subject?->pageNamespaceId );
	}

	public function testPresentedSchemaIsTheOneTheSubjectInstantiates(): void {
		$this->registerSchema( new PropertyDefinitions( [ 'Website' => TestProperty::buildUrl() ] ) );
		$this->storeSubject();

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertSame( self::SCHEMA_NAME, $this->presenterSpy->schema?->getName()->getText() );
	}

	public function testNoSchemaIsPresentedWhenTheSubjectNamesAMissingOne(): void {
		$this->storeSubject( schemaName: new SchemaName( 'NonexistentSchema' ) );

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertNull( $this->presenterSpy->schema );
	}

	public function testRemoveStatementDeletesOnlyTheNamedStatement(): void {
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Before' ),
			TestStatement::build( property: 'Target' ),
			TestStatement::build( property: 'After' ),
		] ) );

		$this->newAction()->removeStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Target' ),
			null
		);

		$this->assertSame(
			[ 'Before', 'After' ],
			array_keys( $this->getStoredSubject()->getStatements()->asArray() )
		);
	}

	public function testRemoveStatementOfAbsentPropertyLeavesTheStatementsUnchanged(): void {
		$this->storeSubject( new StatementList( [ TestStatement::build( property: 'Kept' ) ] ) );

		$this->newAction()->removeStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Absent' ),
			null
		);

		$this->assertSame( [ 'Kept' ], array_keys( $this->getStoredSubject()->getStatements()->asArray() ) );
	}

	public function testUnauthorizedRemoveThrows(): void {
		$this->storeSubject();

		$action = $this->newAction( new SpySubjectWriteAuthorizer( allowed: false ) );

		$this->expectException( SubjectEditNotAuthorizedException::class );

		$action->removeStatement( new SubjectId( self::SUBJECT_ID ), new PropertyName( 'Website' ), null );
	}

	public function testRemoveOnNonExistentSubjectThrows(): void {
		$action = $this->newAction();

		$this->expectException( SubjectNotFoundException::class );

		$action->removeStatement( new SubjectId( self::SUBJECT_ID ), new PropertyName( 'Website' ), null );
	}

	public function testViolationsOfUntouchedPropertiesArePresented(): void {
		$this->registerSchema( new PropertyDefinitions( [
			'Website' => TestProperty::buildUrl(),
			'Required' => TestProperty::buildText( required: true ),
		] ) );
		$this->storeSubject();

		$this->setStatement( 'Website', 'url', [ 'https://pro.wiki' ] );

		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'required', $this->presenterSpy->violations[0]->code );
		$this->assertSame( self::SUBJECT_ID, $this->presenterSpy->subjectId );
	}

	public function testEnforcementOnRejectsAStatementThatIntroducesAViolation(): void {
		$this->registerSchema( new PropertyDefinitions( [ 'Website' => TestProperty::buildUrl() ] ) );
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Website', value: 'https://pro.wiki', propertyType: 'url' ),
		] ) );

		$this->newAction( validationEnforced: true )->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Website' ),
			'url',
			[ 'not-a-url' ],
			null
		);

		$this->assertTrue( $this->presenterSpy->validationFailed );
		$this->assertSame( [ 'https://pro.wiki' ], $this->getStoredValue( 'Website' ) );
	}

	public function testEnforcementOnRejectsRemovalOfARequiredStatement(): void {
		$this->registerSchema( new PropertyDefinitions( [ 'Required' => $this->newRequiredTextProperty() ] ) );
		$this->storeSubject( new StatementList( [
			TestStatement::build( property: 'Required', value: 'present' ),
		] ) );

		$this->newAction( validationEnforced: true )->removeStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Required' ),
			null
		);

		$this->assertTrue( $this->presenterSpy->validationFailed );
		$this->assertSame( [ 'present' ], $this->getStoredValue( 'Required' ) );
	}

	public function testEnforcementOnAllowsAnEditThatOnlyKeepsPreExistingViolations(): void {
		$this->registerSchema( new PropertyDefinitions( [
			'Website' => TestProperty::buildUrl(),
			'Required' => $this->newRequiredTextProperty(),
		] ) );
		$this->storeSubject();

		$this->newAction( validationEnforced: true )->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Website' ),
			'url',
			[ 'https://pro.wiki' ],
			null
		);

		$this->assertFalse( $this->presenterSpy->validationFailed );
		$this->assertSame( [ 'https://pro.wiki' ], $this->getStoredValue( 'Website' ) );
	}

	public function testMissingSchemaIsReportedAsANonBlockingViolation(): void {
		$this->storeSubject( schemaName: new SchemaName( 'NonexistentSchema' ) );

		$this->newAction( validationEnforced: true )->setStatement(
			new SubjectId( self::SUBJECT_ID ),
			new PropertyName( 'Website' ),
			'url',
			[ 'https://pro.wiki' ],
			null
		);

		$this->assertFalse( $this->presenterSpy->validationFailed );
		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'schema-not-found', $this->presenterSpy->violations[0]->code );
		$this->assertSame( [ 'https://pro.wiki' ], $this->getStoredValue( 'Website' ) );
	}

}
