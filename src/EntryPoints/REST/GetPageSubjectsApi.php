<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetPageSubjectsPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetPageSubjectsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	private const string EXPAND_SCHEMAS = 'schemas';
	private const string EXPAND_RELATIONS = 'relations';

	public function run( int $pageId ): Response {
		$presenter = new RestGetPageSubjectsPresenter();

		$expandOptions = explode( '|', $this->getRequest()->getQueryParams()['expand'] ?? '' );

		$this->newQuery( $presenter )->execute(
			pageId: $pageId,
			includeSchemas: in_array( self::EXPAND_SCHEMAS, $expandOptions, true ),
			includeReferencedSubjects: in_array( self::EXPAND_RELATIONS, $expandOptions, true ),
		);

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	private function newQuery( RestGetPageSubjectsPresenter $presenter ): GetPageSubjectsQuery {
		if ( $this->getValidatedParams()['latest'] ) {
			return NeoWikiExtension::getInstance()->newGetLatestPageSubjectsQuery( $presenter, $this->getAuthority() );
		}

		return NeoWikiExtension::getInstance()->newGetPageSubjectsQuery( $presenter, $this->getAuthority() );
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
			'latest' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
				self::PARAM_DESCRIPTION => 'Return the Subjects of the page\'s current revision, for editing, rather than the revision the wiki publishes. Requires the viewer to be allowed to see that revision.',
			],
			'expand' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::EXPAND_SCHEMAS,
					self::EXPAND_RELATIONS,
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Embed related data in the response. Accepted values: "schemas", "relations".',
			],
		];
	}

}
