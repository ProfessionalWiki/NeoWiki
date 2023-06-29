<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;
use RuntimeException;

class CreateSubjectActionTest extends TestCase {
	private const GUID = '00000000-7777-0000-0000-000000000001';

	private function newStubGuidGenerator( string $guid ): GuidGenerator {
		$stubGuidGenerator = $this->createMock( GuidGenerator::class );
		$stubGuidGenerator->method( 'generate' )->willReturn( $guid );
		return $stubGuidGenerator;
	}

	public function testCreateMainSubject(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$mockGuidGenerator = $this->newStubGuidGenerator( self::GUID );
		$subjectAuthorizer = new SucceedingSubjectActionAuthorizer();

		$mockSubjectRepository->method( 'getSubjectsByPageId' )->willReturn( PageSubjects::newEmpty() );
		$mockSubjectRepository->method( 'savePageSubjects' );

		$mockPresenter = $this->createMock( CreateSubjectPresenter::class );
		$mockPresenter->expects( $this->once() )->method( 'presentCreated' )->with( self::GUID );

		$request = new CreateSubjectRequest(
			pageId: 1,
			isMainSubject: true,
			label: 'Some Label',
			schemaId: 'some-schema-id',
			properties: []
		);

		$action = new CreateSubjectAction(
			$mockPresenter,
			$mockSubjectRepository,
			$mockGuidGenerator,
			$subjectAuthorizer
		);
		$action->createSubject( $request );
	}

	public function testCreateChildSubject(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$mockGuidGenerator = $this->newStubGuidGenerator( self::GUID );
		$subjectAuthorizer = new SucceedingSubjectActionAuthorizer();

		$mockSubjectRepository->method( 'getSubjectsByPageId' )->willReturn( $this->createMock( PageSubjects::class ) );
		$mockSubjectRepository->method( 'savePageSubjects' );

		$mockPresenter = $this->createMock( CreateSubjectPresenter::class );
		$mockPresenter->expects( $this->once() )->method( 'presentCreated' )->with( self::GUID );

		$request = new CreateSubjectRequest(
			pageId: 1,
			isMainSubject: false,
			label: 'Child Label',
			schemaId: 'child-schema-id',
			properties: []
		);

		$action = new CreateSubjectAction(
			$mockPresenter,
			$mockSubjectRepository,
			$mockGuidGenerator,
			$subjectAuthorizer
		);
		$action->createSubject( $request );
	}

	public function testSubjectAlreadyExists(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$mockGuidGenerator = $this->newStubGuidGenerator( self::GUID );
		$subjectAuthorizer = new SucceedingSubjectActionAuthorizer();

		$pageSubjects = $this->createMock( PageSubjects::class );
		$pageSubjects->method( 'createMainSubject' )->willThrowException( new RuntimeException( 'Subject already exists' ) );
		$mockSubjectRepository->method( 'getSubjectsByPageId' )->willReturn( $pageSubjects );

		$mockPresenter = $this->createMock( CreateSubjectPresenter::class );
		$mockPresenter->expects( $this->once() )->method( 'presentSubjectAlreadyExists' );

		$request = new CreateSubjectRequest(
			pageId: 1,
			isMainSubject: true,
			label: 'Existing Label',
			schemaId: 'existing-schema-id',
			properties: []
		);

		$action = new CreateSubjectAction(
			$mockPresenter,
			$mockSubjectRepository,
			$mockGuidGenerator,
			$subjectAuthorizer
		);
		$action->createSubject( $request );
	}

	public function testUserIsAllowedToCreateSubject(): void {
		$subjectAuthorizer = new SucceedingSubjectActionAuthorizer();

		$this->assertTrue( $subjectAuthorizer->canCreateChildSubject() );
		$this->assertTrue( $subjectAuthorizer->canCreateMainSubject() );
	}

	public function testUserIsNotAllowedToCreateSubject(): void {
		$subjectAuthorizer = new FailingSubjectActionAuthorizer();

		$createSubjectAction = new CreateSubjectAction(
			$this->createMock( CreateSubjectPresenter::class ),
			$this->createMock( SubjectRepository::class ),
			$this->createMock( GuidGenerator::class ),
			$subjectAuthorizer
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to create this subject' );

		$createSubjectAction->createSubject( new CreateSubjectRequest(
			pageId: 1,
			isMainSubject: true,
			label: 'Some Label',
			schemaId: 'some-schema-id',
			properties: []
		) );
	}

}
