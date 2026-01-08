<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class Relation {

	public function __construct(
		public RelationId $id,
		public SubjectId $targetId,
		public RelationProperties $properties,
	) {
	}

	public function hasProperties(): bool {
		return $this->properties->map !== [];
	}

	public function withType( RelationType $type ): TypedRelation {
		return new TypedRelation(
			id: $this->id,
			targetId: $this->targetId,
			properties: $this->properties,
			type: $type,
		);
	}

}
