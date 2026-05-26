<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
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

			$definition = $schema->getProperty( $propertyName );

			// Writer's-schema drift (ADR 11 / ADR 12): the Schema property's type
			// has changed since this Statement was written. Surface as a violation
			// and skip per-type validation, which would no-op against a wrong-typed
			// PropertyDefinition anyway.
			if ( $statement->getPropertyType() !== $definition->getPropertyType() ) {
				$violations[] = new Violation(
					propertyName: $propertyName,
					code: 'type-mismatch',
					args: [ $statement->getPropertyType(), $definition->getPropertyType() ],
				);
				continue;
			}

			$propertyType = $this->propertyTypeLookup->getType( $statement->getPropertyType() );
			if ( $propertyType === null ) {
				continue;
			}

			foreach ( $propertyType->validate( $statement->getValue(), $definition ) as $rawViolation ) {
				$violations[] = $rawViolation->withPropertyName( $propertyName );
			}
		}

		// Catch required-but-missing: Schema declares the property as required,
		// but no Statement for it is present on the Subject. This also covers
		// the "empty Value dropped by StatementListBuilder" case, since dropped
		// statements look the same as absent statements from the validator's
		// perspective.
		foreach ( $schema->getAllProperties()->asMap() as $name => $definition ) {
			if ( !$definition->isRequired() ) {
				continue;
			}

			$propertyName = new PropertyName( $name );
			if ( $statements->getStatement( $propertyName ) !== null ) {
				continue;
			}

			$violations[] = new Violation(
				propertyName: $propertyName,
				code: 'required',
			);
		}

		return $violations;
	}

}
