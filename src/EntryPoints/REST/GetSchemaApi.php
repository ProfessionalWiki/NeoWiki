<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSchemaPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSchemaApi extends SimpleHandler {

	public function run( string $schemaName ): Response {
		$presenter = new RestGetSchemaPresenter();

		NeoWikiExtension::getInstance()->newGetSchemaQuery( $presenter )->execute( $schemaName );

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $presenter->getJson() ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'schemaName' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
