<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class StringProperty extends PropertyDefinition {

	public function __construct(
		ValueFormat $format,
		string $description,
		bool $required,
		?string $default,
		private readonly bool $multiple
	) {
		$this->assertIsStringFormat( $format );

		parent::__construct(
			type: ValueType::String,
			format: $format,
			description: $description,
			required: $required,
			default: $default,
		);
	}

	public function assertIsStringFormat( ValueFormat $format ): void {
		if ( !in_array( $format, [
			ValueFormat::Text,
			ValueFormat::Email,
			ValueFormat::Url,
			ValueFormat::Date,
			ValueFormat::DateTime,
			ValueFormat::Time,
			ValueFormat::PhoneNumber
		] ) ) {
			throw new InvalidArgumentException( 'StringProperty must have a string format' );
		}
	}

	public function isMultiple(): bool {
		return $this->multiple;
	}

}
