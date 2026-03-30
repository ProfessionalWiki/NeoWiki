<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class DateTimeProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly ?string $minimum,
		private readonly ?string $maximum,
	) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return DateTimeType::NAME;
	}

	public function getMinimum(): ?string {
		return $this->minimum;
	}

	public function hasMinimum(): bool {
		return $this->minimum !== null;
	}

	public function getMaximum(): ?string {
		return $this->maximum;
	}

	public function hasMaximum(): bool {
		return $this->maximum !== null;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			minimum: $property['minimum'] ?? null,
			maximum: $property['maximum'] ?? null,
		);
	}

	protected function nonCoreToJson(): array {
		return [
			'minimum' => $this->getMinimum(),
			'maximum' => $this->getMaximum(),
		];
	}

}
