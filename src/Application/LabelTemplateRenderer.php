<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

/**
 * The label a Schema's label template gives a Subject. Each placeholder reads as the first of the strings
 * the property's type says its value is searched by, which spells a select option by its label and a
 * number in digits. templateLabel() in resources/ext.neowiki/src/domain/LabelTemplate.ts mirrors this
 * for the core types.
 */
readonly class LabelTemplateRenderer {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * Null when the Schema has no template, or none of its placeholders has a value in the Subject.
	 */
	public function render( Subject $subject, Schema $schema ): ?string {
		return $schema->getLabelTemplate()?->render(
			fn( PropertyName $name ): string => $this->valueOf( $subject, $name, $schema )
		);
	}

	private function valueOf( Subject $subject, PropertyName $name, Schema $schema ): string {
		$statement = $subject->getStatements()->getStatement( $name );

		if ( $statement === null ) {
			return '';
		}

		$propertyType = $this->propertyTypeLookup->getType( $statement->getPropertyType() );

		if ( $propertyType === null ) {
			return '';
		}

		$texts = $propertyType->searchText( $statement->getValue(), $schema->definitionOf( $statement ) );

		return trim( $texts[0] ?? '' );
	}

}
