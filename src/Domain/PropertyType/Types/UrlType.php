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
	 * Non-ASCII text is matched only by classes that take every non-ASCII character, and never
	 * counted, so that the validator, which matches bytes, and ECMA-262, which matches code units or
	 * code points, accept the same non-ASCII text.
	 */
	public const string URL_PATTERN = '^(?:[Hh][Tt][Tt][Pp][Ss]?://)?'
		. '(?:(?:' . self::DOMAIN_LABEL . '\.)+' . self::TOP_LEVEL_DOMAIN
		. '|(?:[0-9]{1,3}\.){3}[0-9]{1,3}'
		. '|\[[0-9A-Fa-f:.]+\]'
		. '|[Ll][Oo][Cc][Aa][Ll][Hh][Oo][Ss][Tt])'
		. '(?::[0-9]+)?'
		. '(?:[/?#][^\x00-\x20\x7F<>"\\\\]*)?$';

	/**
	 * Built from single negated classes rather than alternations, which exhaust PCRE's JIT stack on a
	 * host of a few thousand characters.
	 */
	private const string DOMAIN_LABEL = self::LETTER_DIGIT_OR_NON_ASCII
		. '(?:' . self::LETTER_DIGIT_HYPHEN_OR_NON_ASCII . '*' . self::LETTER_DIGIT_OR_NON_ASCII . ')?';

	private const string LETTER_DIGIT_OR_NON_ASCII = '[^\x00-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F]';

	private const string LETTER_DIGIT_HYPHEN_OR_NON_ASCII = '[^\x00-\x2C\x2E\x2F\x3A-\x40\x5B-\x60\x7B-\x7F]';

	/**
	 * A non-ASCII top-level domain holds no ASCII letters, so that a quotation mark pasted right after
	 * `.com` does not pass as part of it.
	 */
	private const string TOP_LEVEL_DOMAIN = '(?:[A-Za-z]{2,}'
		. '|[Xx][Nn]--[A-Za-z0-9-]*[A-Za-z0-9]'
		. '|[^\x00-\x7F]+)';

	private const string REGEX = '@' . self::URL_PATTERN . '@';

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
		return preg_match( self::REGEX, $urlString ) === 1;
	}

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof StringValue ? $value->strings : [];
	}

}
