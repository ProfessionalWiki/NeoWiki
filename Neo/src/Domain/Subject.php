<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class Subject {

	public function __construct(
		private SubjectId $id,
		private SubjectData $data,
	) {
	}

}
