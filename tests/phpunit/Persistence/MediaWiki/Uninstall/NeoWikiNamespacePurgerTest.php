<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Uninstall;

use MediaWiki\Language\RawMessage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiPageDeleter;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\NeoWikiNamespacePurger;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use StatusValue;

/**
 * Deletes real pages through MediaWiki's deletion, and reads the test wiki's page and archive tables.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\NeoWikiNamespacePurger
 * @group Database
 */
class NeoWikiNamespacePurgerTest extends NeoWikiIntegrationTestCase {

	public function testDeletesEveryPageInANeoWikiNamespace(): void {
		$this->createSchema( 'Company' );
		$this->createLayout( 'Company' );
		$this->editPage( Title::makeTitle( NeoWikiExtension::NS_SCHEMA + 1, 'Company' ), 'Talk about the Schema' );

		$this->deletePages();

		$this->assertSame( 0, $this->countRows( 'page', [ 'page_namespace' => NeoWikiNamespacePurger::NAMESPACES ] ) );
	}

	public function testKeepsPagesInOtherNamespaces(): void {
		$this->createSchema( 'Company' );
		$this->editPage( 'NeoWikiNamespacePurgerTestArticle', 'Not NeoWiki' );

		$this->deletePages();

		$this->assertTrue( Title::newFromText( 'NeoWikiNamespacePurgerTestArticle' )->exists() );
	}

	public function testPurgesTheDeletedRevisionsOfNeoWikiNamespaces(): void {
		$this->createSchema( 'Company' );
		$this->createLayout( 'Deleted before' );
		$this->deletePageByName( 'Layout:Deleted before' );

		$this->deletePages();
		$this->purgeDeletedRevisions();

		$this->assertSame( 0, $this->countRows( 'archive', [ 'ar_namespace' => NeoWikiNamespacePurger::NAMESPACES ] ) );
	}

	public function testKeepsTheDeletedRevisionsOfOtherNamespaces(): void {
		$this->editPage( 'NeoWikiNamespacePurgerTestArticle', 'Not NeoWiki' );
		$this->deletePageByName( 'NeoWikiNamespacePurgerTestArticle' );

		$this->purgeDeletedRevisions();

		$this->assertSame( 1, $this->countRows( 'archive', [ 'ar_title' => 'NeoWikiNamespacePurgerTestArticle' ] ) );
	}

	public function testAPageWhoseDeletionIsRefusedIsPassedOver(): void {
		$this->createSchema( 'Refused' );
		$this->createSchema( 'Company' );
		$this->refuseDeletionOf( 'Refused', 'Kept for legal reasons' );

		$this->deletePages();

		$this->assertTrue( $this->schemaPageExists( 'Refused' ) );
		$this->assertFalse( $this->schemaPageExists( 'Company' ) );
	}

	public function testReportsWhyAPageCouldNotBeDeleted(): void {
		$this->createSchema( 'Refused' );
		$this->refuseDeletionOf( 'Refused', 'Kept for legal reasons' );
		$failures = [];

		iterator_to_array( $this->newPurger()->deletePages(
			static function ( string $page, string $reason ) use ( &$failures ): void {
				$failures[$page] = $reason;
			}
		) );

		$this->assertSame( [ 'Schema:Refused' => 'Kept for legal reasons' ], $failures );
	}

	public function testReportsHowManyPagesAreDeletedAfterEachBatch(): void {
		$this->createSchema( 'Company' );
		$this->createLayout( 'Company' );

		$this->assertSame( [ 1, 2 ], iterator_to_array( $this->newPurger()->deletePages( static function (): void {
		} ), false ) );
	}

	public function testReportsHowManyRevisionsArePurgedAfterEachBatch(): void {
		$this->createSchema( 'Company' );
		$this->createSchema( 'Company', '{"title":"Company","description":"Second revision","propertyDefinitions":{}}' );
		$this->deletePageByName( 'Schema:Company' );

		$this->assertSame( [ 1, 2 ], iterator_to_array( $this->newPurger()->purgeDeletedRevisions(), false ) );
	}

	public function testCoversEveryNamespaceNeoWikiRegisters(): void {
		$this->assertEqualsCanonicalizing(
			array_column( $this->readExtensionJson()['namespaces'], 'id' ),
			NeoWikiNamespacePurger::NAMESPACES
		);
	}

	private function refuseDeletionOf( string $schemaName, string $refusal ): void {
		$this->setTemporaryHook(
			'PageDelete',
			static function ( ProperPageIdentity $page, Authority $deleter, string $reason, StatusValue $status ) use (
				$schemaName,
				$refusal
			): bool {
				if ( $page->getDBkey() !== $schemaName ) {
					return true;
				}

				$status->fatal( new RawMessage( $refusal ) );
				return false;
			}
		);
	}

	/**
	 * Read from the database: from MediaWiki 1.44, Title::exists() still reports a page the purger has deleted.
	 */
	private function schemaPageExists( string $name ): bool {
		return $this->countRows( 'page', [ 'page_namespace' => NeoWikiExtension::NS_SCHEMA, 'page_title' => $name ] ) > 0;
	}

	private function deletePages(): void {
		iterator_to_array( $this->newPurger()->deletePages( static function (): void {
		} ) );
	}

	private function purgeDeletedRevisions(): void {
		iterator_to_array( $this->newPurger()->purgeDeletedRevisions() );
	}

	/**
	 * Batches of one, so that every test also covers moving from one batch to the next.
	 */
	private function newPurger(): NeoWikiNamespacePurger {
		$services = $this->getServiceContainer();

		return new NeoWikiNamespacePurger(
			db: $this->getDb(),
			wikiPageFactory: $services->getWikiPageFactory(),
			pageDeleter: new MediaWikiPageDeleter( $services->getDeletePageFactory(), $this->getTestSysop()->getAuthority() ),
			batchSize: 1
		);
	}

}
