<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use ImportableOldRevisionImporter;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use WikiRevision;

/**
 * Characterization test for a deliberately-accepted known gap.
 *
 * NeoWiki gates Schema and Layout content at SchemaContentHandler::validateSave()
 * (and LayoutContentHandler::validateSave()), which runs on every PageUpdater write:
 * the REST /v1/page PUT, the action API, EditPage, and the NeoWiki demo importer.
 *
 * Core MediaWiki XML import (Special:Import -> ImportableOldRevisionImporter ->
 * RevisionStore::insertRevisionOn) writes revisions directly to the RevisionStore and
 * does NOT call ContentHandler::validateSave. Structurally invalid Schema content can
 * therefore still be imported through that path. This is the known remaining gap; closing
 * it would require a dedicated import gate, which is out of scope for the validate-at-save
 * work. This test documents the bypass so the gap is visible and any future change is caught.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler
 * @group Database
 */
class ImportValidationGapTest extends MediaWikiIntegrationTestCase {

	private const INVALID_SCHEMA_JSON = '{ "notPropertyDefinitions": {} }';

	public function testValidateSaveRejectsTheInvalidSchemaContent(): void {
		$handler = new SchemaContentHandler( SchemaContent::CONTENT_MODEL_ID );
		$title = Title::makeTitle( NeoWikiExtension::NS_SCHEMA, 'ImportGapGuard' );
		$params = new ValidationParams( $title->toPageIdentity(), 0 );

		$status = $handler->validateSave( new SchemaContent( self::INVALID_SCHEMA_JSON ), $params );

		$this->assertFalse(
			$status->isOK(),
			'Precondition: validateSave must reject this content, otherwise the test below proves nothing.'
		);
	}

	public function testCoreXmlImportBypassesValidateSave(): void {
		$title = Title::makeTitle( NeoWikiExtension::NS_SCHEMA, 'ImportedInvalidSchema' );

		$revision = new WikiRevision();
		$revision->setTitle( $title );
		$revision->setContent( SlotRecord::MAIN, new SchemaContent( self::INVALID_SCHEMA_JSON ) );

		$imported = $this->newImporter()->import( $revision );

		$this->assertTrue( $imported, 'Core XML import accepts the invalid schema content.' );
		$this->assertTrue( $title->exists() );
	}

	private function newImporter(): ImportableOldRevisionImporter {
		$services = $this->getServiceContainer();

		return new ImportableOldRevisionImporter(
			true,
			new NullLogger(),
			$services->getConnectionProvider(),
			$services->getRevisionStoreFactory()->getRevisionStoreForImport(),
			$services->getSlotRoleRegistry(),
			$services->getWikiPageFactory(),
			$services->getPageUpdaterFactory(),
			$services->getUserFactory()
		);
	}

}
