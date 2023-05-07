<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;

class RelationProperty extends PropertyDefinition {

	public function __construct(
		string $description,
		private readonly SchemaId $targetSchema,
	) {
		parent::__construct(
			type: ValueType::Relation,
			format: ValueFormat::Relation,
			description: $description
		);
	}

	public function getTargetSchema(): SchemaId {
		return $this->targetSchema;
	}

}
