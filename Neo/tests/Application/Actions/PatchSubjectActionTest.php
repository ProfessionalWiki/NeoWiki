<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\TestGuidGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction
 */
class PatchSubjectActionTest extends TestCase {

	private const GUID = '00000000-7777-0000-0000-000000000055';

	private InMemorySubjectRepository $inMemorySubjectRepository;
	private GuidGenerator $guidGenerator;

	public function setUp(): void {
		$this->inMemorySubjectRepository = new InMemorySubjectRepository();
		$this->guidGenerator = new TestGuidGenerator( self::GUID );
	}

	private function newPatchSubjectAction( SubjectActionAuthorizer $authorizer = null ): PatchSubjectAction {
		return new PatchSubjectAction(
			$this->inMemorySubjectRepository,
			$authorizer ?? new SucceedingSubjectActionAuthorizer(),
			new StatementListPatcher(
				formatTypeLookup: NeoWikiExtension::getInstance()->getFormatTypeLookup(),
				guidGenerator: $this->guidGenerator
			)
		);
	}

	public function testPatchSubjectWithPermission(): void {
		$subject = TestSubject::build( id: new SubjectId( self::GUID ) );
		$this->inMemorySubjectRepository->updateSubject( $subject );

		$patchSubjectAction = $this->newPatchSubjectAction();
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
		$patchSubjectAction = $this->newPatchSubjectAction();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Subject not found: ' . self::GUID );

		$patchSubjectAction->patch( new SubjectId( self::GUID ), [] );
	}

	public function testNewRelationGetsGuid(): void {
		$this->inMemorySubjectRepository->updateSubject( TestSubject::build() );

		$initialSubjectId = TestSubject::build()->getId();

		$this->newPatchSubjectAction()
			->patch(
				$initialSubjectId,
				[
					'Has product' => [
						'format' => 'relation',
						'value' => [ [ 'target' => '00000000-5555-0000-0000-000000000099' ] ]
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
			self::GUID,
			$relation->relations[0]->id->asString(),
			'Relation ID does not match expected GUID'
		);
	}

}
