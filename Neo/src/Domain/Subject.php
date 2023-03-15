<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class Subject {

	public function __construct(
		public readonly SubjectId $id,
		public readonly SubjectTypeIdList $types,
		// TODO: "fingerprint"?
		// TODO: "same as" identifiers?
		public readonly RelationList $relations,
		public readonly SubjectProperties $properties,
	) {
	}

}
