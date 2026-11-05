<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\NeoWiki\Domain\Schema\InvalidFieldException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class BooleanProperty extends PropertyDefinition {

	public function __construct( PropertyCore $core ) {
		if ( $core->default !== null && !is_bool( $core->default ) ) {
			throw InvalidFieldException::mustBe( 'default', 'true or false', $core->default );
		}

		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return BooleanType::NAME;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self( core: $core );
	}

	public function nonCoreToJson(): array {
		return [];
	}

	public function toJsonSchema(): array {
		return [ 'type' => 'boolean' ];
	}

}
