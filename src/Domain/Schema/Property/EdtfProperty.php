<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\EdtfType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class EdtfProperty extends PropertyDefinition {

	public function __construct( PropertyCore $core ) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return EdtfType::NAME;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self( $core );
	}

	public function nonCoreToJson(): array {
		return [];
	}

	public function toJsonSchema(): array {
		return $this->listValueSchema( [ 'type' => 'string' ] );
	}

}
