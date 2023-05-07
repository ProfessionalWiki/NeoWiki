<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;

class BooleanProperty extends PropertyDefinition {

	public function __construct(
		string $description,
		ValueFormat $format,
	) {
		$this->assertIsBooleanFormat( $format );

		parent::__construct(
			description: $description,
			type: ValueType::Boolean,
			format: $format
		);
	}

	private function assertIsBooleanFormat( ValueFormat $format ): void {
		if ( !in_array( $format, [
			ValueFormat::Checkbox,
			ValueFormat::Toggle,
		] ) ) {
			throw new InvalidArgumentException( 'BooleanProperty must have a boolean format' );
		}
	}

}
