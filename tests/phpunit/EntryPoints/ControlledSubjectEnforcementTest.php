<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use StatusValue;

/**
 * Goes through MediaWiki's own write paths, which is where the rules for Controlled Subjects are held.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ControlledSubjectHooks
 * @covers \ProfessionalWiki\NeoWiki\Application\ControlledSubjectRules
 * @group Database
 */
class ControlledSubjectEnforcementTest extends NeoWikiIntegrationTestCase {

	private const string TERM = 'sCSEWoman11111a';
	private const string TERM_PAGE = 'Woman';
	private const string CONTROLLED_SCHEMA_JSON = '{"subjectsAreControlled":true,"propertyDefinitions":{}}';

	protected function setUp(): void {
		parent::setUp();

		$this->assertNotNull( $this->createSchema( 'Gender', self::CONTROLLED_SCHEMA_JSON ) );
		$this->setGroupPermissions( [
			'deleter' => [ 'delete' => true, 'undelete' => true, 'rollback' => true ],
		] );
	}

	private function term( ?string $label = 'Woman' ): Subject {
		return TestSubject::build( id: self::TERM, label: $label, schemaName: TestSchema::reference( 'Gender' ) );
	}

	private function userWithoutTheRight(): User {
		return $this->getTestUser( [ 'deleter' ] )->getUser();
	}

	private function saveSlot( UserIdentity $user, string $pageName, string $role, Content $content ): StatusValue {
		$updater = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageName ) )
			->newPageUpdater( $user );

		if ( $role !== SlotRecord::MAIN && !Title::newFromText( $pageName )->exists() ) {
			$updater->setContent( SlotRecord::MAIN, new TextContent( '' ) );
		}

		$updater->setContent( $role, $content );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'test' ) );

		return $updater->getStatus();
	}

	private function saveSubjects( UserIdentity $user, string $pageName, Subject ...$subjects ): StatusValue {
		return $this->saveSlot(
			$user,
			$pageName,
			MediaWikiSubjectRepository::SLOT_NAME,
			SubjectContent::newFromData( new PageSubjects( null, new SubjectMap( ...$subjects ) ) )
		);
	}

	private function saveSchema( UserIdentity $user, string $schemaName, string $json ): StatusValue {
		return $this->saveSlot( $user, $this->schemaPageName( $schemaName ), SlotRecord::MAIN, new SchemaContent( $json ) );
	}

	private function deleteAs( User $user, string $pageName ): StatusValue {
		return $this->getServiceContainer()->getDeletePageFactory()->newDeletePage(
			$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) ),
			$user
		)->deleteUnsafe( 'test' );
	}

	private function restoreAs( User $user, string $pageName ): StatusValue {
		return $this->getServiceContainer()->getUndeletePageFactory()->newUndeletePage(
			Title::newFromText( $pageName )->toPageIdentity(),
			$user
		)->undeleteUnsafe( 'test' );
	}

	private function moveAs( User $user, string $pageName ): StatusValue {
		return $this->getServiceContainer()->getMovePageFactory()
			->newMovePage( Title::newFromText( $pageName ), Title::newFromText( $pageName . ' (moved)' ) )
			->authorizeMove( $user );
	}

	private function schemaPageName( string $schemaName ): string {
		return Title::makeTitle( NeoWikiExtension::NS_SCHEMA, $schemaName )->getPrefixedText();
	}

	public function testAddingAControlledSubjectWithoutTheRightIsRefused(): void {
		$this->assertStatusError(
			'neowiki-controlled-subject-edit-refused',
			$this->saveSubjects( $this->userWithoutTheRight(), self::TERM_PAGE, $this->term() )
		);
	}

	public function testAddingAControlledSubjectWithTheRightSucceeds(): void {
		$this->assertStatusGood( $this->saveSubjects( $this->getTestSysop()->getUser(), self::TERM_PAGE, $this->term() ) );
	}

	public function testAUserWhoMayNotReadTheSchemaStillMayNotAddItsControlledSubjects(): void {
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( Title $title, User $user, string $action, &$result ): bool {
				if ( $action === 'read' && $title->getNamespace() === NeoWikiExtension::NS_SCHEMA ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}

				return true;
			}
		);

		$this->assertStatusError(
			'neowiki-controlled-subject-edit-refused',
			$this->saveSubjects( $this->userWithoutTheRight(), self::TERM_PAGE, $this->term() )
		);
	}

	public function testTheSystemUserMayAddControlledSubjects(): void {
		$this->assertStatusGood(
			$this->saveSubjects( User::newSystemUser( 'NeoWiki', [ 'steal' => true ] ), self::TERM_PAGE, $this->term() )
		);
	}

	public function testEditingTheTextOfAPageWithControlledSubjectsNeedsNoRight(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term() ) );

		$this->assertStatusGood(
			$this->saveSlot( $this->userWithoutTheRight(), self::TERM_PAGE, SlotRecord::MAIN, new TextContent( 'More text' ) )
		);
	}

	public function testSavingAPageWithControlledSubjectsUnchangedNeedsNoRight(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term() ) );

		$this->assertStatusOK(
			$this->saveSlot( $this->userWithoutTheRight(), self::TERM_PAGE, SlotRecord::MAIN, new TextContent( '' ) )
		);
	}

	public function testRollingBackAChangeOfAControlledSubjectWithoutTheRightIsRefused(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term( 'Woman' ) ) );
		$this->saveSubjects( $this->getMutableTestUser( [ 'sysop' ] )->getUser(), self::TERM_PAGE, $this->term( 'Female' ) );

		$status = $this->getServiceContainer()->getRollbackPageFactory()->newRollbackPage(
			Title::newFromText( self::TERM_PAGE ),
			$this->userWithoutTheRight(),
			$this->getServiceContainer()->getRevisionLookup()
				->getRevisionByTitle( Title::newFromText( self::TERM_PAGE ) )->getUser()
		)->rollback();

		$this->assertStatusError( 'neowiki-controlled-subject-edit-refused', $status );
	}

	public function testDeletingAPageWithControlledSubjectsWithoutTheRightIsRefused(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term() ) );

		$this->assertStatusError(
			'neowiki-controlled-subject-edit-refused',
			$this->deleteAs( $this->userWithoutTheRight(), self::TERM_PAGE )
		);
	}

	public function testRestoringAPageWithControlledSubjectsWithoutTheRightIsRefused(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term() ) );
		$this->deletePageByName( self::TERM_PAGE );

		$this->assertStatusError(
			'neowiki-controlled-subject-edit-refused',
			$this->restoreAs( $this->userWithoutTheRight(), self::TERM_PAGE )
		);
	}

	public function testRestoringAPageWithControlledSubjectsWithTheRightSucceeds(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term() ) );
		$this->deletePageByName( self::TERM_PAGE );

		$this->assertStatusGood( $this->restoreAs( $this->getTestSysop()->getUser(), self::TERM_PAGE ) );
	}

	public function testMovingAPageHoldingAControlledSubjectWithoutALabelWithoutTheRightIsRefused(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term( label: null ) ) );

		$this->assertStatusError(
			'neowiki-controlled-subject-page-immovable',
			$this->moveAs( $this->userWithoutTheRight(), self::TERM_PAGE )
		);
	}

	public function testMovingAPageHoldingAControlledSubjectWithoutALabelWithTheRightSucceeds(): void {
		$this->createPageWithSubjects( self::TERM_PAGE, otherSubjects: new SubjectMap( $this->term( label: null ) ) );

		$this->assertStatusGood( $this->moveAs( $this->getTestSysop()->getUser(), self::TERM_PAGE ) );
	}

	public function testDeletingASchemaWithControlledSubjectsWithoutTheRightIsRefused(): void {
		$this->assertStatusError(
			'neowiki-controlled-declaration-change-refused',
			$this->deleteAs( $this->userWithoutTheRight(), $this->schemaPageName( 'Gender' ) )
		);
	}

	public function testRestoringASchemaWithControlledSubjectsWithoutTheRightIsRefused(): void {
		$this->deletePageByName( $this->schemaPageName( 'Gender' ) );

		$this->assertStatusError(
			'neowiki-controlled-declaration-change-refused',
			$this->restoreAs( $this->userWithoutTheRight(), $this->schemaPageName( 'Gender' ) )
		);
	}

	public function testDeclaringTheSubjectsOfASchemaControlledWithoutTheRightIsRefused(): void {
		$this->createSchema( 'Language', '{"propertyDefinitions":{}}' );

		$this->assertStatusError(
			'neowiki-controlled-declaration-change-refused',
			$this->saveSchema( $this->userWithoutTheRight(), 'Language', self::CONTROLLED_SCHEMA_JSON )
		);
	}

	public function testEditingASchemaWithControlledSubjectsWithoutChangingTheDeclarationNeedsNoRight(): void {
		$this->assertStatusGood( $this->saveSchema(
			$this->userWithoutTheRight(),
			'Gender',
			'{"subjectsAreControlled":true,"description":"Edited","propertyDefinitions":{}}'
		) );
	}

}
