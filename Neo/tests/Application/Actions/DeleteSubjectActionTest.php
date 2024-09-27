<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectAuthorizer;

class DeleteSubjectActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111126';

	public function testDeleteSubject(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$subjectAuthorizer = new SucceedingSubjectAuthorizer();

		$mockSubjectRepository->expects( $this->once() )->method( 'deleteSubject' )->with( new SubjectId( self::SUBJECT_ID ) );

		$action = new DeleteSubjectAction( $mockSubjectRepository, $subjectAuthorizer );
		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ) );
	}

	public function testUserIsNotAllowedToDeleteSubject(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$subjectAuthorizer = new FailingSubjectAuthorizer();

		$action = new DeleteSubjectAction( $mockSubjectRepository, $subjectAuthorizer );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to delete this subject' );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ) );
	}
}
