<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiPageDeleter;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiPageDeleter
 * @group Database
 */
class MediaWikiPageDeleterTest extends NeoWikiIntegrationTestCase {

	public function testDeletesAnExistingPage(): void {
		$title = $this->insertPage( 'Page to delete', 'content' )['title'];

		$status = $this->newDeleter()->deletePage( $title->toPageIdentity(), 'no longer needed' );

		$this->assertTrue( $status->succeeded );
		$this->assertFalse(
			$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title )->exists(),
			'the page should be gone after deletion'
		);
	}

	public function testReportsFailureWhenThePageCannotBeDeleted(): void {
		$missingPage = Title::newFromText( 'Never created' )->toPageIdentity();

		$status = $this->newDeleter()->deletePage( $missingPage, 'no longer needed' );

		$this->assertFalse( $status->succeeded );
		$this->assertNotNull( $status->errorMessage );
	}

	public function testDeletesAPageWithALongHistoryRightAway(): void {
		$this->overrideConfigValue( MainConfigNames::DeleteRevisionsBatchSize, 1 );
		$title = $this->insertPage( 'Page with history', 'first' )['title'];
		$this->editPage( $title, 'second' );

		$this->newDeleter()->deletePage( $title->toPageIdentity(), 'no longer needed' );

		$this->assertFalse( $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title )->exists() );
	}

	public function testReportsFailureWhenTheDeletionThrows(): void {
		$title = $this->insertPage( 'Page that fails to delete', 'content' )['title'];
		$this->setTemporaryHook( 'PageDelete', static fn () => throw new RuntimeException( 'Deletion failed' ) );

		$status = $this->newDeleter()->deletePage( $title->toPageIdentity(), 'no longer needed' );

		$this->assertSame( 'Deletion failed', $status->errorMessage );
	}

	private function newDeleter(): MediaWikiPageDeleter {
		return new MediaWikiPageDeleter(
			$this->getServiceContainer()->getDeletePageFactory(),
			$this->getTestSysop()->getUser(),
		);
	}

}
