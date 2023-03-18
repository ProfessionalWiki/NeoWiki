<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class Relation {

	public function __construct(
		public readonly RelationId $id,
		public readonly RelationTypeId $type,
		public readonly SubjectId $targetId,
		public readonly RelationProperties $properties,
	) {
	}

}
