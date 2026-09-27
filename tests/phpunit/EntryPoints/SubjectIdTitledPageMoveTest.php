<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the database for the pages whose Subjects decide whether they can be moved.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onGetUserPermissionsErrors
 * @group Database
 */
class SubjectIdTitledPageMoveTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 's1zz1111111azzb';
	private const string OTHER_ID = 's1zz1111111azzc';
	private const string SUBJECT_NAMESPACE_PAGE = 'Subject:' . self::SUBJECT_ID;
	private const string MOVE_REFUSED = 'neowiki-subject-page-immovable';

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testASubjectsOwnPageCannotBeMovedOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$status = $this->moveStatusOf( $this->createPageHoldingTheSubject( self::SUBJECT_NAMESPACE_PAGE ) );

		$this->assertStatusError( self::MOVE_REFUSED, $status );
	}

	public function testASubjectsOwnPageIsNotOfferedForMovingOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$title = $this->createPageHoldingTheSubject( self::SUBJECT_NAMESPACE_PAGE );

		$this->assertFalse( $this->getTestSysop()->getAuthority()->probablyCan( 'move', $title ) );
	}

	public function testASubjectsOwnPageCanStillBeEditedOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$title = $this->createPageHoldingTheSubject( self::SUBJECT_NAMESPACE_PAGE );

		$this->assertTrue( $this->getTestSysop()->getAuthority()->probablyCan( 'edit', $title ) );
	}

	public function testAPageHoldingSubjectsUnderAChosenTitleCanBeMovedOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$this->assertStatusGood( $this->moveStatusOf( $this->createPageHoldingTheSubject( 'Subject:Ada Lovelace' ) ) );
	}

	public function testAPageInTheSubjectNamespaceTitledByAnIdItDoesNotHoldCanBeMovedOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$this->assertStatusGood( $this->moveStatusOf( $this->createPageHoldingTheSubject( 'Subject:' . self::OTHER_ID ) ) );
	}

	public function testAMainNamespacePageTitledByItsSubjectsIdCannotBeMovedOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$status = $this->moveStatusOf( $this->createPageHoldingTheSubject( self::SUBJECT_ID ) );

		$this->assertStatusError( self::MOVE_REFUSED, $status );
	}

	public function testAPageTitledByItsSubjectsIdCanBeMovedOnAPageFirstWiki(): void {
		$this->assertStatusGood( $this->moveStatusOf( $this->createPageHoldingTheSubject( self::SUBJECT_ID ) ) );
	}

	private function createPageHoldingTheSubject( string $pageName ): Title {
		$this->createPageWithSubjects( $pageName, TestSubject::build( id: self::SUBJECT_ID, label: 'Ada Lovelace' ) );

		return Title::newFromText( $pageName );
	}

	private function moveStatusOf( Title $title ): PermissionStatus {
		return $this->getServiceContainer()->getMovePageFactory()
			->newMovePage( $title, Title::newFromText( 'Moved page' ) )
			->authorizeMove( $this->getTestSysop()->getAuthority() );
	}

}
