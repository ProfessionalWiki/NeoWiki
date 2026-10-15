<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;

/**
 * Schemas that do not resolve are ignored.
 */
readonly class DependentSubjectSchemaRules {

	public function __construct(
		private SchemaReferenceResolver $schemaResolver,
	) {
	}

	/**
	 * Whether $schema declares its Subjects dependent differently from the stored Schema of its name.
	 */
	public function changesDeclaration( Schema $schema ): bool {
		$storedSchema = $this->schemaResolver->getSchema( SchemaReference::local( $schema->getName() ) );

		return $storedSchema !== null && $storedSchema->subjectsAreDependent() !== $schema->subjectsAreDependent();
	}

	/**
	 * The names of the relation properties of $schema whose target Schemas include both Schemas with
	 * Dependent Subjects and Schemas with standalone Subjects.
	 *
	 * @return string[]
	 */
	public function propertiesMixingTargetSchemas( Schema $schema ): array {
		$propertyNames = [];

		foreach ( $schema->getRelationProperties()->asMap() as $propertyName => $property ) {
			if ( $property instanceof RelationProperty && count( $this->declarationsOfTargets( $schema, $property ) ) > 1 ) {
				$propertyNames[] = $propertyName;
			}
		}

		return $propertyNames;
	}

	/**
	 * @return array<int, true> Keyed by whether a target Schema declares its Subjects dependent, as 1 or 0.
	 */
	private function declarationsOfTargets( Schema $schema, RelationProperty $property ): array {
		$declarations = [];

		foreach ( $property->getTargetSchemas() as $targetReference ) {
			$targetSchema = $this->resolveTarget( $schema, $targetReference );

			if ( $targetSchema !== null ) {
				$declarations[(int)$targetSchema->subjectsAreDependent()] = true;
			}
		}

		return $declarations;
	}

	/**
	 * A Schema targeting itself targets itself as saved, not as stored.
	 */
	private function resolveTarget( Schema $schema, SchemaReference $targetReference ): ?Schema {
		if ( $targetReference->equals( SchemaReference::local( $schema->getName() ) ) ) {
			return $schema;
		}

		return $this->schemaResolver->getSchema( $targetReference );
	}

}
