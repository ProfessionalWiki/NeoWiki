<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\FormatTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction
 */
class PatchSubjectActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111127';

	private InMemorySubjectRepository $inMemorySubjectRepository;
	private IdGenerator $idGenerator;

	public function setUp(): void {
		$this->inMemorySubjectRepository = new InMemorySubjectRepository();
		$this->idGenerator = new StubIdGenerator( '11111111111127' );
	}

	private function newPatchSubjectAction( SubjectAuthorizer $authorizer = null ): PatchSubjectAction {
		return new PatchSubjectAction(
			$this->inMemorySubjectRepository,
			$authorizer ?? new SucceedingSubjectAuthorizer(),
			new StatementListPatcher(
				formatTypeLookup: new FormatTypeLookup( ValueFormatRegistry::withCoreFormats() ),
				idGenerator: $this->idGenerator
			)
		);
	}

	public function testPatchSubjectWithPermission(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction();
		$patchSubjectAction->patch( $subject->getId(), null, [] );

		$patchedSubject = $this->inMemorySubjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertSame(
			self::SUBJECT_ID,
			$patchedSubject->getId()->text,
			'Subject ID does not match the expected GUID after patching'
		);
	}

	public function testPatchSubjectWithComment(): void {
		$subject = TestSubject::build( id: new SubjectId( self::SUBJECT_ID ) );
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction();
		$patchSubjectAction->patch( $subject->getId(), null, [], 'Edit comment' );

		$this->assertSame(
			'Edit comment',
			$this->inMemorySubjectRepository->comments[self::SUBJECT_ID],
			'Subject comment was not passed correctly'
		);
	}

	public function testPatchSubjectLabel(): void {
		$subject = TestSubject::build(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Original Label' )
		);
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction();
		$patchSubjectAction->patch(
			$subject->getId(),
			'Updated Label',
			[]
		);

		$patchedSubject = $this->inMemorySubjectRepository->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertSame(
			'Updated Label',
			$patchedSubject->getLabel()->text,
			'Subject label was not updated correctly'
		);
	}

	public function testPatchSubjectWithoutPermission(): void {
		$patchSubjectAction = $this->newPatchSubjectAction( new FailingSubjectAuthorizer() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to edit this subject' );

		$patchSubjectAction->patch( new SubjectId( self::SUBJECT_ID ), null, [] );
	}

	public function testPatchNonExistentSubject(): void {
		$patchSubjectAction = $this->newPatchSubjectAction();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Subject not found: ' . self::SUBJECT_ID );

		$patchSubjectAction->patch( new SubjectId( self::SUBJECT_ID ), null, [] );
	}

	public function testNewRelationGetsGuid(): void {
		$this->inMemorySubjectRepository->updateSubject( TestSubject::build() );

		$initialSubjectId = TestSubject::build()->getId();

		$this->newPatchSubjectAction()
			->patch(
				$initialSubjectId,
				null,
				[
					'Has product' => [
						'propertyType' => 'relation',
						'value' => [ [ 'target' => self::SUBJECT_ID ] ]
					]
				]
			);

		/**
		 * @var RelationValue $relation
		 */
		$relation = $this->inMemorySubjectRepository
			->getSubject( $initialSubjectId )
			->getStatements()->getStatement( new PropertyName( 'Has product' ) )->getValue();

		$this->assertSame(
			'r11111111111127',
			$relation->relations[0]->id->asString(),
			'Relation ID does not match expected GUID'
		);
	}

}
