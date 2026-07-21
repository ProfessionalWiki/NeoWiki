<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetMappingSummariesApi extends SimpleHandler {

	public function run(): Response {
		$params = $this->getValidatedParams();
		$extension = NeoWikiExtension::getInstance();
		$mappingLookup = $extension->getMappingLookup();

		// The Mapping name lookup is shared with the RDF-projection path and exposes no SQL-level
		// pagination or count. With one Mapping page per target ontology the name list stays small,
		// so slicing it here beats widening that interface.
		$names = $extension->getMappingNameLookup()->getMappingNames();

		$summaries = [];

		foreach ( array_slice( $names, $params['offset'], $params['limit'] ) as $name ) {
			$mapping = $mappingLookup->getMapping( $name );

			if ( $mapping === null ) {
				continue;
			}

			$summaries[] = $this->mappingToSummary( $mapping );
		}

		$result = [
			'mappings' => $summaries,
			'totalRows' => count( $names ),
		];

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $result ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of items to return.',
			],
			'offset' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 0,
				IntegerDef::PARAM_MIN => 0,
				self::PARAM_DESCRIPTION => 'Zero-based index of the first item to return.',
			],
		];
	}

	/**
	 * @return array{name: string, schemas: list<string>}
	 */
	private function mappingToSummary( Mapping $mapping ): array {
		$schemaNames = array_keys( $mapping->schemas );
		sort( $schemaNames, SORT_STRING );

		return [
			'name' => $mapping->name->getText(),
			'schemas' => $schemaNames,
		];
	}

}
