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
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectAction
 */
class ReplaceSubjectActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111127';
	private const string SCHEMA_NAME = 'TestSchema';

	private InMemorySubjectRepository $subjectRepository;
	private InMemorySchemaLookup $schemaLookup;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->schemaLookup = new InMemorySchemaLookup();
	}

	private function newAction( ?SubjectAuthorizer $authorizer = null ): ReplaceSubjectAction {
		$builder = new StatementListBuilder(
			propertyTypeToValueType: new PropertyTypeToValueType( PropertyTypeRegistry::withCoreTypes() ),
			idGenerator: new StubIdGenerator( '11111111111127' )
		);
		return new ReplaceSubjectAction(
			subjectRepository: $this->subjectRepository,
			subjectAuthorizer: $authorizer ?? new SucceedingSubjectAuthorizer(),
			statementListBuilder: $builder,
			schemaLookup: $this->schemaLookup,
			selectStatementResolver: new SelectStatementResolver( new SelectValueResolver() ),
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

	public function testUnauthorizedThrows(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->subjectRepository->updateSubject( $subject );

		$action = $this->newAction( new FailingSubjectAuthorizer() );

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

}
