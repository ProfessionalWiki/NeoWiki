<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

class GetSchemaNamesApi extends SimpleHandler {

	public function run( string $search ): Response {
		$schemas = NeoWikiExtension::getInstance()->newGetSchemaNamesQuery()->execute( $search );

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $schemas ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'search' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			],
		];
	}

}
