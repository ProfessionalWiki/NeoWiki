<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property\PropertyTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition
 */
class PropertyDefinitionTest extends PropertyTestCase {

	public function testLabelsSurviveSerialization(): void {
		$this->assertSame(
			[ 'en' => 'Date of birth', 'de' => 'Geburtsdatum' ],
			$this->fromJson( '{ "type": "date", "labels": { "en": "Date of birth", "de": "Geburtsdatum" } }' )->toJson()['labels']
		);
	}

	public function testLabelLanguageTagsAreLowercased(): void {
		$this->assertSame(
			[ 'pt-br' => 'Data de nascimento' ],
			$this->fromJson( '{ "type": "date", "labels": { "pt-BR": "Data de nascimento" } }' )->getLabels()
		);
	}

	public function testLabelsAreTrimmed(): void {
		$this->assertSame(
			[ 'de' => 'Geburtsdatum' ],
			$this->fromJson( '{ "type": "date", "labels": { "de": " Geburtsdatum " } }' )->getLabels()
		);
	}

	public function testEmptyLabelsAreDropped(): void {
		$this->assertSame(
			[ 'en' => 'Date of birth' ],
			$this->fromJson( '{ "type": "date", "labels": { "de": " ", "en": "Date of birth", "nl": "" } }' )->getLabels()
		);
	}

	public function testLabelsAreNeverReadAsAConstraintSeverity(): void {
		$this->assertSame(
			[ 'severity' => 'error' ],
			$this->fromJson( '{ "type": "date", "labels": { "severity": "error" } }' )->getLabels()
		);
	}

	public function testDefinitionWhoseLabelsAreAllEmptySerializesWithoutThem(): void {
		$this->assertArrayNotHasKey(
			'labels',
			$this->fromJson( '{ "type": "date", "labels": { "de": "" } }' )->toJson()
		);
	}

}
