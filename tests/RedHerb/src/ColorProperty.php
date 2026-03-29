<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class ColorProperty extends PropertyDefinition {

	public function __construct( PropertyCore $core ) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return ColorType::NAME;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self( core: $core );
	}

	protected function nonCoreToJson(): array {
		return [];
	}

}
