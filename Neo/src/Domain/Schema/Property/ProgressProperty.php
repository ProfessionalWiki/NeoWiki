<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\ProgressFormat;

class ProgressProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly int $minimum,
		private readonly int $maximum,
		private readonly int $step,

	) {
		parent::__construct( $core );
	}

	public function getFormat(): string {
		return ProgressFormat::NAME;
	}

	public function getMinimum(): int {
		return $this->minimum;
	}

	public function getMaximum(): int {
		return $this->maximum;
	}

	public function getStep(): int {
		return $this->step;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			minimum: $property['minimum'] ?? 0,
			maximum: $property['maximum'] ?? 100,
			step: $property['step'] ?? 1,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'minimum' => $this->minimum,
			'maximum' => $this->maximum,
			'step' => $this->step,
		];
	}

}
