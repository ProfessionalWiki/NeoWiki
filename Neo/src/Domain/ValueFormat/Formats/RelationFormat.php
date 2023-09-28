<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class RelationFormat implements ValueFormat {

	public const NAME = 'relation';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Relation;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): RelationProperty {
		return RelationProperty::fromPartialJson( $core, $property );
	}

	public function buildNeo4jValue( NeoValue $value ): mixed {
		return ValueFormat::NO_NEO4J_VALUE;
	}

}
