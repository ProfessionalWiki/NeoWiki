<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\CheckboxProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\CurrencyProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

class TestProperty {

	public static function buildText(
		string $description = '',
		bool $required = false,
		?string $default = null,
		bool $multiple = false
	): TextProperty {
		return new TextProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			multiple: $multiple,
			uniqueItems: false
		);
	}

	public static function buildRelation(
		string $description = '',
		bool $required = false,
		$default = null,
		RelationType|string $relationType = 'TestPropRelation',
		SchemaName|string $targetSchema = 'TestPropSchema',
		bool $multiple = false
	): RelationProperty {
		return new RelationProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			relationType: $relationType instanceof RelationType ? $relationType : new RelationType( $relationType ),
			targetSchema: $targetSchema instanceof SchemaName ? $targetSchema : new SchemaName( $targetSchema ),
			multiple: $multiple
		);
	}

	public static function buildCurrency(
		string $description = '',
		bool $required = false,
		float|int|null $default = null,
		string $currencyCode = 'EUR',
		float|int|null $precision = null,
		float|int|null $minimum = null,
		float|int|null $maximum = null,
	): CurrencyProperty {
		return new CurrencyProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			currencyCode: $currencyCode,
			precision: $precision,
			minimum: $minimum,
			maximum: $maximum
		);
	}

	public static function buildCheckbox(
		string $description = '',
		bool $required = false,
		?bool $default = false,
	): CheckboxProperty {
		return new CheckboxProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
		);
	}

	public static function buildUrl(
		string $description = '',
		bool $required = false,
		?string $default = null,
		bool $multiple = false
	): UrlProperty {
		return new UrlProperty(
			core: new PropertyCore(
				description: $description,
				required: $required,
				default: $default
			),
			multiple: $multiple,
			uniqueItems: false
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

}
