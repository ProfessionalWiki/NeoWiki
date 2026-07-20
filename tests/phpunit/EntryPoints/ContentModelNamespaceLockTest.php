<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\NamespaceContentModels;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * NeoWiki's JSON namespaces (Schema, Layout, Mapping) are each locked to a single content model.
 * The lock is enforced by the ContentModelCanBeUsedOn hook and by the content handlers, both driven
 * by the same NamespaceContentModels map, so these tests iterate that map instead of hard-coding the
 * pairs. That way a namespace added to the map is automatically exercised here too.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onContentModelCanBeUsedOn
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\NamespaceContentModels
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler::canBeUsedOn
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContentHandler::canBeUsedOn
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContentHandler::canBeUsedOn
 * @group Database
 */
class ContentModelNamespaceLockTest extends MediaWikiIntegrationTestCase {

	/**
	 * The concrete namespace/model pairs are pinned here with literal model ids, not derived from the map,
	 * so this catches a wrong or swapped binding that the map-driven tests below (which read their expected
	 * model from the same map) cannot.
	 */
	public function testLocksEachJsonNamespaceToItsContentModel(): void {
		$this->assertSame(
			[
				NeoWikiExtension::NS_SCHEMA => 'NeoWikiSchema',
				NeoWikiExtension::NS_LAYOUT => 'NeoWikiLayout',
				NeoWikiExtension::NS_MAPPING => 'NeoWikiMapping',
			],
			NamespaceContentModels::map()
		);
	}

	/**
	 * @dataProvider namespaceAndModelProvider
	 */
	public function testNamespaceAllowsItsOwnContentModel( int $namespace, string $model ): void {
		$ok = false;

		NeoWikiHooks::onContentModelCanBeUsedOn( $model, Title::makeTitle( $namespace, 'Example' ), $ok );

		$this->assertTrue( $ok );
	}

	/**
	 * @dataProvider neoWikiNamespaceProvider
	 */
	public function testNamespaceRejectsWikitext( int $namespace ): void {
		$ok = true;

		NeoWikiHooks::onContentModelCanBeUsedOn( CONTENT_MODEL_WIKITEXT, Title::makeTitle( $namespace, 'Example' ), $ok );

		$this->assertFalse( $ok );
	}

	/**
	 * A foreign NeoWiki model (e.g. NeoWikiLayout in the Schema namespace) must be rejected too, so the
	 * lock cannot be satisfied by any NeoWiki model regardless of the namespace it belongs to.
	 *
	 * @dataProvider foreignNeoWikiModelProvider
	 */
	public function testNamespaceRejectsForeignNeoWikiContentModels( int $namespace, string $foreignModel ): void {
		$ok = true;

		NeoWikiHooks::onContentModelCanBeUsedOn( $foreignModel, Title::makeTitle( $namespace, 'Example' ), $ok );

		$this->assertFalse( $ok );
	}

	public function testLeavesForeignNamespaceDecisionUnchanged(): void {
		$title = Title::makeTitle( NS_MAIN, 'Ordinary Page' );

		$allowed = true;
		NeoWikiHooks::onContentModelCanBeUsedOn( CONTENT_MODEL_WIKITEXT, $title, $allowed );
		$this->assertTrue( $allowed, 'An allowed decision must be left untouched outside NeoWiki namespaces.' );

		$denied = false;
		NeoWikiHooks::onContentModelCanBeUsedOn( SchemaContent::CONTENT_MODEL_ID, $title, $denied );
		$this->assertFalse( $denied, 'A denied decision must be left untouched outside NeoWiki namespaces.' );
	}

	/**
	 * The handler side of the lock, reached through the real content-handler factory so this also proves
	 * each model is registered: a NeoWiki model may be used in its own namespace but nowhere else.
	 *
	 * @dataProvider namespaceAndModelProvider
	 */
	public function testNeoWikiModelIsRestrictedToItsNamespace( int $namespace, string $model ): void {
		$handler = $this->getServiceContainer()->getContentHandlerFactory()->getContentHandler( $model );

		$this->assertTrue(
			$handler->canBeUsedOn( Title::makeTitle( $namespace, 'Example' ) ),
			'The model must be usable in its own namespace.'
		);
		$this->assertFalse(
			$handler->canBeUsedOn( Title::makeTitle( NS_MAIN, 'Example' ) ),
			'The model must not be usable outside its namespace.'
		);
	}

	/**
	 * The real save path: writing a foreign content model into a NeoWiki namespace is rejected before the
	 * revision is created. This exercises the hook through PageUpdater, proving it is wired, not just callable.
	 *
	 * @dataProvider neoWikiNamespaceProvider
	 */
	public function testSavingForeignModelInNeoWikiNamespaceIsRejected( int $namespace ): void {
		$title = Title::makeTitle( $namespace, 'Example' );
		$updater = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title )
			->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new WikitextContent( 'Plain wikitext' ) );

		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'test' ) );

		$this->assertStatusError( 'content-not-allowed-here', $updater->getStatus() );
	}

	public static function namespaceAndModelProvider(): iterable {
		foreach ( NamespaceContentModels::map() as $namespace => $model ) {
			yield $model => [ $namespace, $model ];
		}
	}

	public static function neoWikiNamespaceProvider(): iterable {
		foreach ( NamespaceContentModels::map() as $namespace => $model ) {
			yield $model => [ $namespace ];
		}
	}

	public static function foreignNeoWikiModelProvider(): iterable {
		$map = NamespaceContentModels::map();

		foreach ( $map as $namespace => $ownModel ) {
			foreach ( $map as $foreignModel ) {
				if ( $foreignModel !== $ownModel ) {
					yield "$ownModel namespace rejects $foreignModel" => [ $namespace, $foreignModel ];
				}
			}
		}
	}

}
