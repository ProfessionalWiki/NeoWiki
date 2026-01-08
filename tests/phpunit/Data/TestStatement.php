<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\RelationFormat;

class TestStatement {

	public static function build(
		string|PropertyName $property = 'TestProperty',
		string|NeoValue $value = 'TestValue',
		string $format = 'text',
	): Statement {
		return new Statement(
			property: new PropertyName( (string)$property ),
			format: $format,
			value: is_string( $value ) ? new StringValue( $value ) : $value
		);
	}

	/**
	 * @param Relation[] $relations
	 */
	public static function buildRelation( string|PropertyName $property = 'TestProperty', array $relations = [] ): Statement {
		return self::build(
			property: $property,
			value: new RelationValue( ...$relations ),
			format: RelationFormat::NAME
		);
	}

}
