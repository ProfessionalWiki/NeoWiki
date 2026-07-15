<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;

readonly class SubjectValidator {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
		private SourceRegistry $sourceRegistry,
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
			if ( $schema->hasProperty( $statement->getPropertyName() ) ) {
				$violations = array_merge(
					$violations,
					$this->validateStatement( $statement, $schema->getProperty( $statement->getPropertyName() ) )
				);
			}

			$violations = array_merge( $violations, $this->validateRelationTargetSources( $statement ) );
		}

		return array_merge( $violations, $this->validateRequiredProperties( $statements, $schema ) );
	}

	/**
	 * @return Violation[]
	 */
	private function validateStatement( Statement $statement, PropertyDefinition $definition ): array {
		$propertyName = $statement->getPropertyName();

		// Writer's-schema drift (ADR 11 / ADR 12): the Schema property's type
		// has changed since this Statement was written. Surface as a violation
		// and skip per-type validation, which would no-op against a wrong-typed
		// PropertyDefinition anyway.
		if ( $statement->getPropertyType() !== $definition->getPropertyType() ) {
			return [ new Violation(
				propertyName: $propertyName,
				code: 'type-mismatch',
				args: [ $statement->getPropertyType(), $definition->getPropertyType() ],
			) ];
		}

		// The extension owning the type is disabled or failed to load, so the Value
		// cannot be interpreted, let alone validated. It is preserved verbatim, and
		// the degraded state is reported without blocking the write (ADR 12 / ADR 21).
		// Covers both a Statement written before the type went away and a new one.
		$propertyType = $this->propertyTypeLookup->getType( $statement->getPropertyType() );
		if ( $propertyType === null ) {
			return [ $this->newUnregisteredTypeViolation( $propertyName, $statement->getPropertyType() ) ];
		}

		$violations = [];

		foreach ( $propertyType->validate( $statement->getValue(), $definition ) as $rawViolation ) {
			$violations[] = $rawViolation->withPropertyName( $propertyName );
		}

		return $violations;
	}

	/**
	 * The v1 guard of ADR 23 ("Relations across Sources"): a Relation may only target a Subject whose
	 * Source resolves through the registry. Runs against every statement in the payload, not only
	 * schema-declared ones: schema membership says nothing about whether a target's Source resolves,
	 * and scoping the check to the schema would let an out-of-schema or type-mismatched relation
	 * statement carry an unresolvable target past enforcement. Bare and local-qualified targets carry
	 * no source key (SubjectIdParser canonicalizes the local-qualified form to bare) and pass
	 * untouched. This runs only on the validate and write paths; persisted Subjects are never
	 * revalidated on read, so an already-stored foreign target is never rejected and cross-source
	 * relations can open up later.
	 *
	 * @return Violation[]
	 */
	private function validateRelationTargetSources( Statement $statement ): array {
		$value = $statement->getValue();

		if ( !$value instanceof RelationValue ) {
			return [];
		}

		$violations = [];

		foreach ( $value->relations as $relation ) {
			$sourceKey = $relation->targetId->getSource();

			if ( $sourceKey !== null && $this->sourceRegistry->getSource( $sourceKey ) === null ) {
				$violations[] = new Violation(
					propertyName: $statement->getPropertyName(),
					code: 'relation-target-source-unresolvable',
					args: [ $sourceKey ],
				);
			}
		}

		return $violations;
	}

	/**
	 * Catch required-but-missing: the Schema declares the property as required, but no
	 * Statement for it is present on the Subject. This also covers the "empty Value
	 * dropped by StatementListBuilder" case, since dropped statements look the same as
	 * absent statements from the validator's perspective.
	 *
	 * @return Violation[]
	 */
	private function validateRequiredProperties( StatementList $statements, Schema $schema ): array {
		$violations = [];

		foreach ( $schema->getAllProperties()->asMap() as $name => $definition ) {
			$propertyName = new PropertyName( $name );

			if ( !$definition->isRequired() || $statements->getStatement( $propertyName ) !== null ) {
				continue;
			}

			// A required property of an unregistered type cannot be satisfied: the
			// extension that owned the type provided the only editor for its values.
			// A blocking `required` here would make the Subject uncreatable until the
			// extension returns. Report the degraded type instead.
			$violations[] = $this->propertyTypeLookup->getType( $definition->getPropertyType() ) === null
				? $this->newUnregisteredTypeViolation( $propertyName, $definition->getPropertyType() )
				: new Violation( propertyName: $propertyName, code: 'required' );
		}

		return $violations;
	}

	private function newUnregisteredTypeViolation( PropertyName $propertyName, string $propertyType ): Violation {
		return new Violation(
			propertyName: $propertyName,
			code: 'unregistered-type',
			args: [ $propertyType ],
		);
	}

}
