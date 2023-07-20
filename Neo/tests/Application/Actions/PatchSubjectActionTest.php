<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\TestGuidGenerator;

class PatchSubjectActionTest extends TestCase {

	private const GUID = '00000000-7777-0000-0000-000000000055';

	public function setUp(): void {
		$this->inMemorySubjectRepository = new InMemorySubjectRepository();
		$this->guidGenerator = new TestGuidGenerator( self::GUID );
	}

	private function newPatchSubjectAction( SubjectActionAuthorizer $authorizer ): PatchSubjectAction {
		return new PatchSubjectAction(
			$this->inMemorySubjectRepository,
			$authorizer,
			$this->guidGenerator
		);
	}

	public function testPatchSubjectWithPermission(): void {
		$subject = TestSubject::build( id: new SubjectId( self::GUID ) );
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction( new SucceedingSubjectActionAuthorizer() );
		$patchSubjectAction->patch( $subject->getId(), [] );

		$patchedSubject = $this->inMemorySubjectRepository->getSubject( new SubjectId( self::GUID ) );

		$this->assertSame(
			self::GUID,
			$patchedSubject->getId()->text,
			'Subject ID does not match the expected GUID after patching'
		);
	}

	public function testPatchSubjectWithoutPermission(): void {
		$patchSubjectAction = $this->newPatchSubjectAction( new FailingSubjectActionAuthorizer() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to edit this subject' );

		$patchSubjectAction->patch( new SubjectId( self::GUID ), [] );
	}

	public function testPatchNonExistentSubject(): void {
		$patchSubjectAction = $this->newPatchSubjectAction( new SucceedingSubjectActionAuthorizer() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Subject not found: ' . self::GUID );

		$patchSubjectAction->patch( new SubjectId( self::GUID ), [] );
	}

	public function testNewRelationGetsGuid(): void {
		$this->inMemorySubjectRepository->updateSubject( TestSubject::build() );

		$this->newPatchSubjectAction( new SucceedingSubjectActionAuthorizer() )
			->patch(
				$initialSubjectId = TestSubject::build()->getId(),
				[ 'Has product' => [ [ 'target' => '00000000-5555-0000-0000-000000000099' ] ] ]
			);

		$relation = $this->inMemorySubjectRepository
			->getSubject( $initialSubjectId )
			->getStatements()
			->asMap()['Has product'][0]['id'];

		$this->assertSame(
			self::GUID,
			$relation,
			'Relation ID does not match expected GUID'
		);
	}

	public function testFailingToSetRelationDueToMissingTarget(): void {
		$mockSubjectRepository = $this->createMock( SubjectRepository::class );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( "Subject not found: " . self::GUID );

		( new PatchSubjectAction(
			$mockSubjectRepository,
			new SucceedingSubjectActionAuthorizer(),
			new TestGuidGenerator( self::GUID )
		) )->patch(
			new SubjectId( self::GUID ),
			[
				'Founded at' => 2005,
				'Websites' => [
					'https://acme.example'
				],
				'Main product' => [],
				'Products' => [],
				'World domination progress' => 42
			]
		);
	}

}
