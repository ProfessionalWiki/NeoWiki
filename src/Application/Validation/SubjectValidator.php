<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

readonly class SubjectValidator {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * @return Violation[]
	 */
	public function validate( Subject $subject, Schema $schema ): array {
		$violations = [];

		if ( trim( $subject->getLabel()->text ) === '' ) {
			$violations[] = new Violation( propertyName: null, code: 'label-required' );
		}

		foreach ( $subject->getStatements()->asArray() as $statement ) {
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
