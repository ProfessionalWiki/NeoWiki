<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

class DeleteSchemaApi extends SchemaPageActionApi {

	protected function mayActOn( Title $schemaPage ): bool {
		return $schemaPage->exists( IDBAccessObject::READ_LATEST ) && parent::mayActOn( $schemaPage );
	}

	protected function executeActionModule(): Response {
		parent::executeActionModule();

		return new Response();
	}

	protected function getActionParameters(): array {
		return [ 'action' => 'delete' ] + $this->getCommentAs( 'reason' );
	}

	protected function mapActionModuleResult( array $data ): array {
		return [];
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
