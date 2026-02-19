<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetSchemaSummariesApi extends SimpleHandler {

	public function run(): Response {
		$params = $this->getValidatedParams();
		$extension = NeoWikiExtension::getInstance();
		$schemaNameLookup = $extension->getSchemaNameLookup();
		$schemaLookup = $extension->getSchemaLookup();

		$summaries = [];

		foreach ( $schemaNameLookup->getSchemaNamesMatching( '', $params['limit'], $params['offset'] ) as $title ) {
			$schema = $schemaLookup->getSchema( new SchemaName( $title->getText() ) );

			if ( $schema === null ) {
				continue;
			}

			$summaries[] = $this->schemaToSummary( $schema );
		}

		$result = [
			'schemas' => $summaries,
			'totalRows' => $schemaNameLookup->getSchemaCount(),
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
			],
			'offset' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 0,
				IntegerDef::PARAM_MIN => 0,
			],
		];
	}

	/**
	 * @return array{name: string, description: string, propertyCount: int}
	 */
	private function schemaToSummary( Schema $schema ): array {
		return [
			'name' => $schema->getName()->getText(),
			'description' => $schema->getDescription(),
			'propertyCount' => count( $schema->getAllProperties()->asMap() ),
		];
	}

}
