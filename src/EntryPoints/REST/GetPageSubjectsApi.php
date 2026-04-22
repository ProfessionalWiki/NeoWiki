<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetPageSubjectsPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetPageSubjectsApi extends SimpleHandler {

	private const string EXPAND_SCHEMAS = 'schemas';
	private const string EXPAND_RELATIONS = 'relations';

	public function run( int $pageId ): Response {
		$presenter = new RestGetPageSubjectsPresenter();

		$expandOptions = explode( '|', $this->getRequest()->getQueryParams()['expand'] ?? '' );

		NeoWikiExtension::getInstance()->newGetPageSubjectsQuery( $presenter )->execute(
			pageId: $pageId,
			includeSchemas: in_array( self::EXPAND_SCHEMAS, $expandOptions, true ),
			includeReferencedSubjects: in_array( self::EXPAND_RELATIONS, $expandOptions, true ),
		);

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'expand' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::EXPAND_SCHEMAS,
					self::EXPAND_RELATIONS,
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

}
