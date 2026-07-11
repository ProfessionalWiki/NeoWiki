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

	private function validate( string $json, string $name = 'Person to EDM' ): StatusValue {
		$handler = new MappingContentHandler( MappingContent::CONTENT_MODEL_ID );
		$params = new ValidationParams( Title::makeTitle( NeoWikiExtension::NS_MAPPING, $name )->toPageIdentity(), 0 );

		return $handler->validateSave( new MappingContent( $json ), $params );
	}

	public function testValidMappingPassesValidation(): void {
		$this->assertTrue( $this->validate( $this->personToEdm() )->isOK() );
	}

	public function testStructurallyInvalidMappingFailsValidation(): void {
		$this->assertFalse( $this->validate( '{ "version": 2, "schema": "Person", "target": "edm", "subject": { "class": "http://x/C" }, "properties": {} }' )->isOK() );
	}

	public function testUnresolvableCuriePredicateFailsValidation(): void {
		$this->assertFalse( $this->validate( <<<JSON
			{
				"version": 1,
				"schema": "Person",
				"target": "edm",
				"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
				"subject": { "class": "edm:ProvidedCHO" },
				"properties": { "Name": { "predicate": "crm:P1_is_identified_by" } }
			}
			JSON )->isOK() );
	}

	public function testDuplicateSchemaAndTargetPairFailsValidation(): void {
		$this->createMapping( 'Existing Person to EDM', $this->personToEdm() );

		$status = $this->validate( $this->personToEdm(), 'Another Person to EDM' );

		$this->assertFalse( $status->isOK() );
	}

	public function testSameSchemaWithADifferentTargetPassesValidation(): void {
		$this->createMapping( 'Person to EDM', $this->personToEdm() );

		$status = $this->validate(
			<<<JSON
			{
				"version": 1,
				"schema": "Person",
				"target": "cidoc",
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"subject": { "class": "crm:E21_Person" },
				"properties": {}
			}
			JSON,
			'Person to CIDOC'
		);

		$this->assertTrue( $status->isOK() );
	}

	public function testEditingAMappingInPlaceDoesNotConflictWithItself(): void {
		$this->createMapping( 'Person to EDM', $this->personToEdm() );

		// Re-saving the same page with the same (schema, target) must not count as a duplicate of itself.
		$status = $this->validate( $this->personToEdm(), 'Person to EDM' );

		$this->assertTrue( $status->isOK() );
	}

	private function personToEdm(): string {
		return <<<JSON
			{
				"version": 1,
				"schema": "Person",
				"target": "edm",
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"dc": "http://purl.org/dc/elements/1.1/"
				},
				"subject": { "class": "edm:ProvidedCHO" },
				"properties": {
					"Name": { "predicate": "dc:title", "lang": "en" }
				}
			}
			JSON;
	}

}
