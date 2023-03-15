<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class RelationId {

	public function __construct(
		public readonly string $text,
	) {
	}

}
