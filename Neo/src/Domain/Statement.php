<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;

class Statement {

	public function __construct(
		private readonly PropertyName $property,
		private readonly string $format,
		private readonly NeoValue $value
	) {
	}

	public function getPropertyName(): PropertyName {
		return $this->property;
	}

	public function getFormat(): string {
		return $this->format;
	}

	public function getValue(): NeoValue {
		return $this->value;
	}

}
