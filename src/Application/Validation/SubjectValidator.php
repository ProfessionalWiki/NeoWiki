<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

readonly class SubjectValidator {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * @return Violation[]
	 */
	public function validate( SubjectLabel $label, StatementList $statements, Schema $schema ): array {
		$violations = [];

		if ( trim( $label->text ) === '' ) {
			$violations[] = new Violation( propertyName: null, code: 'label-required' );
		}

		foreach ( $statements->asArray() as $statement ) {
			$propertyName = $statement->getPropertyName();

			if ( !$schema->hasProperty( $propertyName ) ) {
				continue;
			}

			$propertyType = $this->propertyTypeLookup->getType( $statement->getPropertyType() );
			if ( $propertyType === null ) {
				continue;
			}

			$definition = $schema->getProperty( $propertyName );

			foreach ( $propertyType->validate( $statement->getValue(), $definition ) as $rawViolation ) {
				$violations[] = $rawViolation->withPropertyName( $propertyName );
			}
		}

		return $violations;
	}

}
