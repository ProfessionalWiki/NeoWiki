<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectEditNoticesPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectEditNoticesApi extends SimpleHandler {

	public function run( int $pageId ): Response {
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

	public function needsWriteAccess(): bool {
		return false;
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
