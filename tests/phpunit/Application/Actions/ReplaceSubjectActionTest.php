<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectAction;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectAction
 */
class ReplaceSubjectActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111127';
	private const string SCHEMA_NAME = 'TestSchema';

	private InMemorySubjectRepository $subjectRepository;
	private InMemorySchemaLookup $schemaLookup;
	private ReplaceSubjectPresenterSpy $presenterSpy;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->schemaLookup = new InMemorySchemaLookup();
		$this->presenterSpy = new ReplaceSubjectPresenterSpy();
	}

	private function newAction(
		?SubjectWriteAuthorizer $authorizer = null,
		bool $validationEnforced = false,
	): ReplaceSubjectAction {
		$registry = PropertyTypeRegistry::withCoreTypes();
		$builder = new StatementListBuilder(
			propertyTypeToValueType: new PropertyTypeToValueType( $registry ),
			idGenerator: new StubIdGenerator( '11111111111127' )
		);
		return new ReplaceSubjectAction(
			subjectRepository: $this->subjectRepository,
			writeAuthorizer: $authorizer ?? new SpySubjectWriteAuthorizer( allowed: true ),
			statementListBuilder: $builder,
			schemaLookup: $this->schemaLookup,
			selectStatementResolver: new SelectStatementResolver( new SelectValueResolver() ),
			proposedSubjectValidator: new ProposedSubjectValidator(
				schemaLookup: $this->schemaLookup,
				subjectValidator: new SubjectValidator( propertyTypeLookup: $registry ),
			),
			presenter: $this->presenterSpy,
			validationEnforced: $validationEnforced,
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 7 ), 'Test page', 0 ) ]
			] ),
		);
	}

	private function registerSchemaWithSelect(): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SCHEMA_NAME ),
			description: '',
			properties: new PropertyDefinitions( [
				'Status' => new SelectProperty(
					core: new PropertyCore( description: '', required: false, default: null ),
					options: [
						new SelectOption( id: 'opt_draft', label: 'Draft' ),
						new SelectOption( id: 'opt_approved', label: 'Approved' ),
					],
					multiple: false,
				),
			] )
		) );
	}

	private function getStatusValue( SubjectId $subjectId ): StringValue {
		$statement = $this->subjectRepository
			->getSubject( $subjectId )
			->getStatements()
			->getStatement( new PropertyName( 'Status' ) );

		/** @var StringValue $value */
		$value = $statement->getValue();

		return $value;
	}

	public function testLabelIsReplaced(): void {
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Original Label' )
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace( new SubjectId( self::SUBJECT_ID ), 'New Label', [], null );

		$this->assertSame(
			'New Label',
			$this->subjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) )->getLabel()->text
		);
	}

	public function testStatementsAreReplaced(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace(
			new SubjectId( self::SUBJECT_ID ),
			'Label',
			[ 'Founded at' => [ 'propertyType' => 'number', 'value' => 2019 ] ],
			null
		);

		$persisted = $this->subjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );
		$statement = $persisted->getStatements()->getStatement( new PropertyName( 'Founded at' ) );

		$this->assertSame( 'number', $statement->getPropertyType() );
	}

	public function testOmittedStatementsAreDeleted(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->subjectRepository->updateSubject( $subject );

		$action = $this->newAction();
		$action->replace(
			new SubjectId( self::SUBJECT_ID ),
			'Label',
			[ 'Keep' => [ 'propertyType' => 'text', 'value' => 'yes' ] ],
			null
		);

		$action->replace( new SubjectId( self::SUBJECT_ID ), 'Label', [], null );

		$persisted = $this->subjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertNull( $persisted->getStatements()->getStatement( new PropertyName( 'Keep' ) ) );
	}

	public function testCommentIsForwarded(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace( new SubjectId( self::SUBJECT_ID ), 'Label', [], 'Edit summary' );

		$this->assertSame( 'Edit summary', $this->subjectRepository->comments[self::SUBJECT_ID] );
	}

	public function testAuthorizesAgainstTheSubjectsResolvedPage(): void {
		$spy = new SpySubjectWriteAuthorizer( allowed: true );
		$this->subjectRepository->updateSubject( TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) ) );

		$this->newAction( $spy )->replace( new SubjectId( self::SUBJECT_ID ), 'Label', [], null );

		$this->assertEquals( new PageId( 7 ), $spy->authorizedPageId );
	}

	public function testUnauthorizedThrows(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->subjectRepository->updateSubject( $subject );

		$action = $this->newAction( new SpySubjectWriteAuthorizer( allowed: false ) );

		$this->expectException( SubjectEditNotAuthorizedException::class );

		$action->replace( new SubjectId( self::SUBJECT_ID ), 'Label', [], null );
	}

	public function testNonExistentSubjectThrows(): void {
		$action = $this->newAction();

		$this->expectException( SubjectNotFoundException::class );
		$this->expectExceptionMessage( 'Subject not found: ' . self::SUBJECT_ID );

		$action->replace( new SubjectId( self::SUBJECT_ID ), 'Label', [], null );
	}

	public function testSelectValueResolvedWhenSchemaPresent(): void {
		$this->registerSchemaWithSelect();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace(
			new SubjectId( self::SUBJECT_ID ),
			'Label',
			[ 'Status' => [ 'propertyType' => 'select', 'value' => 'Approved' ] ],
			null
		);

		$this->assertSame( [ 'opt_approved' ], $this->getStatusValue( new SubjectId( self::SUBJECT_ID ) )->strings );
	}

	public function testSelectValuePassesThroughWhenNoSchemaRegistered(): void {
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( 'UnknownSchema' ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace(
			new SubjectId( self::SUBJECT_ID ),
			'Label',
			[ 'Status' => [ 'propertyType' => 'select', 'value' => 'opt_draft' ] ],
			null
		);

		$this->assertSame( [ 'opt_draft' ], $this->getStatusValue( new SubjectId( self::SUBJECT_ID ) )->strings );
	}

	private function registerSchemaWithRequiredStatus(): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SCHEMA_NAME ),
			description: '',
			properties: new PropertyDefinitions( [
				'Status' => new SelectProperty(
					core: new PropertyCore( description: '', required: true, default: null ),
					options: [
						new SelectOption( id: 'opt_draft', label: 'Draft' ),
						new SelectOption( id: 'opt_approved', label: 'Approved' ),
					],
					multiple: false,
				),
			] )
		) );
	}

	public function testReplacePresentsEmptyViolationsForCleanInput(): void {
		$this->registerSchemaWithSelect();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Original Label' ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace(
			new SubjectId( self::SUBJECT_ID ),
			'New Label',
			[ 'Status' => [ 'propertyType' => 'select', 'value' => 'Approved' ] ],
			null
		);

		$this->assertSame( [], $this->presenterSpy->violations );
		$this->assertSame( self::SUBJECT_ID, $this->presenterSpy->subjectId );
	}

	public function testReplacePresentsViolationForRequiredPropertyMissing(): void {
		$this->registerSchemaWithRequiredStatus();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Before' ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace(
			new SubjectId( self::SUBJECT_ID ),
			'After',
			[],
			null
		);

		$violations = $this->presenterSpy->violations;
		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertSame( 'Status', $violations[0]->propertyName?->text );
	}

	public function testReplaceWithMissingSchemaPresentsSchemaNotFound(): void {
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Orphan' ),
			schemaName: new SchemaName( 'NonexistentSchema' ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace(
			new SubjectId( self::SUBJECT_ID ),
			'Still Orphan',
			[],
			null
		);

		$violations = $this->presenterSpy->violations;
		$this->assertCount( 1, $violations );
		$this->assertSame( 'schema-not-found', $violations[0]->code );
		$this->assertSame( [ 'NonexistentSchema' ], $violations[0]->args );
	}

	public function testReplaceWithViolationsStillPersistsTheSubject(): void {
		$this->registerSchemaWithRequiredStatus();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Before' ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction()->replace( new SubjectId( self::SUBJECT_ID ), 'After', [], null );

		$stored = $this->subjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );
		$this->assertSame( 'After', $stored->getLabel()->text );
	}

	private function registerSchemaWithRequiredAlphaAndBeta(): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SCHEMA_NAME ),
			description: '',
			properties: new PropertyDefinitions( [
				'Alpha' => new SelectProperty(
					core: new PropertyCore( description: '', required: true, default: null ),
					options: [ new SelectOption( id: 'opt_a', label: 'A' ) ],
					multiple: false,
				),
				'Beta' => new SelectProperty(
					core: new PropertyCore( description: '', required: true, default: null ),
					options: [ new SelectOption( id: 'opt_b', label: 'B' ) ],
					multiple: false,
				),
			] )
		) );
	}

	public function testEnforcementOffPersistsEditWithNewViolations(): void {
		$this->registerSchemaWithRequiredAlphaAndBeta();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Original' ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
			statements: new StatementList( [
				TestStatement::build( property: 'Alpha', value: new StringValue( 'opt_a' ), propertyType: 'select' ),
				TestStatement::build( property: 'Beta', value: new StringValue( 'opt_b' ), propertyType: 'select' ),
			] ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction( validationEnforced: false )->replace(
			new SubjectId( self::SUBJECT_ID ),
			'After',
			[],
			null
		);

		$stored = $this->subjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );
		$this->assertSame( 'After', $stored->getLabel()->text );
		$this->assertFalse( $this->presenterSpy->validationFailed );
	}

	public function testEnforcementOnRejectsEditThatIntroducesNewViolation(): void {
		$this->registerSchemaWithRequiredAlphaAndBeta();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Original' ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
			statements: new StatementList( [
				TestStatement::build( property: 'Alpha', value: new StringValue( 'opt_a' ), propertyType: 'select' ),
				TestStatement::build( property: 'Beta', value: new StringValue( 'opt_b' ), propertyType: 'select' ),
			] ),
		);
		$this->subjectRepository->updateSubject( $subject );
		$updatesBeforeAction = $this->subjectRepository->updateSubjectCallCount;

		$this->newAction( validationEnforced: true )->replace(
			new SubjectId( self::SUBJECT_ID ),
			'After',
			[],
			null
		);

		$this->assertTrue( $this->presenterSpy->validationFailed );
		$this->assertSame( $updatesBeforeAction, $this->subjectRepository->updateSubjectCallCount );
	}

	public function testEnforcementOnAllowsEditWithOnlyPreExistingViolations(): void {
		$this->registerSchemaWithRequiredAlphaAndBeta();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Old' ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction( validationEnforced: true )->replace(
			new SubjectId( self::SUBJECT_ID ),
			'New',
			[],
			null
		);

		$this->assertFalse( $this->presenterSpy->validationFailed );
		$this->assertCount( 2, $this->presenterSpy->violations );
		$this->assertSame( 'New', $this->subjectRepository->getSubject(
			new SubjectId( self::SUBJECT_ID )
		)->getLabel()->text );
	}

	public function testEnforcementOnAllowsEditThatReducesViolations(): void {
		$this->registerSchemaWithRequiredAlphaAndBeta();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction( validationEnforced: true )->replace(
			new SubjectId( self::SUBJECT_ID ),
			'After',
			[ 'Alpha' => [ 'propertyType' => 'select', 'value' => 'opt_a' ] ],
			null
		);

		$this->assertFalse( $this->presenterSpy->validationFailed );
		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'Beta', $this->presenterSpy->violations[0]->propertyName?->text );
	}

	public function testEnforcementOnAllowsEditWhenSchemaIsMissing(): void {
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( 'NonexistentSchema' ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction( validationEnforced: true )->replace(
			new SubjectId( self::SUBJECT_ID ),
			'After',
			[],
			null
		);

		$this->assertFalse( $this->presenterSpy->validationFailed );
		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'schema-not-found', $this->presenterSpy->violations[0]->code );
	}

	public function testEnforcementOnRejectsEditThatAddsBadValueAtNewIndex(): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SCHEMA_NAME ),
			description: '',
			properties: new PropertyDefinitions( [
				'Website' => new UrlProperty(
					core: new PropertyCore( description: '', required: false, default: null ),
					multiple: true,
					uniqueItems: false,
				),
			] )
		) );
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
			statements: new StatementList( [
				TestStatement::build(
					property: 'Website',
					value: new StringValue( 'not-a-url' ),
					propertyType: 'url',
				),
			] ),
		);
		$this->subjectRepository->updateSubject( $subject );

		$this->newAction( validationEnforced: true )->replace(
			new SubjectId( self::SUBJECT_ID ),
			'After',
			[ 'Website' => [ 'propertyType' => 'url', 'value' => [ 'not-a-url', 'also-not-a-url' ] ] ],
			null
		);

		$this->assertTrue( $this->presenterSpy->validationFailed );
	}

}
