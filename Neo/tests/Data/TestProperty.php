<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;

class TestProperty {

	public static function buildString(
		ValueFormat $format = ValueFormat::Text,
		string $description = '',
		bool $required = false,
		?string $default = null,
		bool $multiple = false
	): StringProperty {
		return new StringProperty(
			format: $format,
			description: $description,
			required: $required,
			default: $default,
			multiple: $multiple
		);
	}

	public static function buildRelation(
		string $description = '',
		bool $required = false,
		$default = null,
		RelationType|string $relationType = 'TestPropRelation',
		SchemaId|string $targetSchema = 'TestPropSchema',
		bool $multiple = false
	): RelationProperty {
		return new RelationProperty(
			description: $description,
			required: $required,
			default: $default,
			relationType: $relationType instanceof RelationType ? $relationType : new RelationType( $relationType ),
			targetSchema: $targetSchema instanceof SchemaId ? $targetSchema : new SchemaId( $targetSchema ),
			multiple: $multiple
		);
	}

}
