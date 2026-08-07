<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class RelationType implements PropertyType {

	public const NAME = 'relation';

	/**
	 * @param string $localSourceKey Needed to read a `targetSchema` naming this wiki's own Source as
	 *   the local reference it is (ADR 23).
	 */
	public function __construct(
		private readonly string $localSourceKey
	) {
	}

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Relation;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): RelationProperty {
		return RelationProperty::fromPartialJson( $core, $property, $this->localSourceKey );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof RelationProperty ) {
			return [];
		}

		$relations = $value instanceof RelationValue ? $value->relations : [];

		if ( $definition->isRequired() && $relations === [] ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		$violations = [];

		if ( !$definition->allowsMultipleValues() && count( $relations ) > 1 ) {
			$violations[] = new Violation( propertyName: null, code: 'single-value-only', severity: Severity::Error );
		}

		return $violations;
	}

}
