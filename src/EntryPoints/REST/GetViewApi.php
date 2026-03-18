<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetViewPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetViewApi extends SimpleHandler {

	public function run( string $viewName ): Response {
		$presenter = new RestGetViewPresenter();

		NeoWikiExtension::getInstance()->newGetViewQuery( $presenter )->execute( $viewName );

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $presenter->getJson() ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'viewName' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
