<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Serves a Schema as a JSON Schema document describing a Subject that follows it.
 *
 * A Schema whose page the caller may not read answers with the same 404 as one that does not
 * exist: the Schema lookup is the read gate, and it withholds both alike (#1046).
 */
class GetJsonSchemaApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run( string $schemaName ): Response {
		$schema = $this->findSchema( $schemaName );

		if ( $schema === null ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'status' => 'error',
				'message' => 'Schema not found: ' . $schemaName,
			] );
		}

		$document = NeoWikiExtension::getInstance()
			->newJsonSchemaSerializer( $this->getRouteUrl( [ 'schemaName' => $schema->getName()->getText() ] ) )
			->serialize( $schema );

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $document ) );
		$response->setHeader( 'Content-Type', 'application/schema+json' );

		return $response;
	}

	private function findSchema( string $schemaName ): ?Schema {
		try {
			$name = new SchemaName( $schemaName );
		} catch ( InvalidArgumentException ) {
			// A reserved name such as "page" can never be a Schema.
			return null;
		}

		return NeoWikiExtension::getInstance()->getSchemaLookup()->getSchema( $name );
	}

	public function getParamSettings(): array {
		return [
			'schemaName' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Schema name (e.g. "Person"), in any spelling that names its Schema page.',
			],
		];
	}

}
