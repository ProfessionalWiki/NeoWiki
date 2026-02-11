<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class GetSchemaSummariesApi extends SimpleHandler {

	public function run(): Response {
		$extension = NeoWikiExtension::getInstance();
		$schemaNameLookup = $extension->getSchemaNameLookup();
		$schemaLookup = $extension->getSchemaLookup();

		$summaries = [];

		foreach ( $schemaNameLookup->getSchemaNamesMatching( '' ) as $title ) {
			$schema = $schemaLookup->getSchema( new SchemaName( $title->getText() ) );

			if ( $schema === null ) {
				continue;
			}

			$summaries[] = [
				'name' => $schema->getName()->getText(),
				'description' => $schema->getDescription(),
				'propertyCount' => count( $schema->getAllProperties()->asMap() ),
			];
		}

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $summaries ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

}
