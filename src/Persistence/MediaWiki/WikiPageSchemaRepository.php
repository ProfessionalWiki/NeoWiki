<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use RuntimeException;

class WikiPageSchemaRepository implements SchemaRepository {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
	) {
	}

	public function getSchema( SchemaId $schemaName ): ?Schema {
		$content = $this->getContent( $schemaName );

		if ( $content === null ) {
			return null;
		}

		$json = json_decode( $content->getText(), true );

		return new Schema(
			id: $schemaName,
			description: $json['description'] ?? '',
			properties: $this->propertiesFromJson( $json ),
		);
	}

	private function getContent( SchemaId $schemaName ): ?SchemaContent {
		$content = $this->pageContentFetcher->getPageContent( $schemaName->getText(), NS_NEOWIKI_SCHEMA );

		if ( $content instanceof SchemaContent ) {
			return $content;
		}

		return null;
	}

	private function propertiesFromJson( array $json ): PropertyDefinitions {
		$properties = [];

		foreach ( $json['propertyDefinitions'] ?? [] as $propertyName => $property ) {
			$properties[] = $this->propertyDefinitionFromJson( $propertyName, $property );
		}

		return new PropertyDefinitions( ...$properties );
	}

	private function propertyDefinitionFromJson( string $propertyName, array $property ): PropertyDefinition {
		return match ( $property['type'] ) {
			// TODO: handle all types

			/*
			'array' => new ArrayProperty(
				name: $propertyName,
				description: $property['description'] ?? '',
				items: $this->propertyDefinitionFromJson( $propertyName, $property['items'] ), // TODO: name does not make sense here
			),
			*/

			'number' => new NumberProperty(
				name: $propertyName,
				description: $property['description'] ?? '',
				format: $this->newValueFormat( $property['format'] ),
				minimum: $property['minimum'] ?? null,
				maximum: $property['maximum'] ?? null,
			),

			default => throw new RuntimeException( 'Unknown property type: ' . $property['type'] ),
		};
	}

	private function newValueFormat( string $format ): ValueFormat {
		$map = [
			'text' => ValueFormat::Text,

			'email' => ValueFormat::Email,
			'url' => ValueFormat::Url,
			'phoneNumber' => ValueFormat::PhoneNumber,

			'date' => ValueFormat::Date,
			'time' => ValueFormat::Time,
			'dateTime' => ValueFormat::DateTime,
			'duration' => ValueFormat::Duration,

			'percentage' => ValueFormat::Percentage,
			'currency' => ValueFormat::Currency,
			'slider' => ValueFormat::Slider,

			'checkbox' => ValueFormat::Checkbox,
			'toggle' => ValueFormat::Toggle,
		];

		if ( array_key_exists( $format, $map ) ) {
			return $map[$format];
		}

		throw new RuntimeException( 'Unknown number format: ' . $format );
	}

}
