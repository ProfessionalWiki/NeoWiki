<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;

readonly class Statement {

	public function __construct(
		private PropertyName $property,
		private string $propertyType,
		private NeoValue $value
	) {
	}

	public function getPropertyName(): PropertyName {
		return $this->property;
	}

	public function getPropertyType(): string {
		return $this->propertyType;
	}

	public function getValue(): NeoValue {
		return $this->value;
	}

	/**
	 * Strict, so values that only read alike, such as "10" and "1e1", differ.
	 */
	public function equals( self $other ): bool {
		return $this->property->text === $other->property->text
			&& $this->propertyType === $other->propertyType
			&& $this->value->toScalars() === $other->value->toScalars();
	}

}
