<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\TestGuidGenerator;
use RuntimeException;

class CreateSubjectActionTest extends TestCase {

	private const GUID = '00000000-8888-0000-0000-000000000002';
	private CreateSubjectPresenter $mockPresenter;

	public function setUp(): void {
		$this->inMemorySubjectRepository = new InMemorySubjectRepository();
		$this->guidGenerator = new TestGuidGenerator( self::GUID );
		$this->mockPresenter = $this->createMock( CreateSubjectPresenter::class );
	}

	private function newCreateSubjectAction(): CreateSubjectAction {
		return new CreateSubjectAction(
			$this->mockPresenter,
			$this->inMemorySubjectRepository,
			$this->guidGenerator,
			new SucceedingSubjectActionAuthorizer()
		);
	}

	public function testCreateMainSubject(): void {
		$this->inMemorySubjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( 1 ) );

		$this->mockPresenter->expects( $this->once() )->method( 'presentCreated' )->with( self::GUID );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaId: 'some-schema-id',
				properties: []
			)
		);
	}

	public function testSubjectAlreadyExists(): void {
		$pageSubjects = $this->createMock( PageSubjects::class );
		$pageSubjects->method( 'createMainSubject' )->willThrowException(
			new RuntimeException( 'Subject already exists' )
		);
		$this->inMemorySubjectRepository->savePageSubjects( $pageSubjects, new PageId( 1 ) );

		$this->mockPresenter->expects( $this->once() )->method( 'presentSubjectAlreadyExists' );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Existing Label',
				schemaId: 'existing-schema-id',
				properties: []
			)
		);
	}

	public function testUserIsAllowedToCreateSubject(): void {
		$subjectAuthorizer = new SucceedingSubjectActionAuthorizer();
		$this->assertTrue( $subjectAuthorizer->canCreateChildSubject() );
		$this->assertTrue( $subjectAuthorizer->canCreateMainSubject() );
	}

	public function testUserIsNotAllowedToCreateSubject(): void {
		$subjectAuthorizer = new FailingSubjectActionAuthorizer();

		$createSubjectAction = new CreateSubjectAction(
			$this->mockPresenter,
			$this->inMemorySubjectRepository,
			$this->guidGenerator,
			$subjectAuthorizer
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to create this subject' );

		$createSubjectAction->createSubject(
			new CreateSubjectRequest(
				pageId: 1,
				isMainSubject: true,
				label: 'Some Label',
				schemaId: 'some-schema-id',
				properties: []
			)
		);
	}

	public function testNewRelationGetsCreated(): void {
		$this->inMemorySubjectRepository->updateSubject( TestSubject::build() );

		$this->newCreateSubjectAction()->createSubject(
			new CreateSubjectRequest(
				pageId: 145345,
				isMainSubject: true,
				label: 'Some Label',
				schemaId: '00000000-8888-0000-0000-000000000022',
				properties: [ 'Has product' => [ [ 'target' => '00000000-5555-0000-0000-000000000099' ] ] ]
			)
		);

		$this->assertSame(
			self::GUID,
			$this->inMemorySubjectRepository
				->getSubjectsByPageId( new PageId( 145345 ) )
				->getMainSubject()
				->getId()
				->text,
			'Relation ID is incorrect'
		);
	}

	public function testIsRelationValueWithEmptyArray(): void {
		$createSubjectAction = $this->newCreateSubjectAction();
		$emptyArray = [];

		$this->assertFalse(
			$createSubjectAction->isRelationValue( $emptyArray ),
			'isRelationValue should return false when passed an empty array.'
		);
	}

}
