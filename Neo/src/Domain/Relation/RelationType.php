<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

class RelationType {

	public function __construct(
		public readonly string $text,
	) {
	}

	public function getText(): string {
		return $this->text;
	}

}
