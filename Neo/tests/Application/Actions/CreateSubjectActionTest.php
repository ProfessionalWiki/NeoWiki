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
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\TestGuidGenerator;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction
 */
class CreateSubjectActionTest extends TestCase {

	private const GUID = '00000000-8888-0000-0000-000000000002';

	private InMemorySubjectRepository $subjectRepository;
	private GuidGenerator $guidGenerator;
	private CreateSubjectPresenterSpy $presenterSpy;
	private SubjectActionAuthorizer $authorizer;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->guidGenerator = new TestGuidGenerator( self::GUID );
		$this->presenterSpy = new CreateSubjectPresenterSpy();
		$this->authorizer = new SucceedingSubjectActionAuthorizer();
	}

	private function newCreateSubjectAction(): CreateSubjectAction {
		return new CreateSubjectAction(
			$this->presenterSpy,
			$this->subjectRepository,
			$this->guidGenerator,
			$this->authorizer,
			new StatementListPatcher(
				NeoWikiExtension::getInstance()->getFormatTypeLookup(),
				$this->guidGenerator
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
			self::GUID,
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
		$this->authorizer = new FailingSubjectActionAuthorizer();

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
						'format' => 'relation',
						'value' => [
							[
								// No ID
								'target' => '00000000-5555-0000-0000-000000000098'
							],
							[
								'id' => '00000000-5555-0000-0000-000000000199', // Existing ID
								'target' => '00000000-5555-0000-0000-000000000099'
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
							id: self::GUID, // Generated ID
							targetId: '00000000-5555-0000-0000-000000000098'
						),
						TestRelation::build(
							id: '00000000-5555-0000-0000-000000000199',
							targetId: '00000000-5555-0000-0000-000000000099'
						)
					),
					format: 'relation'
				)
			] ),
			$newSubject->getStatements()
		);
	}

}
