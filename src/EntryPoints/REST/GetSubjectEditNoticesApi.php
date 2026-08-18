<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectEditNoticesPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectEditNoticesApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run( int $pageId ): Response {
		$presenter = new RestGetSubjectEditNoticesPresenter();

		NeoWikiExtension::getInstance()->newGetSubjectEditNoticesQuery( $presenter, $this->getAuthority() )->execute(
			pageId: $pageId,
			schemaName: $this->getValidatedParams()['schema'],
		);

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	/**
	 * Notices depend on the viewer and on state that changes without an edit, such as approval, so
	 * they may not be stored at all. Doing this in run() would not hold: core overwrites the header
	 * afterwards whenever the session is persistent.
	 */
	public function applyCacheControl( ResponseInterface $response ): void {
		parent::applyCacheControl( $response );

		$response->setHeader( 'Cache-Control', 'private,no-store' );
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
