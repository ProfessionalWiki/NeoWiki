<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;

class StringProperty extends PropertyDefinition {

	public function __construct(
		string $name,
		string $description,
		ValueFormat $format,
	) {
		$this->assertIsStringFormat( $format );

		parent::__construct(
			name: $name,
			description: $description,
			type: ValueType::String,
			format: $format
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
		] ) ) {
			throw new InvalidArgumentException( 'StringProperty must have a string format' );
		}
	}

}
