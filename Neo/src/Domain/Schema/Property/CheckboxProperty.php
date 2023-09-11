<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\CheckboxFormat;

class CheckboxProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
	) {
		parent::__construct( $core );
	}

	public function getFormat(): string {
		return CheckboxFormat::NAME;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
		);
	}

	public function nonCoreToJson(): array {
		return [];
	}

}
