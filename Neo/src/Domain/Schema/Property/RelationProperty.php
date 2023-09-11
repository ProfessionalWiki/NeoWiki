<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\RelationFormat;

class RelationProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly RelationType $relationType,
		private readonly SchemaName $targetSchema,
		private readonly bool $multiple

	) {
		parent::__construct( $core );
	}

	public function getFormat(): string {
		return RelationFormat::NAME;
	}

	public function getRelationType(): RelationType {
		return $this->relationType;
	}

	public function getTargetSchema(): SchemaName {
		return $this->targetSchema;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			relationType: new RelationType( $property['relation'] ),
			targetSchema: new SchemaName( $property['targetSchema'] ),
			multiple: $property['multiple'] ?? false,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'relation' => $this->relationType->getText(),
			'targetSchema' => $this->targetSchema->getText(),
			'multiple' => $this->multiple,
		];
	}

}
