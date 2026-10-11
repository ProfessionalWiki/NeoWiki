<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class Relation {

	public function __construct(
		public RelationId $id,
		public SubjectId $targetId,
	) {
	}

	public function withPropertyName( PropertyName $propertyName ): TypedRelation {
		return new TypedRelation(
			id: $this->id,
			targetId: $this->targetId,
			propertyName: $propertyName,
		);
	}

}
