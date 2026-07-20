<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\FirstRevisionAuthorPageTitlesLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use User;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\FirstRevisionAuthorPageTitlesLookup
 * @group Database
 */
class FirstRevisionAuthorPageTitlesLookupTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markPageTableAsUsed();
	}

	public function testReturnsAPageTheImporterCreated(): void {
		$this->editAs( $this->importer(), 'Importer page', 'created by the importer' );

		$this->assertSame( [ 'Importer page' ], $this->importedTitleTexts() );
	}

	public function testExcludesPagesCreatedByAnotherUser(): void {
		$this->editAs( $this->otherUser(), 'Third party page', 'created by another user' );
		$this->editAs( $this->importer(), 'Importer page', 'created by the importer' );

		$this->assertSame( [ 'Importer page' ], $this->importedTitleTexts() );
	}

	public function testExcludesPagesTheImporterOnlyEditedLater(): void {
		$this->editAs( $this->otherUser(), 'Community page', 'first revision by another user' );
		$this->editAs( $this->importer(), 'Community page', 'importer edits it afterwards' );
		$this->editAs( $this->importer(), 'Importer page', 'created by the importer' );

		$this->assertSame( [ 'Importer page' ], $this->importedTitleTexts() );
	}

	public function testExcludesPagesThatNoLongerExist(): void {
		$this->editAs( $this->importer(), 'Temporary page', 'created by the importer' );
		$this->editAs( $this->importer(), 'Surviving page', 'also created by the importer' );
		$this->deletePageByName( 'Temporary page' );

		$this->assertSame( [ 'Surviving page' ], $this->importedTitleTexts() );
	}

	public function testReturnsNothingForAnImporterThatHasAuthoredNoPages(): void {
		$this->editAs( $this->otherUser(), 'Someone elses page', 'created by another user' );

		$ghostImporter = UserIdentityValue::newRegistered( 424242, 'Ghost importer' );

		$this->assertSame( [], $this->newLookup( $ghostImporter )->getImportedPageTitles() );
	}

	/**
	 * @return string[]
	 */
	private function importedTitleTexts(): array {
		return array_map(
			static fn ( Title $title ): string => $title->getPrefixedText(),
			$this->newLookup( $this->importer() )->getImportedPageTitles()
		);
	}

	private function newLookup( UserIdentity $importer ): FirstRevisionAuthorPageTitlesLookup {
		$services = $this->getServiceContainer();

		return new FirstRevisionAuthorPageTitlesLookup(
			db: $this->getDb(),
			actorNormalization: $services->getActorNormalization(),
			revisionLookup: $services->getRevisionLookup(),
			titleFactory: $services->getTitleFactory(),
			importer: $importer,
		);
	}

	private function importer(): User {
		return User::newSystemUser( 'NeoWikiImportTest', [ 'steal' => true ] );
	}

	private function otherUser(): User {
		return $this->getTestUser()->getUser();
	}

	private function editAs( Authority $performer, string $pageName, string $text ): void {
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );

		$updater = $wikiPage->newPageUpdater( $performer );
		$updater->setContent( 'main', new WikitextContent( $text ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'test edit' ) );
	}

	private function deletePageByName( string $pageName ): void {
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );

		$this->getServiceContainer()->getDeletePageFactory()
			->newDeletePage( $page, $this->getTestSysop()->getUser() )
			->deleteUnsafe( 'test cleanup' );
	}

}
