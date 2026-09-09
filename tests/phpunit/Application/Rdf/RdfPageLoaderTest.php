<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSlots;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\NullRevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageLoader;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageLoader
 * @group Database
 */
class RdfPageLoaderTest extends NeoWikiIntegrationTestCase {

	public function testPageWhoseSubjectSlotDoesNotHoldSubjectContentIsNotLoaded(): void {
		// The write path refuses to touch such a page, since it cannot tell which Subjects it holds. The
		// export must not answer for it either: describing it as a page without Subjects would state
		// something about the page that NeoWiki does not know. Reachable when the content model that
		// wrote the slot is no longer installed.
		$loader = $this->newLoaderFor( $this->newRevisionWithSubjectSlotContent() );

		$this->assertNull( $loader->loadByTitle( Title::makeTitle( NS_MAIN, 'Page with an unreadable slot' ) ) );
	}

	public function testMissingPageIsNotLoaded(): void {
		$loader = $this->newLoaderFor( null );

		$this->assertNull( $loader->loadByTitle( Title::makeTitle( NS_MAIN, 'Page that does not exist' ) ) );
	}

	public function testExportsTheRevisionTheRevisionPolicyPublishes(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$approved = $this->createPageWithSubjects( 'Exported from its approved revision', TestSubject::build() );
		$draft = $this->createPageWithSubjects( 'Exported from its approved revision' );

		$page = $this->newLoaderFor( $draft, FixedRevisionPolicy::publishing( $approved ) )
			->loadByTitle( Title::makeTitle( NS_MAIN, 'Exported from its approved revision' ) );

		$this->assertNotNull( $page );
		$this->assertTrue(
			$page->getSubjects()->hasSubjects(),
			'the approved revision holds a Subject the draft removed'
		);
	}

	public function testPageWithNoPublishableRevisionIsNotLoaded(): void {
		// The revision must be one that would otherwise load, or the null proves nothing: a slot that
		// does not hold Subject data already yields null through the pass-through path.
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$revision = $this->createPageWithSubjects( 'Page with nothing published', TestSubject::build() );

		$loader = $this->newLoaderFor( $revision, FixedRevisionPolicy::publishingNothing() );

		$this->assertNull( $loader->loadByTitle( Title::makeTitle( NS_MAIN, 'Page with nothing published' ) ) );
		$this->assertNotNull(
			$this->newLoaderFor( $revision )->loadByTitle( Title::makeTitle( NS_MAIN, 'Page with nothing published' ) ),
			'the same revision loads when the policy publishes it'
		);
	}

	private function newRevisionWithSubjectSlotContent(): RevisionRecord {
		$slots = $this->createStub( RevisionSlots::class );
		$slots->method( 'getContent' )->willReturn( new WikitextContent( 'Not Subject data.' ) );

		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getPageId' )->willReturn( 42 );
		$revision->method( 'hasSlot' )
			->willReturnCallback( static fn ( string $role ): bool => $role === MediaWikiSubjectRepository::SLOT_NAME );
		$revision->method( 'getSlots' )->willReturn( $slots );

		return $revision;
	}

	private function newLoaderFor( ?RevisionRecord $revision, ?RevisionPolicy $policy = null ): RdfPageLoader {
		$page = $this->createStub( WikiPage::class );
		$page->method( 'getRevisionRecord' )->willReturn( $revision );

		$factory = $this->createStub( WikiPageFactory::class );
		$factory->method( 'newFromTitle' )->willReturn( $page );

		return new RdfPageLoader(
			$factory,
			NeoWikiExtension::getInstance()->getPagePropertiesBuilder(),
			$policy ?? new NullRevisionPolicy()
		);
	}

}
