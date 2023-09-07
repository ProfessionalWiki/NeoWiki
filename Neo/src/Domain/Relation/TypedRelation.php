<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class TypedRelation {

	public function __construct(
		public readonly RelationId $id,
		public readonly SubjectId $targetId,
		public readonly RelationProperties $properties,
		public readonly RelationType $type,
	) {
	}

}
