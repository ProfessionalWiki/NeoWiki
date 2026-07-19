<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Content\ValidationParams;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContentHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContentHandler
 * @group Database
 */
class MappingContentHandlerValidateSaveTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	private function validate( string $json, string $name = 'EDM' ): StatusValue {
		$handler = new MappingContentHandler( MappingContent::CONTENT_MODEL_ID );
		$params = new ValidationParams( Title::makeTitle( NeoWikiExtension::NS_MAPPING, $name )->toPageIdentity(), 0 );

		return $handler->validateSave( new MappingContent( $json ), $params );
	}

	public function testValidMappingPassesValidation(): void {
		$this->assertTrue( $this->validate( $this->edmMapping() )->isOK() );
	}

	public function testStructurallyInvalidMappingFailsValidation(): void {
		$this->assertFalse( $this->validate( '{ "version": 2, "schemas": {} }' )->isOK() );
	}

	public function testUnresolvableCuriePredicateFailsValidation(): void {
		$this->assertFalse( $this->validate( <<<JSON
			{
				"version": 1,
				"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "edm:ProvidedCHO" },
						"properties": { "Name": { "predicate": "crm:P1_is_identified_by" } }
					}
				}
			}
			JSON )->isOK() );
	}

	public function testReservedNativeNameIsRejected(): void {
		// The page title is the projection name, and "native" is the built-in projection, so a
		// Mapping:Native page is rejected — even with otherwise valid content.
		$status = $this->validate( $this->edmMapping(), 'Native' );

		$this->assertFalse( $status->isOK() );
		$this->assertTrue( $status->hasMessage( 'neowiki-mapping-name-invalid' ) );
	}

	private function edmMapping(): string {
		return <<<JSON
			{
				"version": 1,
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"dc": "http://purl.org/dc/elements/1.1/"
				},
				"schemas": {
					"Person": {
						"subject": { "class": "edm:ProvidedCHO" },
						"properties": {
							"Name": { "predicate": "dc:title", "lang": "en" }
						}
					}
				}
			}
			JSON;
	}

}
