<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\RelationFormat;

class RelationProperty extends PropertyDefinition {

	public function __construct(
		string $description,
		bool $required,
		mixed $default, // TODO: type
		private readonly RelationType $relationType,
		private readonly SchemaName $targetSchema,
		private readonly bool $multiple
	) {
		parent::__construct(
			type: ValueType::Relation,
			format: RelationFormat::NAME,
			description: $description,
			required: $required,
			default: $default
		);
	}

	public function getRelationType(): RelationType {
		return $this->relationType;
	}

	public function getTargetSchema(): SchemaName {
		return $this->targetSchema;
	}

	public function isMultiple(): bool {
		return $this->multiple;
	}

}
