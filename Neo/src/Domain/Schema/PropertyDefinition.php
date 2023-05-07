<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

abstract class PropertyDefinition {

	public function __construct(
		private readonly string $description,
		private readonly ValueType $type,
		private readonly ValueFormat $format,
	) {
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getType(): ValueType {
		return $this->type;
	}

	public function getFormat(): ValueFormat {
		return $this->format;
	}

}
