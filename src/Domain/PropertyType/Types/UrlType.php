<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class UrlType implements PropertyType {

	public const NAME = 'url';

	private const ALLOWED_PROTOCOLS = [ 'http:', 'https:' ];

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
			return [ new Violation( propertyName: null, code: 'required' ) ];
		}

		$violations = [];

		foreach ( $value->strings as $index => $part ) {
			$url = trim( $part );
			if ( $url !== '' && !self::isValidUrl( $url ) ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'invalid-url',
					valuePartIndex: $index,
				);
			}
		}

		if ( $definition->enforcesUniqueValues()
			&& count( array_unique( $value->strings ) ) !== count( $value->strings )
		) {
			$violations[] = new Violation( propertyName: null, code: 'unique' );
		}

		return $violations;
	}

	private static function isValidUrl( string $urlString ): bool {
		if ( preg_match( '/^([a-z][a-z\d+.-]*):\/\//i', $urlString, $protocolMatch ) === 1 ) {
			if ( !in_array( strtolower( $protocolMatch[1] ) . ':', self::ALLOWED_PROTOCOLS, true ) ) {
				return false;
			}
		}

		$pattern = '/^([a-z][a-z\d+.-]*:\/\/)?'
			. '((?:[a-z\d](?:[a-z\d-]*[a-z\d])?\.)+[a-z]{2,}|'
			. '((\d{1,3}\.){3}\d{1,3})|'
			. '(localhost))'
			. '(\:\d+)?'
			. '(\/[-a-z\d%_.~+]*)*'
			. '(\?[;&a-z\d%_.~+=-]*)?'
			. '(\#[-a-z\d_]*)?$/i';

		return preg_match( $pattern, $urlString ) === 1;
	}

}
