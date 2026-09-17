<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class UrlType implements PropertyType {

	public const NAME = 'url';

	/**
	 * A scheme is optional and, when present, http or https. Carries no delimiters and stays within
	 * ECMA-262, so that the JSON Schema documents can use it as a `pattern`. A `pattern` takes no
	 * flags, and the inline `(?i)` and `(?i:...)` are a syntax error in most ECMA-262 engines and
	 * match beyond ASCII where they parse, so the case rules are spelled out rather than left to `/i`.
	 */
	public const string URL_PATTERN = '^(?:[Hh][Tt][Tt][Pp][Ss]?://)?'
		. '(?:(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}'
		. '|(?:[0-9]{1,3}\.){3}[0-9]{1,3}'
		. '|[Ll][Oo][Cc][Aa][Ll][Hh][Oo][Ss][Tt])'
		. '(?::[0-9]+)?'
		. '(?:/[-A-Za-z0-9%_.~+]*)*'
		. '(?:\?[;&A-Za-z0-9%_.~+=-]*)?'
		. '(?:#[-A-Za-z0-9_]*)?$';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): UrlProperty {
		return UrlProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue ) {
			return [];
		}

		if ( !$definition instanceof UrlProperty ) {
			return [];
		}

		$hasContent = false;
		foreach ( $value->strings as $part ) {
			if ( trim( $part ) !== '' ) {
				$hasContent = true;
				break;
			}
		}

		if ( $definition->isRequired() && !$hasContent ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		$violations = [];

		foreach ( $value->strings as $index => $part ) {
			$url = trim( $part );
			if ( $url !== '' && !self::isValidUrl( $url ) ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'invalid-url',
					valuePartIndex: $index,
					severity: Severity::Error,
				);
			}
		}

		if ( $definition->enforcesUniqueValues()
			&& count( array_unique( $value->strings ) ) !== count( $value->strings )
		) {
			$violations[] = new Violation( propertyName: null, code: 'unique', severity: $definition->severityOf( 'uniqueItems' ) );
		}

		return $violations;
	}

	private static function isValidUrl( string $urlString ): bool {
		return preg_match( '@' . self::URL_PATTERN . '@', $urlString ) === 1;
	}

}
