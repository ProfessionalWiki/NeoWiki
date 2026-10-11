<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

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

	public function __construct(
		PropertyCore $core,
		private readonly SchemaReference $targetSchema,
		private readonly bool $multiple

	) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return RelationType::NAME;
	}

	public function getTargetSchema(): SchemaReference {
		return $this->targetSchema;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public static function fromPartialJson( PropertyCore $core, array $property, SchemaReferenceParser $schemaReferenceParser ): self {
		return new self(
			core: $core,
			targetSchema: $schemaReferenceParser->fromJson( $property['targetSchema'] ?? '' ), // Required field, SchemaName throws on empty
			multiple: $property['multiple'] ?? false,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'targetSchema' => $this->targetSchema->toJson(),
			'multiple' => $this->multiple,
		];
	}

	public function toJsonSchema(): array {
		return $this->listValueSchema( self::RELATION_JSON_SCHEMA );
	}

}
