<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\CheckboxFormat;

class BooleanProperty extends PropertyDefinition {

	public function __construct(
		string $format,
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

	private function assertIsBooleanFormat( string $format ): void {
		if ( $format !== CheckboxFormat::NAME ) {
			throw new InvalidArgumentException( 'BooleanProperty must have a boolean format' );
		}
	}

}
