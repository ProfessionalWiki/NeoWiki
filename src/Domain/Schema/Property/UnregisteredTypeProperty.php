<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

/**
 * Property Definition whose Property Type is not registered, because the extension owning
 * the type is disabled or failed to load. The type-specific JSON keys (Constraints and
 * Display Attributes) are kept verbatim, so the Schema still serves the property and a
 * re-save does not drop it.
 */
class UnregisteredTypeProperty extends PropertyDefinition {

	/**
	 * @param array<string, mixed> $json The property's full JSON, core fields included.
	 */
	public function __construct(
		PropertyCore $core,
		private readonly string $propertyType,
		private readonly array $json,
	) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return $this->propertyType;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			propertyType: $property['type'],
			json: $property,
		);
	}

	/**
	 * Everything the core does not already carry. Subtracting the core keys rather than
	 * listing the type-specific ones keeps unknown keys, which is the point, and cannot
	 * drift from what coreToJson() emits.
	 */
	public function nonCoreToJson(): array {
		return array_diff_key( $this->json, $this->coreToJson() );
	}

}
