<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\DateFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\DateTimeFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\EmailFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\PhoneNumberFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\TextFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\TimeFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\UrlFormat;

class StringProperty extends PropertyDefinition {

	public function __construct(
		string $format,
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

	public function assertIsStringFormat( string $format ): void {
		if ( !in_array( $format, [
			TextFormat::NAME,
			EmailFormat::NAME,
			UrlFormat::NAME,
			DateFormat::NAME,
			DateTimeFormat::NAME,
			TimeFormat::NAME,
			PhoneNumberFormat::NAME
		] ) ) {
			throw new InvalidArgumentException( 'StringProperty must have a string format' );
		}
	}

	public function isMultiple(): bool {
		return $this->multiple;
	}

}
