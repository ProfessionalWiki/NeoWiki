<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectApi extends SimpleHandler {

	public function run( string $subjectId ): Response {
		$presenter = new RestGetSubjectPresenter();
		$query = NeoWikiExtension::getInstance()->newGetSubjectQuery( $presenter );

		$query->execute( $subjectId, false ); // TODO: add parameter

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
