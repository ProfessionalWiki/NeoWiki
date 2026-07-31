<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;

readonly class SubjectValidator {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
		private SubjectLookup $subjectLookup,
	) {
	}

	/**
	 * @return Violation[]
	 */
	public function validate( SubjectLabel $label, StatementList $statements, Schema $schema ): array {
		$violations = [];

		if ( trim( $label->text ) === '' ) {
			$violations[] = new Violation( propertyName: null, code: 'label-required', severity: Severity::Error );
		}

		foreach ( $statements->asArray() as $statement ) {
			if ( $schema->hasProperty( $statement->getPropertyName() ) ) {
				$violations = array_merge(
					$violations,
					$this->validateStatement( $statement, $schema->getProperty( $statement->getPropertyName() ) )
				);
			}
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
				severity: Severity::Error,
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

		return array_merge( $violations, $this->validateRelationTargets( $statement, $definition ) );
	}

	/**
	 * Server-side relation-target checks. Schema-scoped by necessity: both need the writer's-schema
	 * RelationProperty (for its declared targetSchema) and a Subject lookup, neither of which
	 * PropertyType::validate() has access to. This runs after the per-type dispatch, so the
	 * Statement's type still matches the Schema's relation property here; a type-mismatched
	 * Statement returned earlier and is not treated as a relation.
	 *
	 * A missing target is a non-blocking `relation-target-not-found` warning (red-link philosophy:
	 * the target may be minted later, e.g. during import); a resolvable target whose own Schema is
	 * not the declared targetSchema is a blocking `relation-target-schema-mismatch` error.
	 *
	 * The Schema compared is the target's own writer's-schema, read from its revision slot rather
	 * than from a graph node property. Reaching that slot still resolves the target id through the
	 * subject -> page index, which lives only in the graph projection (see
	 * {@see \ProfessionalWiki\NeoWiki\NeoWikiExtension::getPageIdentifiersLookup()}), so an
	 * unrebuilt or stale graph reports an existing target as not found. That is the same
	 * degradation the read path has, and the reason not-found is non-blocking.
	 *
	 * @return Violation[]
	 */
	private function validateRelationTargets( Statement $statement, PropertyDefinition $definition ): array {
		$value = $statement->getValue();

		if ( !$definition instanceof RelationProperty || !$value instanceof RelationValue ) {
			return [];
		}

		$violations = [];

		foreach ( $value->relations as $index => $relation ) {
			$violation = $this->validateRelationTarget(
				$relation,
				$statement->getPropertyName(),
				$definition->getTargetSchema(),
				(int)$index
			);

			if ( $violation !== null ) {
				$violations[] = $violation;
			}
		}

		return $violations;
	}

	/**
	 * The target's position in the multi-value RelationValue is carried as valuePartIndex so
	 * ViolationDiff can tell a newly-added bad target apart from a pre-existing same-code
	 * violation on the same property, exactly as SelectType/UrlType do for their parts.
	 */
	private function validateRelationTarget(
		Relation $relation,
		PropertyName $propertyName,
		SchemaName $targetSchema,
		int $valuePartIndex
	): ?Violation {
		$target = $this->subjectLookup->getSubject( $relation->targetId );

		if ( $target === null ) {
			return new Violation(
				propertyName: $propertyName,
				code: 'relation-target-not-found',
				args: [ $relation->targetId->text ],
				valuePartIndex: $valuePartIndex,
				severity: Severity::Warning,
			);
		}

		if ( $target->getSchemaName()->getText() !== $targetSchema->getText() ) {
			return new Violation(
				propertyName: $propertyName,
				code: 'relation-target-schema-mismatch',
				args: [ $targetSchema->getText(), $target->getSchemaName()->getText() ],
				valuePartIndex: $valuePartIndex,
				severity: Severity::Error,
			);
		}

		return null;
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
				: new Violation( propertyName: $propertyName, code: 'required', severity: $definition->severityOf( 'required' ) );
		}

		return $violations;
	}

	private function newUnregisteredTypeViolation( PropertyName $propertyName, string $propertyType ): Violation {
		return new Violation(
			propertyName: $propertyName,
			code: 'unregistered-type',
			args: [ $propertyType ],
			severity: Severity::Warning,
		);
	}

}
