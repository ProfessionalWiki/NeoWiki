<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\FormatTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction
 */
class CreateSubjectActionTest extends TestCase {

	private const string STUB_ID = 'EVNrDCjgVpv9oC';

	private InMemorySubjectRepository $subjectRepository;
	private IdGenerator $idGenerator;
	private CreateSubjectPresenterSpy $presenterSpy;
	private SubjectAuthorizer $authorizer;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->idGenerator = new StubIdGenerator( self::STUB_ID );
		$this->presenterSpy = new CreateSubjectPresenterSpy();
		$this->authorizer = new SucceedingSubjectAuthorizer();
	}

	private function newCreateSubjectAction(): CreateSubjectAction {
		return new CreateSubjectAction(
			$this->presenterSpy,
			$this->subjectRepository,
			$this->idGenerator,
			$this->authorizer,
			new StatementListPatcher(
				new FormatTypeLookup( ValueFormatRegistry::withCoreFormats() ),
				$this->idGenerator
			)
		);
	}

	public function testCreateMainSubject(): void {
		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaId: 'some-schema-id',
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
				schemaId: 'existing-schema-id',
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
				schemaId: 'some-schema-id',
				statements: []
			)
		);
	}

	public function testNewRelationGetsGuidAssigned(): void {
		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 145345,
				isMainSubject: true,
				label: 'Some Label',
				schemaId: '00000000-8888-0000-0000-000000000022',
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
					format: 'relation'
				)
			] ),
			$newSubject->getStatements()
		);
	}

}
