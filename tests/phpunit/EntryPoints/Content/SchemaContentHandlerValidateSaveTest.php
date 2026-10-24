<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Content\ValidationParams;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\DependentSubjectSchemaRules
 * @covers \ProfessionalWiki\NeoWiki\Presentation\PropertyNameProblemMessage
 * @group Database
 */
class SchemaContentHandlerValidateSaveTest extends NeoWikiIntegrationTestCase {

	private function validate( string $json, string $name = 'Person' ): StatusValue {
		$handler = new SchemaContentHandler( SchemaContent::CONTENT_MODEL_ID );
		$title = Title::makeTitle( NeoWikiExtension::NS_SCHEMA, $name );
		$params = new ValidationParams( $title->toPageIdentity(), 0 );

		return $handler->validateSave( new SchemaContent( $json ), $params );
	}

	public function testValidSchemaPassesValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "Age": { "type": "number" } } }' );

		$this->assertTrue( $status->isOK() );
	}

	public function testSchemaMissingPropertyDefinitionsFailsValidation(): void {
		$status = $this->validate( '{ "notPropertyDefinitions": {} }' );

		$this->assertFalse( $status->isOK() );
	}

	public function testStructurallyInvalidPropertyDefinitionFailsValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "Age": { "type": "" } } }' );

		$this->assertFalse( $status->isOK() );
	}

	public function testReservedSchemaNameFailsValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": {} }', 'Page' );

		$this->assertFalse( $status->isOK() );
	}

	/**
	 * The REST envelope carries only the first message, so a caller that never sees the rest
	 * is told nothing unless that one names every error, and how many there were.
	 */
	public function testFirstMessageNamesEveryErrorAndCountsThem(): void {
		$status = $this->validate( '{ "propertyDefinitions": {
			"Owner": { "type": "relation" },
			"Maker": { "type": "relation", "targetSchemas": [ " Company" ] }
		} }' );

		$text = wfMessage( $status->getMessages()[0] )->text();

		$this->assertStringContainsString( '2 errors', $text );
		$this->assertStringContainsString( '/propertyDefinitions/Owner', $text );
		$this->assertStringContainsString( '/propertyDefinitions/Maker/targetSchemas/0', $text );
	}

	public function testFirstMessageNamesEveryPropertyNameBreakingARuleAndCountsThem(): void {
		$status = $this->validate( '{ "propertyDefinitions": {
			"id": { "type": "text" },
			"Birth date": { "type": "text" },
			"birth date": { "type": "text" },
			"Birth  place": { "type": "text" },
			"Death\u200bplace": { "type": "text" },
			"Phone #": { "type": "text" }
		} }' );

		$text = $this->firstMessageInQqx( $status );

		$this->assertStringContainsString( '(neowiki-schema-invalid: 5,', $text );
		$this->assertStringContainsString( '(neowiki-property-name-reserved: id)', $text );
		$this->assertStringContainsString( '(neowiki-property-name-near-duplicate: birth date, Birth date)', $text );
		$this->assertStringContainsString( '(neowiki-property-name-not-canonical: Birth  place, Birth place)', $text );
		$this->assertStringContainsString(
			"(neowiki-property-name-not-canonical-invisible: Death\u{200B}place, Deathplace, U+200B)",
			$text
		);
		$this->assertStringContainsString( '(neowiki-property-name-characters: Phone #, #)', $text );
	}

	/**
	 * PHP decodes an integer-like JSON key into an int, which reading the page as a Schema drops.
	 */
	public function testIntegerPropertyNameFailsValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "2024": { "type": "text" } } }' );

		$this->assertStringContainsString( '(neowiki-property-name-integer: 2024)', $this->firstMessageInQqx( $status ) );
	}

	public function testStructurallyInvalidSchemaReportsOnlyItsStructuralError(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "id": { "type": "" } } }' );

		$this->assertCount( 1, $status->getMessages() );
		$this->assertStringContainsString( '(neowiki-schema-invalid: 1,', $this->firstMessageInQqx( $status ) );
	}

	private function firstMessageInQqx( StatusValue $status ): string {
		return wfMessage( $status->getMessages()[0] )->inLanguage( 'qqx' )->text();
	}

	public function testChangingWhetherTheSubjectsOfAStoredSchemaAreDependentFailsValidation(): void {
		$this->createSchema( 'Birth', '{"subjectsAreDependent":true,"propertyDefinitions":{}}' );

		$status = $this->validate( '{ "propertyDefinitions": {} }', 'Birth' );

		$this->assertStatusError( 'neowiki-schema-dependence-unchangeable', $status );
	}

	public function testEditingAStoredSchemaWithoutChangingItsDeclarationPassesValidation(): void {
		$this->createSchema( 'Birth', '{"subjectsAreDependent":true,"propertyDefinitions":{}}' );

		$status = $this->validate( '{ "subjectsAreDependent": true, "description": "Edited", "propertyDefinitions": {} }', 'Birth' );

		$this->assertStatusGood( $status );
	}

	public function testARelationPropertyTargetingSchemasWithDependentAndWithStandaloneSubjectsFailsValidation(): void {
		$this->createSchema( 'Birth', '{"subjectsAreDependent":true,"propertyDefinitions":{}}' );
		$this->createSchema( 'Place', '{"propertyDefinitions":{}}' );

		$status = $this->validate(
			'{ "propertyDefinitions": { "Related": { "type": "relation", "targetSchemas": [ "Birth", "Place" ] } } }'
		);

		$this->assertStatusError( 'neowiki-schema-relation-targets-mixed', $status );
	}

}
