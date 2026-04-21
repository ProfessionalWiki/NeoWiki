<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetPageSubjectsPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetPageSubjectsApi extends SimpleHandler {

	public function run( int $pageId ): Response {
		$presenter = new RestGetPageSubjectsPresenter();

		NeoWikiExtension::getInstance()->newGetPageSubjectsQuery( $presenter )->execute( $pageId );

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
