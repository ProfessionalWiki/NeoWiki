<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetLayoutPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetLayoutApi extends SimpleHandler {

	public function run( string $layoutName ): Response {
		$presenter = new RestGetLayoutPresenter();

		NeoWikiExtension::getInstance()->newGetLayoutQuery( $presenter )->execute( $layoutName );

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $presenter->getJson() ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'layoutName' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Layout name.',
			],
		];
	}

}
