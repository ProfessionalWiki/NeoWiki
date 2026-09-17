<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType as RelationPropertyType;

class RelationProperty extends PropertyDefinition {

	private const array RELATION_JSON_SCHEMA = [
		'type' => 'object',
		'required' => [ 'target' ],
		'properties' => [
			'id' => [ 'type' => 'string' ],
			'target' => [ 'type' => 'string' ],
			'properties' => [
				'type' => 'object',
				'additionalProperties' => [ 'type' => [ 'string', 'number', 'boolean', 'null' ] ],
			],
		],
	];

	public function __construct(
		PropertyCore $core,
		private readonly RelationType $relationType,
		private readonly SchemaReference $targetSchema,
		private readonly bool $multiple

	) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return RelationPropertyType::NAME;
	}

	public function getRelationType(): RelationType {
		return $this->relationType;
	}

	public function getTargetSchema(): SchemaReference {
		return $this->targetSchema;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public static function fromPartialJson( PropertyCore $core, array $property, string $localSourceKey ): self {
		return new self(
			core: $core,
			relationType: new RelationType( $property['relation'] ?? null ), // Required field, constructor throws on null
			targetSchema: SchemaReference::fromJson( $property['targetSchema'] ?? '', $localSourceKey ), // Required field, SchemaName throws on empty
			multiple: $property['multiple'] ?? false,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'relation' => $this->relationType->getText(),
			'targetSchema' => $this->targetSchema->toJson(),
			'multiple' => $this->multiple,
		];
	}

	public function toJsonSchema(): array {
		return $this->listValueSchema( self::RELATION_JSON_SCHEMA );
	}

}
