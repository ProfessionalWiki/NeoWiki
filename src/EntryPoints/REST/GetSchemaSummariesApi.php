<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

class GetSchemaSummariesApi extends SimpleHandler {

	use CursorPaginationTrait;
	use ReadOnlyEndpoint;

	public function run(): Response {
		$params = $this->getValidatedParams();
		$extension = NeoWikiExtension::getInstance();
		$schemaLookup = $extension->getSchemaLookup();

		$page = $this->buildPage(
			$extension->getSchemaNameLookup()->getReadableSchemaNames(
				$params['search'],
				$this->nameFromCursor( $params['cursor'] )
			),
			$params['limit'],
			function ( TitleValue $title ) use ( $schemaLookup ): ?array {
				$schema = $schemaLookup->getSchema( new SchemaName( $title->getText() ) );
				return $schema === null ? null : $this->schemaToSummary( $schema );
			}
		);

		$result = [
			'schemas' => $page['items'],
			'nextCursor' => $page['nextCursor'] === null ? null : $this->cursorForName( $page['nextCursor'] ),
		];

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $result ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'search' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
				self::PARAM_DESCRIPTION => 'Keeps the Schemas whose name contains it, in any case.',
			],
			...$this->paginationParamSettings(),
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
