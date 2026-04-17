<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\Application\SelectPatchResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectAuthorizer;
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
	private SubjectAuthorizer $authorizer;
	private InMemorySchemaLookup $schemaLookup;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->idGenerator = new StubIdGenerator( self::STUB_ID );
		$this->presenterSpy = new CreateSubjectPresenterSpy();
		$this->authorizer = new SucceedingSubjectAuthorizer();
		$this->schemaLookup = new InMemorySchemaLookup();
	}

	private function newCreateSubjectAction(): CreateSubjectAction {
		return new CreateSubjectAction(
			$this->presenterSpy,
			$this->subjectRepository,
			$this->idGenerator,
			$this->authorizer,
			new StatementListPatcher(
				new PropertyTypeToValueType( PropertyTypeRegistry::withCoreTypes() ),
				$this->idGenerator
			),
			$this->schemaLookup,
			new SelectPatchResolver( new SelectValueResolver() ),
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
	}

	public function testUserIsNotAllowedToCreateSubject(): void {
		$this->authorizer = new FailingSubjectAuthorizer();

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

}
