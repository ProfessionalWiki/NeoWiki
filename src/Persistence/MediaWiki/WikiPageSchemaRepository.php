<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\ArrayProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;

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
			$properties[$propertyName] = $this->propertyDefinitionFromJson( $property );
		}

		return new PropertyDefinitions( $properties );
	}

	private function propertyDefinitionFromJson( array $property ): PropertyDefinition {
		return match ( ValueType::from( $property['type'] ) ) {
			// TODO: handle all types

			ValueType::Array => new ArrayProperty(
				description: $property['description'] ?? '',
				itemDefinition: $this->propertyDefinitionFromJson( $property['items'] ),
			),

			ValueType::Number => new NumberProperty(
				format: ValueFormat::from( $property['format'] ),
				description: $property['description'] ?? '',
				minimum: $property['minimum'] ?? null,
				maximum: $property['maximum'] ?? null,
			),

			ValueType::String => new StringProperty(
				format: ValueFormat::from( $property['format'] ),
				description: $property['description'] ?? '',
			),
		};
	}

}
