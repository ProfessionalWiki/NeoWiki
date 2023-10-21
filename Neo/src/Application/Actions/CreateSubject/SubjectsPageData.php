<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

class SubjectsPageData {

	public function __construct(
		public readonly string $wikitext,
		public readonly string $subjectsJson
	) {
	}

}
