<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

abstract class PropertyDefinition {

	public function __construct(
		private readonly ValueType $type,
		private readonly ValueFormat $format,
		private readonly string $description,
		private readonly bool $required,
		private readonly mixed $default,
	) {
	}

	public function getType(): ValueType {
		return $this->type;
	}

	public function getFormat(): ValueFormat {
		return $this->format;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function isRequired(): bool {
		return $this->required;
	}

	public function getDefault(): mixed {
		return $this->default;
	}

	public function hasDefault(): bool {
		return $this->default !== null;
	}

	public function isMultiple(): bool {
		return false;
	}

}
