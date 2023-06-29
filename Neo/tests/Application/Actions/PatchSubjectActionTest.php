<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;

class PatchSubjectActionTest extends TestCase {
	private const GUID = '00000000-7777-0000-0000-000000000001';

	public function testPatchSubjectWithPermission(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$subjectAuthorizer = new SucceedingSubjectActionAuthorizer();

		$mockSubject = $this->createMock( Subject::class );
		$mockSubject->expects( $this->once() )->method( 'applyPatch' );
		$mockSubjectRepository->method( 'getSubject' )->willReturn( $mockSubject );
		$mockSubjectRepository->expects( $this->once() )->method( 'updateSubject' );

		$patchSubjectAction = new PatchSubjectAction(
			$mockSubjectRepository,
			$subjectAuthorizer
		);

		$patchSubjectAction->patch( new SubjectId( self::GUID ), [] );
	}

	public function testPatchSubjectWithoutPermission(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$subjectAuthorizer = new FailingSubjectActionAuthorizer();

		$patchSubjectAction = new PatchSubjectAction(
			$mockSubjectRepository,
			$subjectAuthorizer
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to edit this subject' );

		$patchSubjectAction->patch( new SubjectId( self::GUID ), [] );
	}

	public function testPatchNonExistentSubject(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );
		$subjectAuthorizer = new SucceedingSubjectActionAuthorizer();

		$mockSubjectRepository->method( 'getSubject' )->willReturn( null );

		$patchSubjectAction = new PatchSubjectAction(
			$mockSubjectRepository,
			$subjectAuthorizer
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Subject not found: ' . self::GUID );

		$patchSubjectAction->patch( new SubjectId( self::GUID ), [] );
	}
}
