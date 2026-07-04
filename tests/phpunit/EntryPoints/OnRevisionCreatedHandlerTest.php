<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentityValue;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler
 * @group Database
 */
class OnRevisionCreatedHandlerTest extends NeoWikiIntegrationTestCase {

	private SpyGraphDatabasePlugin $graphStore;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
		$this->graphStore = new SpyGraphDatabasePlugin();
	}

	public function testReturnsTrueAndSavesPageWhenSubjectSlotPresent(): void {
		$revision = $this->createPageWithSubjects( 'Page with subject', TestSubject::build() );

		$wrote = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertTrue( $wrote );
		$this->assertCount( 1, $this->graphStore->savedPages );
	}

	public function testReturnsFalseAndDoesNotSaveWhenSubjectSlotMissing(): void {
		$pageId = $this->insertPage( 'Plain page', 'Just wikitext, no subjects.' )['id'];
		$revision = $this->getServiceContainer()->getRevisionStore()->getRevisionByPageId( $pageId );
		$this->assertInstanceOf( RevisionRecord::class, $revision );

		$wrote = $this->newHandler()->onRevisionCreated( $revision, new UserIdentityValue( 1, 'Tester' ) );

		$this->assertFalse( $wrote );
		$this->assertSame( [], $this->graphStore->savedPages );
	}

	public function testOnPageUndeleteReprojectsRestoredPage(): void {
		$revision = $this->createPageWithSubjects( 'Restored page', TestSubject::build() );

		$this->newHandler()->onPageUndelete( $revision );

		$this->assertCount( 1, $this->graphStore->savedPages );
	}

	private function newHandler(): OnRevisionCreatedHandler {
		$services = $this->getServiceContainer();

		return new OnRevisionCreatedHandler(
			$this->graphStore,
			new PagePropertiesBuilder(
				revisionStore: $services->getRevisionStore(),
				contentHandlerFactory: $services->getContentHandlerFactory(),
				titleFormatter: $services->getTitleFormatter(),
				providerRegistry: new PagePropertyProviderRegistry(),
			)
		);
	}

}
