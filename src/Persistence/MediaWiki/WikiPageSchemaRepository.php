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

			/*
			'array' => new ArrayProperty(
				name: $propertyName,
				description: $property['description'] ?? '',
				items: $this->propertyDefinitionFromJson( $propertyName, $property['items'] ), // TODO: name does not make sense here
			),
			*/

			ValueType::Number => new NumberProperty(
				description: $property['description'] ?? '',
				format: ValueFormat::from( $property['format'] ),
				minimum: $property['minimum'] ?? null,
				maximum: $property['maximum'] ?? null,
			),
		};
	}

}
