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

	public function testFieldValueItsPropertyTypeCannotUseIsNamedWithWhatItMustHold(): void {
		$status = $this->validate( '{ "propertyDefinitions": {
			"Name": { "type": "text", "minLength": "five" },
			"Age": { "type": "number" }
		} }' );

		$this->assertSame(
			'(neowiki-schema-invalid: 1, (neowiki-schema-invalid-detail: /propertyDefinitions/Name/minLength, must be a whole number, not "five"))',
			$this->firstMessageInQqx( $status )
		);
	}

	public function testPointerToAFieldEscapesSlashAndTildeInThePropertyName(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "Height/We~ight": { "type": "text", "minLength": "five" } } }' );

		$this->assertStringContainsString( '/propertyDefinitions/Height~1We~0ight/minLength,', $this->firstMessageInQqx( $status ) );
	}

	public function testFirstMessageNamesEveryPropertyDefinitionThatCannotBeReadAndCountsThem(): void {
		$status = $this->validate( '{ "propertyDefinitions": {
			"Founded": { "type": "date", "minimum": "yesterday" },
			"Motto": { "type": "text" },
			"Status": { "type": "select", "options": [ { "id": "a", "label": "A" }, { "id": "a", "label": "B" } ] }
		} }' );

		$text = $this->firstMessageInQqx( $status );

		$this->assertStringContainsString( '(neowiki-schema-invalid: 2,', $text );
		$this->assertStringContainsString( '/propertyDefinitions/Founded/minimum, must be a date', $text );
		$this->assertStringContainsString( '/propertyDefinitions/Status/options, the option ID "a"', $text );
		$this->assertStringNotContainsString( 'Motto', $text );
	}

	public function testPropertyDefinitionItsPropertyTypeCannotReadForAnotherReasonIsNamedAsAWhole(): void {
		$status = $this->validate( '{ "propertyDefinitions": {
			"Supervisor": { "type": "relation", "targetSchemas": [ "Page" ] }
		} }' );

		$this->assertStringContainsString(
			'(neowiki-schema-invalid-detail: /propertyDefinitions/Supervisor, its Property Type cannot use this Property Definition)',
			$this->firstMessageInQqx( $status )
		);
	}

	public function testMessageShowsTheNamesOfUnreadablePropertiesAsWritten(): void {
		$status = $this->validate( '{ "propertyDefinitions": {
			"\'\'Big\'\'": { "type": "date", "minimum": "yesterday" },
			"R&amp;D": { "type": "date", "minimum": "yesterday" }
		} }' );

		$text = html_entity_decode(
			strip_tags( wfMessage( $status->getMessages()[0] )->inLanguage( 'en' )->parse() ),
			ENT_QUOTES | ENT_HTML5
		);

		$this->assertStringContainsString( "''Big''", $text );
		$this->assertStringContainsString( 'R&amp;D', $text );
	}

	public function testPropertyDefinitionOfAnUnregisteredTypePassesValidation(): void {
		$status = $this->validate( '{ "propertyDefinitions": { "Mood": { "type": "typeNoExtensionRegisters" } } }' );

		$this->assertStatusGood( $status );
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

	public function testUnreadablePropertyDefinitionIsReportedAlongsideADependentSubjectError(): void {
		$this->createSchema( 'Birth', '{"subjectsAreDependent":true,"propertyDefinitions":{}}' );

		$status = $this->validate(
			'{ "propertyDefinitions": { "Founded": { "type": "date", "minimum": "yesterday" } } }',
			'Birth'
		);

		$this->assertStatusError( 'neowiki-schema-invalid', $status );
		$this->assertStatusError( 'neowiki-schema-dependence-unchangeable', $status );
	}

}
