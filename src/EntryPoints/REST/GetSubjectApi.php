<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectApi extends SimpleHandler {

	private const string EXPAND_PAGE = 'page';
	private const string EXPAND_RELATIONS = 'relations';

	public function run( string $subjectId ): Response {
		$presenter = new RestGetSubjectPresenter();
		$query = NeoWikiExtension::getInstance()->newGetSubjectQuery( $presenter );

		$expendOptions = explode( '|', $this->getRequest()->getQueryParams()['expand'] ?? '' );

		$query->execute(
			subjectId: $subjectId,
			includePageIdentifiers: in_array( self::EXPAND_PAGE, $expendOptions ),
			includeReferencedSubjects: in_array( self::EXPAND_RELATIONS, $expendOptions )
		);

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'expand' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::EXPAND_PAGE,
					self::EXPAND_RELATIONS,
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

}
