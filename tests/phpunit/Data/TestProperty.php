<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;

class TestProperty {

	/**
	 * @param array<string, Severity> $constraintSeverities
	 */
	public static function buildText(
		string $description = '',
		bool $required = false,
		?string $default = null,
		bool $multiple = false,
		bool $uniqueItems = false,
		?int $minLength = null,
		?int $maxLength = null,
		array $constraintSeverities = []
	): TextProperty {
		return new TextProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default,
				constraintSeverities: $constraintSeverities
			),
			multiple: $multiple,
			uniqueItems: $uniqueItems,
			minLength: $minLength,
			maxLength: $maxLength
		);
	}

	public static function buildRelation(
		string $description = '',
		bool $required = false,
		$default = null,
		RelationType|string $relationType = 'TestPropRelation',
		SchemaName|SchemaReference|string $targetSchema = 'TestPropSchema',
		bool $multiple = false
	): RelationProperty {
		return new RelationProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			relationType: $relationType instanceof RelationType ? $relationType : new RelationType( $relationType ),
			targetSchema: TestSchema::reference( $targetSchema ),
			multiple: $multiple
		);
	}

	public static function buildUrl(
		string $description = '',
		bool $required = false,
		?string $default = null,
		bool $multiple = false,
		bool $uniqueItems = false
	): UrlProperty {
		return new UrlProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			multiple: $multiple,
			uniqueItems: $uniqueItems
		);
	}

	public static function buildNumber(
		string $description = '',
		bool $required = false,
		float|int|null $default = null,
		float|int|null $precision = null,
		float|int|null $minimum = null,
		float|int|null $maximum = null,
	): NumberProperty {
		return new NumberProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			precision: $precision,
			minimum: $minimum,
			maximum: $maximum
		);
	}

	public static function buildBoolean(
		string $description = '',
		bool $required = false,
		?bool $default = null,
	): BooleanProperty {
		return new BooleanProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
		);
	}

	/**
	 * @param SelectOption[] $options
	 */
	public static function buildSelect(
		string $description = '',
		bool $required = false,
		?string $default = null,
		array $options = [],
		bool $multiple = false,
	): SelectProperty {
		return new SelectProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			options: $options,
			multiple: $multiple
		);
	}

	public static function buildDate(
		string $description = '',
		bool $required = false,
		?string $default = null,
		?string $minimum = null,
		?string $maximum = null,
	): DateProperty {
		return new DateProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			minimum: $minimum,
			maximum: $maximum
		);
	}

	public static function buildDateTime(
		string $description = '',
		bool $required = false,
		?string $default = null,
		?string $minimum = null,
		?string $maximum = null,
	): DateTimeProperty {
		return new DateTimeProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			minimum: $minimum,
			maximum: $maximum
		);
	}

}
