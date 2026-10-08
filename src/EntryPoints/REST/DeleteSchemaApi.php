<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Request\WebResponse;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Title\Title;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

class DeleteSchemaApi extends SchemaPageWriteApi {

	protected function mayActOn( Title $schemaPage ): bool {
		return $schemaPage->exists( IDBAccessObject::READ_LATEST ) && parent::mayActOn( $schemaPage );
	}

	public function execute(): Response {
		$response = new Response();
		$response->setStatus( parent::execute()->getStatusCode() );

		return $response;
	}

	protected function getActionParameters(): array {
		return [
			'action' => 'delete',
			'reason' => $this->getComment(),
		];
	}

	protected function mapActionModuleResult( array $data ): array {
		$deletion = $data['delete'] ?? [];

		if ( !isset( $deletion['logid'] ) && !( $deletion['scheduled'] ?? false ) ) {
			// The module reports a deletion that a concurrent edit or deletion stopped as done, without a log entry.
			throw new LocalizedHttpException(
				new MessageValue( 'cannotdelete', [ $this->getSchemaPage()->getPrefixedText() ] ),
				409
			);
		}

		return [];
	}

	protected function mapActionModuleResponse(
		WebResponse $actionModuleResponse,
		array $actionModuleResult,
		Response $response
	): void {
		parent::mapActionModuleResponse( $actionModuleResponse, $actionModuleResult, $response );

		if ( $actionModuleResult['delete']['scheduled'] ?? false ) {
			$response->setStatus( 202 );
		}
	}

	public function getBodyParamSettings(): array {
		return [
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional reason for the deletion.',
			],
		];
	}

}
