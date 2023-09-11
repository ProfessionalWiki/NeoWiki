<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\EmailFormat;

class EmailProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly bool $multiple,
		private readonly bool $uniqueItems,
	) {
		parent::__construct( $core );
	}

	public function getFormat(): string {
		return EmailFormat::NAME;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public function enforcesUniqueValues(): bool {
		return $this->uniqueItems;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			multiple: $property['multiple'] ?? false,
			uniqueItems: $property['uniqueItems'] ?? false,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'multiple' => $this->allowsMultipleValues(),
			'uniqueItems' => $this->enforcesUniqueValues(),
		];
	}

}
