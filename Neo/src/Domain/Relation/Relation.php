<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class Relation {

	public function __construct(
		public readonly RelationTypeId $type,
		public readonly SubjectId $targetId,
		public readonly RelationProperties $properties,
	) {
	}

}
