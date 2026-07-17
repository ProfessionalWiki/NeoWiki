<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction
 */
class CreateSubjectActionTest extends TestCase {

	private const string STUB_ID = 'EVNrDCjgVpv9oC';
	private const string SELECT_SCHEMA_NAME = 'StatusSchema';

	private InMemorySubjectRepository $subjectRepository;
	private IdGenerator $idGenerator;
	private CreateSubjectPresenterSpy $presenterSpy;
	private SubjectWriteAuthorizer $authorizer;
	private InMemorySchemaLookup $schemaLookup;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->idGenerator = new StubIdGenerator( self::STUB_ID );
		$this->presenterSpy = new CreateSubjectPresenterSpy();
		$this->authorizer = new SpySubjectWriteAuthorizer( allowed: true );
		$this->schemaLookup = new InMemorySchemaLookup();
	}

	private function newCreateSubjectAction( bool $validationEnforced = false ): CreateSubjectAction {
		$registry = PropertyTypeRegistry::withCoreTypes();
		return new CreateSubjectAction(
			$this->presenterSpy,
			$this->subjectRepository,
			$this->idGenerator,
			$this->authorizer,
			new StatementListBuilder(
				$registry,
				$this->idGenerator,
				TestData::newSubjectIdParser()
			),
			$this->schemaLookup,
			new SelectStatementResolver( new SelectValueResolver() ),
			new ProposedSubjectValidator(
				schemaLookup: $this->schemaLookup,
				subjectValidator: new SubjectValidator( propertyTypeLookup: $registry ),
			),
			$validationEnforced,
		);
	}

	private function registerSelectSchema( bool $multiple = false ): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SELECT_SCHEMA_NAME ),
			description: '',
			properties: new PropertyDefinitions( [
				'Status' => new SelectProperty(
					core: new PropertyCore( description: '', required: false, default: null ),
					options: [
						new SelectOption( id: 'opt_draft', label: 'Draft' ),
						new SelectOption( id: 'opt_approved', label: 'Approved' ),
					],
					multiple: $multiple,
				),
			] )
		) );
	}

	private function getStatusValueForCreatedSubject(): StringValue {
		$statement = $this->subjectRepository
			->getSubject( new SubjectId( $this->presenterSpy->result ) )
			->getStatements()
			->getStatement( new PropertyName( 'Status' ) );

		/** @var StringValue $value */
		$value = $statement->getValue();

		return $value;
	}

	public function testCreateMainSubject(): void {
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: 'some-schema-id',
				statements: []
			)
		);

		$this->assertSame(
			's' . self::STUB_ID,
			$this->presenterSpy->result
		);
	}

	public function testSubjectAlreadyExists(): void {
		$pageSubjects = $this->createMock( PageSubjects::class );
		$pageSubjects->method( 'createMainSubject' )->willThrowException(
			new RuntimeException( 'Subject already exists' )
		);
		$this->subjectRepository->savePageSubjects( $pageSubjects, new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Existing Label',
				schemaName: 'existing-schema-id',
				statements: []
			)
		);

		$this->assertSame(
			'presentSubjectAlreadyExists',
			$this->presenterSpy->result
		);
		$this->assertSame( [], $this->presenterSpy->violations );
	}

	public function testUserIsNotAllowedToCreateSubject(): void {
		$this->authorizer = new SpySubjectWriteAuthorizer( allowed: false );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to create this subject' );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: 'some-schema-id',
				statements: []
			)
		);
	}

	public function testCommentIsPassedToRepository(): void {
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: 'some-schema-id',
				statements: [],
				comment: 'My custom comment'
			)
		);

		$this->assertSame( 'My custom comment', $this->subjectRepository->comments[1] );
	}

	public function testNullCommentIsPassedToRepositoryByDefault(): void {
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: 'some-schema-id',
				statements: []
			)
		);

		$this->assertNull( $this->subjectRepository->comments[1] );
	}

	public function testSelectValueAcceptsOptionId(): void {
		$this->registerSelectSchema();

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [
					'Status' => [ 'propertyType' => 'select', 'value' => 'opt_draft' ],
				]
			)
		);

		$this->assertSame( [ 'opt_draft' ], $this->getStatusValueForCreatedSubject()->strings );
	}

	public function testSelectValueResolvesLabelToId(): void {
		$this->registerSelectSchema();

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [
					'Status' => [ 'propertyType' => 'select', 'value' => '  approved  ' ],
				]
			)
		);

		$this->assertSame( [ 'opt_approved' ], $this->getStatusValueForCreatedSubject()->strings );
	}

	public function testSelectValueAcceptsConsistentIdLabelObject(): void {
		$this->registerSelectSchema();

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [
					'Status' => [
						'propertyType' => 'select',
						'value' => [ 'id' => 'opt_approved', 'label' => 'Approved' ],
					],
				]
			)
		);

		$this->assertSame( [ 'opt_approved' ], $this->getStatusValueForCreatedSubject()->strings );
	}

	public function testSelectValueRejectsInconsistentIdLabelObject(): void {
		$this->registerSelectSchema();

		$this->expectException( \InvalidArgumentException::class );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [
					'Status' => [
						'propertyType' => 'select',
						'value' => [ 'id' => 'opt_draft', 'label' => 'WrongName' ],
					],
				]
			)
		);
	}

	public function testMultiSelectValueResolvesMixedForms(): void {
		$this->registerSelectSchema( multiple: true );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [
					'Status' => [
						'propertyType' => 'select',
						'value' => [
							'opt_draft',
							'Approved',
							[ 'id' => 'opt_draft', 'label' => 'Draft' ],
						],
					],
				]
			)
		);

		$this->assertSame(
			[ 'opt_draft', 'opt_approved', 'opt_draft' ],
			$this->getStatusValueForCreatedSubject()->strings
		);
	}

	public function testSelectValuePassesThroughWhenSchemaIsMissing(): void {
		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: 'UnknownSchema',
				statements: [
					'Status' => [ 'propertyType' => 'select', 'value' => 'opt_draft' ],
				]
			)
		);

		$this->assertSame( [ 'opt_draft' ], $this->getStatusValueForCreatedSubject()->strings );
	}

	private function registerSchemaWithRequiredStatus(): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( self::SELECT_SCHEMA_NAME ),
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

	public function testCreateProducesEmptyViolationsForCleanInput(): void {
		$this->registerSelectSchema();
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [],
			)
		);

		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertSame( [], $this->presenterSpy->violations );
	}

	public function testCreateProducesViolationForRequiredPropertyMissing(): void {
		$this->registerSchemaWithRequiredStatus();
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [],
			)
		);

		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'required', $this->presenterSpy->violations[0]->code );
		$this->assertSame( 'Status', $this->presenterSpy->violations[0]->propertyName?->text );
	}

	public function testCreateWithMissingSchemaSurfacesSchemaNotFoundButStillPersists(): void {
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: 'NonexistentSchema',
				statements: [],
			)
		);

		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'schema-not-found', $this->presenterSpy->violations[0]->code );
		$this->assertSame( [ 'NonexistentSchema' ], $this->presenterSpy->violations[0]->args );
	}

	public function testCreateWithViolationsStillPersistsTheSubject(): void {
		$this->registerSchemaWithRequiredStatus();
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: self::SELECT_SCHEMA_NAME,
				statements: [],
			)
		);

		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertNotEmpty(
			$this->subjectRepository->getSubject( new SubjectId( 's' . self::STUB_ID ) )
		);
	}

	public function testNewRelationGetsGuidAssigned(): void {
		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 145345,
				isMainSubject: true,
				label: 'Some Label',
				schemaName: '00000000-8888-0000-0000-000000000022',
				statements: [
					'Has product' => [
						'propertyType' => 'relation',
						'value' => [
							[
								// No ID
								'target' => 's11111111111111'
							],
							[
								'id' => 'rzzzzzzzzzzzzzz', // Existing ID
								'target' => 's11111111111112'
							]
						]
					]
				]
			)
		);

		$newSubject = $this->subjectRepository->getSubject( new SubjectId( $this->presenterSpy->result ) );

		$this->assertEquals(
			new StatementList( [
				TestStatement::build(
					property: 'Has product',
					value: new RelationValue(
						TestRelation::build(
							id: 'r' . self::STUB_ID, // Generated ID
							targetId: 's11111111111111'
						),
						TestRelation::build(
							id: 'rzzzzzzzzzzzzzz',
							targetId: 's11111111111112'
						)
					),
					propertyType: 'relation'
				)
			] ),
			$newSubject->getStatements()
		);
	}

	private function registerPersonSchemaWithRequiredName(): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( 'PersonSchema' ),
			description: '',
			properties: new PropertyDefinitions( [
				'Name' => new SelectProperty(
					core: new PropertyCore( description: '', required: true, default: null ),
					options: [ new SelectOption( id: 'opt_alice', label: 'Alice' ) ],
					multiple: false,
				),
			] )
		) );
	}

	private function registerPersonSchemaWithRequiredUnregisteredProperty(): void {
		$this->schemaLookup->updateSchema( new Schema(
			name: new SchemaName( 'PersonSchema' ),
			description: '',
			properties: new PropertyDefinitions( [
				'Swatch' => UnregisteredTypeProperty::fromPartialJson(
					new PropertyCore( description: '', required: true, default: null ),
					[ 'type' => 'color' ],
				),
			] )
		) );
	}

	/**
	 * The extension owning the type is gone, so no value can be supplied for the required
	 * property. Blocking the create would make the Schema unusable until it returns.
	 */
	public function testEnforcementOnAllowsCreateWhenARequiredPropertyHasAnUnregisteredType(): void {
		$this->registerPersonSchemaWithRequiredUnregisteredProperty();
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction( validationEnforced: true )->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Bob',
				schemaName: 'PersonSchema',
				statements: [],
			)
		);

		$this->assertFalse( $this->presenterSpy->validationFailed );
		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'unregistered-type', $this->presenterSpy->violations[0]->code );
	}

	public function testEnforcementOffPersistsCreateWithViolations(): void {
		$this->registerPersonSchemaWithRequiredName();
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction( validationEnforced: false )->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Bob',
				schemaName: 'PersonSchema',
				statements: [],
			)
		);

		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertFalse( $this->presenterSpy->validationFailed );
	}

	public function testEnforcementOnRejectsCreateWithViolations(): void {
		$this->registerPersonSchemaWithRequiredName();
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction( validationEnforced: true )->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Bob',
				schemaName: 'PersonSchema',
				statements: [],
			)
		);

		$this->assertTrue( $this->presenterSpy->validationFailed );
		$this->assertSame( '', $this->presenterSpy->result );
		$this->assertNull( $this->subjectRepository->getSubject( new SubjectId( 's' . self::STUB_ID ) ) );
	}

	public function testEnforcementOnAllowsCleanCreate(): void {
		$this->registerPersonSchemaWithRequiredName();
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction( validationEnforced: true )->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Bob',
				schemaName: 'PersonSchema',
				statements: [
					'Name' => [ 'propertyType' => 'select', 'value' => 'opt_alice' ],
				],
			)
		);

		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertFalse( $this->presenterSpy->validationFailed );
	}

	public function testEnforcementOnAllowsCreateWhenSchemaIsMissing(): void {
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction( validationEnforced: true )->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Subject',
				schemaName: 'NonexistentSchema',
				statements: [],
			)
		);

		$this->assertSame( 's' . self::STUB_ID, $this->presenterSpy->result );
		$this->assertFalse( $this->presenterSpy->validationFailed );
		$this->assertCount( 1, $this->presenterSpy->violations );
		$this->assertSame( 'schema-not-found', $this->presenterSpy->violations[0]->code );
	}

	public function testEnforcementOnDoesNotInterceptAlreadyExistsBranch(): void {
		$this->registerPersonSchemaWithRequiredName();
		$pageSubjects = $this->createMock( PageSubjects::class );
		$pageSubjects->method( 'createMainSubject' )->willThrowException(
			new RuntimeException( 'Subject already exists' )
		);
		$this->subjectRepository->savePageSubjects( $pageSubjects, new PageId( 1 ) );

		$this->newCreateSubjectAction( validationEnforced: true )->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Existing Label',
				schemaName: 'PersonSchema',
				statements: [],
			)
		);

		$this->assertSame( 'presentSubjectAlreadyExists', $this->presenterSpy->result );
		$this->assertFalse( $this->presenterSpy->validationFailed );
	}

}
