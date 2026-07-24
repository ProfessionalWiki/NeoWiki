<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class GetMappingSummariesApi extends SimpleHandler {

	use CursorPaginationTrait;

	public function run(): Response {
		$params = $this->getValidatedParams();
		$extension = NeoWikiExtension::getInstance();
		$mappingLookup = $extension->getMappingLookup();

		$page = $this->buildPage(
			$extension->getMappingNameLookup()->getReadableMappingNames( $this->pageIdFromCursor( $params['cursor'] ) ),
			$params['limit'],
			function ( MappingName $name ) use ( $mappingLookup ): ?array {
				$mapping = $mappingLookup->getMapping( $name );
				return $mapping === null ? null : $this->mappingToSummary( $mapping );
			}
		);

		$result = [
			'mappings' => $page['items'],
			'nextCursor' => $page['nextCursor'],
		];

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $result ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return $this->paginationParamSettings();
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
