<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;

class RelationProperty extends PropertyDefinition {

	private const array RELATION_JSON_SCHEMA = [
		'type' => 'object',
		'required' => [ 'target' ],
		'properties' => [
			'id' => [ 'type' => 'string' ],
			'target' => [ 'type' => 'string' ],
		],
	];

	/**
	 * @param list<SchemaReference> $targetSchemas
	 *
	 * @throws InvalidArgumentException When no target Schema is given.
	 */
	public function __construct(
		PropertyCore $core,
		private readonly array $targetSchemas,
		private readonly bool $multiple

	) {
		if ( $targetSchemas === [] ) {
			throw new InvalidArgumentException( 'A relation property needs at least one target Schema' );
		}

		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return RelationType::NAME;
	}

	/**
	 * Whether a Subject of this Schema can be the target of a Relation of this property.
	 */
	public function hasTargetSchema( SchemaReference $schema ): bool {
		foreach ( $this->targetSchemas as $targetSchema ) {
			if ( $targetSchema->equals( $schema ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return list<SchemaReference>
	 */
	public function getTargetSchemas(): array {
		return $this->targetSchemas;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	/**
	 * @throws InvalidArgumentException When the target Schemas are not a non-empty list of Schema references.
	 */
	public static function fromPartialJson( PropertyCore $core, array $property, SchemaReferenceParser $schemaReferenceParser ): self {
		$targetSchemas = $property['targetSchemas'] ?? self::singleTargetSchema( $property );

		if ( !is_array( $targetSchemas ) || !array_is_list( $targetSchemas ) ) {
			throw new InvalidArgumentException( 'The target Schemas of a relation property must be a list' );
		}

		return new self(
			core: $core,
			targetSchemas: self::withoutRepeats( array_map( $schemaReferenceParser->fromJson( ... ), $targetSchemas ) ),
			multiple: $property['multiple'] ?? false,
		);
	}

	/**
	 * Schemas saved before a relation property could list several target Schemas hold its one target in `targetSchema`.
	 */
	private static function singleTargetSchema( array $property ): ?array {
		return array_key_exists( 'targetSchema', $property ) ? [ $property['targetSchema'] ] : null;
	}

	/**
	 * Spellings that name one Schema, such as `person` and `Person`, read as that Schema once.
	 *
	 * @param list<SchemaReference> $references
	 * @return list<SchemaReference>
	 */
	private static function withoutRepeats( array $references ): array {
		$distinct = [];

		foreach ( $references as $reference ) {
			foreach ( $distinct as $kept ) {
				if ( $kept->equals( $reference ) ) {
					continue 2;
				}
			}

			$distinct[] = $reference;
		}

		return $distinct;
	}

	public function nonCoreToJson(): array {
		return [
			'targetSchemas' => array_map(
				static fn ( SchemaReference $targetSchema ): string|array => $targetSchema->toJson(),
				$this->targetSchemas
			),
			'multiple' => $this->multiple,
		];
	}

	public function toJsonSchema(): array {
		return $this->listValueSchema( self::RELATION_JSON_SCHEMA );
	}

}
