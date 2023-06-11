<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;

class BooleanProperty extends PropertyDefinition {

	public function __construct(
		ValueFormat $format,
		string $description,
		bool $required,
		?bool $default,
	) {
		$this->assertIsBooleanFormat( $format );

		parent::__construct(
			type: ValueType::Boolean,
			format: $format,
			description: $description,
			required: $required,
			default: $default
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
