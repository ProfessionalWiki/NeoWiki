<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction
 */
class DeleteSubjectActionTest extends TestCase {

	private const SUBJECT_ID = 's11111111111126';

	public function testDeleteSubjectRemovesSubjectFromRepository(): void {
		$repository = $this->newRepositoryWithSubject();

		$this->newAction( $repository )->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );

		$this->assertNull( $repository->getSubject( new SubjectId( self::SUBJECT_ID ) ) );
	}

	public function testDeleteSubjectPassesCommentThrough(): void {
		$repository = $this->newRepositoryWithSubject();

		$this->newAction( $repository )->deleteSubject( new SubjectId( self::SUBJECT_ID ), 'Removed by curator' );

		$this->assertSame( 'Removed by curator', $repository->comments[self::SUBJECT_ID] );
	}

	public function testThrowsWhenUserMayNotDeleteSubject(): void {
		$action = new DeleteSubjectAction(
			new InMemorySubjectRepository(),
			new FailingSubjectAuthorizer()
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to delete this subject' );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
	}

	private function newRepositoryWithSubject(): InMemorySubjectRepository {
		$repository = new InMemorySubjectRepository();
		$repository->updateSubject( TestSubject::build( id: self::SUBJECT_ID ) );
		return $repository;
	}

	private function newAction( SubjectRepository $repository ): DeleteSubjectAction {
		return new DeleteSubjectAction( $repository, new SucceedingSubjectAuthorizer() );
	}

}
