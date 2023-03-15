<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

/**
 * "Instance Of"
 */
class SubjectTypeId {

	public function __construct(
		public readonly string $text,
	) {
	}

}
