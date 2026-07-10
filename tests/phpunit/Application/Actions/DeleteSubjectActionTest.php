<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction
 */
class DeleteSubjectActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111126';

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

	public function testAuthorizesAgainstTheSubjectsResolvedPage(): void {
		$authorizer = new SpySubjectWriteAuthorizer( allowed: true );
		$action = new DeleteSubjectAction(
			$this->newRepositoryWithSubject(),
			$authorizer,
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 7 ), 'Owning page', 0 ) ]
			] )
		);

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );

		$this->assertEquals( new PageId( 7 ), $authorizer->authorizedPageId );
	}

	public function testThrowsWhenUserMayNotDeleteSubject(): void {
		$action = new DeleteSubjectAction(
			new InMemorySubjectRepository(),
			new SpySubjectWriteAuthorizer( allowed: false ),
			$this->pageIdentifiersLookupWithSubject()
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
		return new DeleteSubjectAction(
			$repository,
			new SpySubjectWriteAuthorizer( allowed: true ),
			$this->pageIdentifiersLookupWithSubject()
		);
	}

	private function pageIdentifiersLookupWithSubject(): InMemoryPageIdentifiersLookup {
		return new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 1 ), 'Test page', 0 ) ]
		] );
	}

}
