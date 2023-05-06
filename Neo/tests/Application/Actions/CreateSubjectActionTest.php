<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions\CreateSubject;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;

class CreateSubjectActionTest extends TestCase {
	private function newStubGuidGenerator( string $guid ): GuidGenerator {
		$stubGuidGenerator = $this->createMock( GuidGenerator::class );
		$stubGuidGenerator->method( 'generate' )->willReturn( $guid );
		return $stubGuidGenerator;
	}

	public function testCreateMainSubject(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$mockGuidGenerator = $this->newStubGuidGenerator( 'some-guid' );

		$mockSubjectRepository->method( 'getSubjectsByPageId' )->willReturn( PageSubjects::newEmpty() );
		$mockSubjectRepository->method( 'savePageSubjects' );

		$mockPresenter = $this->createMock( CreateSubjectPresenter::class );
		$mockPresenter->expects( $this->once() )->method( 'presentCreated' )->with( 'some-guid' );

		$request = new CreateSubjectRequest(
			pageId: 1,
			isMainSubject: true,
			label: 'Some Label',
			schemaId: 'some-schema-id',
			properties: []
		);

		$action = new CreateSubjectAction( $mockPresenter, $mockSubjectRepository, $mockGuidGenerator );
		$action->createSubject( $request );
	}

	public function testCreateChildSubject(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$mockGuidGenerator = $this->newStubGuidGenerator( 'child-guid' );

		$mockSubjectRepository->method( 'getSubjectsByPageId' )->willReturn( $this->createMock( PageSubjects::class ) );
		$mockSubjectRepository->method( 'savePageSubjects' );

		$mockPresenter = $this->createMock( CreateSubjectPresenter::class );
		$mockPresenter->expects( $this->once() )->method( 'presentCreated' )->with( 'child-guid' );

		$request = new CreateSubjectRequest(
			pageId: 1,
			isMainSubject: false,
			label: 'Child Label',
			schemaId: 'child-schema-id',
			properties: []
		);

		$action = new CreateSubjectAction( $mockPresenter, $mockSubjectRepository, $mockGuidGenerator );
		$action->createSubject( $request );
	}

	public function testSubjectAlreadyExists(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$mockGuidGenerator = $this->newStubGuidGenerator( 'some-guid' );

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

		$action = new CreateSubjectAction( $mockPresenter, $mockSubjectRepository, $mockGuidGenerator );
		$action->createSubject( $request );
	}
}
