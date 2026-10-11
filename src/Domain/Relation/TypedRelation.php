<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class TypedRelation {

	public function __construct(
		public RelationId $id,
		public SubjectId $targetId,
		public PropertyName $propertyName,
	) {
	}

}
