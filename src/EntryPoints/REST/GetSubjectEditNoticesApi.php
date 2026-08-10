<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectEditNoticesPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectEditNoticesApi extends SimpleHandler {

	public function run( int $pageId ): Response {
		$this->giveProvidersTheirPageContext( $pageId );

		$presenter = new RestGetSubjectEditNoticesPresenter();

		NeoWikiExtension::getInstance()->newGetSubjectEditNoticesQuery( $presenter, $this->getAuthority() )->execute(
			pageId: $pageId,
			schemaName: $this->getValidatedParams()['schema'],
		);

		$response = $this->getResponseFactory()->createJson( $presenter->getJsonArray() );

		// Notices depend on the viewer and on state that changes without an edit, such as approval.
		$response->setHeader( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Providers may read the main request context rather than the context they are handed:
	 * ContentStabilization does, when describing pending revisions. Outside a page request that
	 * context carries no title, so it is set here. VisualEditor does the same for the same reason
	 * (T307852).
	 */
	private function giveProvidersTheirPageContext( int $pageId ): void {
		$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromID( $pageId );

		if ( $title !== null ) {
			RequestContext::getMain()->setTitle( $title );
		}
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
			'schema' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Name of the Schema being edited. Enables Schema-scoped notices.',
			],
		];
	}

}
