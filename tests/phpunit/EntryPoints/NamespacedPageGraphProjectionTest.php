<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * The page node name must carry the full title including the namespace prefix, so that
 * consumers such as the relation value link can build a correct URL for subjects stored
 * on pages outside the main namespace.
 *
 * @covers \ProfessionalWiki\NeoWiki\PagePropertiesBuilder
 * @covers \ProfessionalWiki\NeoWiki\Persistence\CorePagePropertyProvider
 * @group Database
 */
class NamespacedPageGraphProjectionTest extends NeoWikiIntegrationTestCase {

	private const PAGE_NAME = 'Help:Namespaced subject page';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testPageNodeNameIncludesNamespacePrefix(): void {
		$revision = $this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build() );

		$this->assertSame(
			self::PAGE_NAME,
			$this->readPageNodeName( $revision->getPageId() )
		);
	}

	public function testPageNodeNameUsesContentLanguageNamespacePrefix(): void {
		$this->setContentLang( 'de' );

		$revision = $this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build() );

		// The German content language localizes the "Help" namespace to "Hilfe", proving the
		// stored name uses the content-language prefix, not a canonical or interface-language one.
		$this->assertSame(
			'Hilfe:Namespaced subject page',
			$this->readPageNodeName( $revision->getPageId() )
		);
	}

	public function testPageNodeStoresNamespaceId(): void {
		$revision = $this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build() );

		$this->assertSame(
			NS_HELP,
			$this->readPageNodeNamespaceId( $revision->getPageId() )
		);
	}

}
