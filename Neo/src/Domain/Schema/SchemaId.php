<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

class SchemaId {

	public function __construct(
		private readonly string $text,
	) {
	}

	public function getText(): string {
		return $this->text;
	}

}
