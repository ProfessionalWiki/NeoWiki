<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiPageDeleter;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiPageDeleter
 * @group Database
 */
class MediaWikiPageDeleterTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markPageTableAsUsed();
	}

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

	private function newDeleter(): MediaWikiPageDeleter {
		return new MediaWikiPageDeleter(
			$this->getServiceContainer()->getDeletePageFactory(),
			$this->getTestSysop()->getUser(),
		);
	}

}
