<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Relation;

readonly class RelationType {

	public function __construct(
		public string $text,
	) {
	}

	public function getText(): string {
		return $this->text;
	}

}
