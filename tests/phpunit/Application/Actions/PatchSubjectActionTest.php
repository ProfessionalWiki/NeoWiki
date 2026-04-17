<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Application\SelectPatchResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction
 */
class PatchSubjectActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111127';
	private const string SCHEMA_NAME = 'TestSchema';

	private InMemorySubjectRepository $inMemorySubjectRepository;
	private IdGenerator $idGenerator;
	private InMemorySchemaLookup $schemaLookup;

	public function setUp(): void {
		$this->inMemorySubjectRepository = new InMemorySubjectRepository();
		$this->idGenerator = new StubIdGenerator( '11111111111127' );
		$this->schemaLookup = new InMemorySchemaLookup();
	}

	private function newPatchSubjectAction( SubjectAuthorizer $authorizer = null ): PatchSubjectAction {
		return new PatchSubjectAction(
			$this->inMemorySubjectRepository,
			$authorizer ?? new SucceedingSubjectAuthorizer(),
			new StatementListPatcher(
				propertyTypeToValueType: new PropertyTypeToValueType( PropertyTypeRegistry::withCoreTypes() ),
				idGenerator: $this->idGenerator
			),
			$this->schemaLookup,
			new SelectPatchResolver( new SelectValueResolver() ),
		);
	}

	private function registerSchemaWithSelect( bool $multiple = false ): void {
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
					multiple: $multiple,
				),
			] )
		) );
	}

	private function getStatusValue( SubjectId $subjectId ): StringValue {
		$statement = $this->inMemorySubjectRepository
			->getSubject( $subjectId )
			->getStatements()
			->getStatement( new PropertyName( 'Status' ) );

		/** @var StringValue $value */
		$value = $statement->getValue();

		return $value;
	}

	public function testPatchSubjectWithPermission(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction();
		$patchSubjectAction->patch( $subject->getId(), null, [] );

		$patchedSubject = $this->inMemorySubjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertSame(
			self::SUBJECT_ID,
			$patchedSubject->getId()->text,
			'Subject ID does not match the expected GUID after patching'
		);
	}

	public function testPatchSubjectWithComment(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction();
		$patchSubjectAction->patch( $subject->getId(), null, [], 'Edit comment' );

		$this->assertSame(
			'Edit comment',
			$this->inMemorySubjectRepository->comments[self::SUBJECT_ID],
			'Subject comment was not passed correctly'
		);
	}

	public function testPatchSubjectLabel(): void {
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Original Label' )
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction();
		$patchSubjectAction->patch(
			$subject->getId(),
			'Updated Label',
			[]
		);

		$patchedSubject = $this->inMemorySubjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertSame(
			'Updated Label',
			$patchedSubject->getLabel()->text,
			'Subject label was not updated correctly'
		);
	}

	public function testPatchSubjectWithoutPermission(): void {
		$patchSubjectAction = $this->newPatchSubjectAction( new FailingSubjectAuthorizer() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to edit this subject' );

		$patchSubjectAction->patch( new SubjectId( self::SUBJECT_ID ), null, [] );
	}

	public function testPatchNonExistentSubject(): void {
		$patchSubjectAction = $this->newPatchSubjectAction();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Subject not found: ' . self::SUBJECT_ID );

		$patchSubjectAction->patch( new SubjectId( self::SUBJECT_ID ), null, [] );
	}

	public function testSelectValueAcceptsOptionId(): void {
		$this->registerSchemaWithSelect();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$this->newPatchSubjectAction()->patch(
			$subject->getId(),
			null,
			[
				'Status' => [ 'propertyType' => 'select', 'value' => 'opt_approved' ],
			]
		);

		$this->assertSame( [ 'opt_approved' ], $this->getStatusValue( $subject->getId() )->strings );
	}

	public function testSelectValueResolvesLabelToId(): void {
		$this->registerSchemaWithSelect();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$this->newPatchSubjectAction()->patch(
			$subject->getId(),
			null,
			[
				'Status' => [ 'propertyType' => 'select', 'value' => '  approved  ' ],
			]
		);

		$this->assertSame( [ 'opt_approved' ], $this->getStatusValue( $subject->getId() )->strings );
	}

	public function testSelectValueAcceptsConsistentIdLabelObject(): void {
		$this->registerSchemaWithSelect();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$this->newPatchSubjectAction()->patch(
			$subject->getId(),
			null,
			[
				'Status' => [
					'propertyType' => 'select',
					'value' => [ 'id' => 'opt_draft', 'label' => 'Draft' ],
				],
			]
		);

		$this->assertSame( [ 'opt_draft' ], $this->getStatusValue( $subject->getId() )->strings );
	}

	public function testSelectValueRejectsInconsistentIdLabelObject(): void {
		$this->registerSchemaWithSelect();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$action = $this->newPatchSubjectAction();

		$this->expectException( \InvalidArgumentException::class );

		$action->patch(
			$subject->getId(),
			null,
			[
				'Status' => [
					'propertyType' => 'select',
					'value' => [ 'id' => 'opt_draft', 'label' => 'WrongName' ],
				],
			]
		);
	}

	public function testSelectValueRejectsUnknownValue(): void {
		$this->registerSchemaWithSelect();
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$action = $this->newPatchSubjectAction();

		$this->expectException( \InvalidArgumentException::class );

		$action->patch(
			$subject->getId(),
			null,
			[
				'Status' => [ 'propertyType' => 'select', 'value' => 'Nonexistent' ],
			]
		);
	}

	public function testMultiSelectValueResolvesMixedForms(): void {
		$this->registerSchemaWithSelect( multiple: true );
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( self::SCHEMA_NAME ),
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$this->newPatchSubjectAction()->patch(
			$subject->getId(),
			null,
			[
				'Status' => [
					'propertyType' => 'select',
					'value' => [
						'opt_draft',
						'Approved',
						[ 'id' => 'opt_draft', 'label' => 'Draft' ],
					],
				],
			]
		);

		$this->assertSame(
			[ 'opt_draft', 'opt_approved', 'opt_draft' ],
			$this->getStatusValue( $subject->getId() )->strings
		);
	}

	public function testSelectValuePassesThroughWhenSchemaIsMissing(): void {
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			schemaName: new SchemaName( 'UnknownSchema' ),
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$this->newPatchSubjectAction()->patch(
			$subject->getId(),
			null,
			[
				'Status' => [ 'propertyType' => 'select', 'value' => 'opt_draft' ],
			]
		);

		$this->assertSame( [ 'opt_draft' ], $this->getStatusValue( $subject->getId() )->strings );
	}

	public function testNewRelationGetsGuid(): void {
		$this->inMemorySubjectRepository->updateSubject( TestSubject::build() );

		$initialSubjectId = TestSubject::build()->getId();

		$this->newPatchSubjectAction()
			->patch(
				$initialSubjectId,
				null,
				[
					'Has product' => [
						'propertyType' => 'relation',
						'value' => [ [ 'target' => self::SUBJECT_ID ] ]
					]
				]
			);

		/**
		 * @var RelationValue $relation
		 */
		$relation = $this->inMemorySubjectRepository
			->getSubject( $initialSubjectId )
			->getStatements()->getStatement( new PropertyName( 'Has product' ) )->getValue();

		$this->assertSame(
			'r11111111111127',
			$relation->relations[0]->id->asString(),
			'Relation ID does not match expected GUID'
		);
	}

}
