<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Application\Actions\SaveSchema\SaveSchemaAction;
use ProfessionalWiki\NeoWiki\Application\Actions\SaveSchema\SaveSchemaPresenter;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use Wikimedia\ParamValidator\ParamValidator;

class SaveSchemaApi extends SimpleHandler {

	public function __construct(
		private readonly SaveSchemaPresenter $saveSchemaPresenter,
		private readonly SaveSchemaAction $saveSchemaAction,
		private readonly CsrfValidator $csrfValidator,
		private readonly bool $isFirstSaving
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function run( string $schemaName ): Response {
		$this->csrfValidator->verifyCsrfToken();

		if ( $this->validateAuthorPermissions() ) {
			$this->saveSchemaAction->saveSchema( $schemaName, $this->getRequest(), $this->isFirstSaving );
		}

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $this->saveSchemaPresenter->getJson() ) );
		$response->setStatus( $this->getStatus() );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	private function validateAuthorPermissions(): bool {
		$authorizer = NeoWikiExtension::getInstance()->newAuthorityBasedSchemaActionAuthorizer( $this->getAuthority() );

		if ( $this->isFirstSaving && !$authorizer->canAddSchema() ) {
			$this->saveSchemaPresenter->presentError( 'You do not have the necessary permissions to create the schema' );
			return false;
		}

		if ( !$authorizer->canEditSchema() ) {
			$this->saveSchemaPresenter->presentError( 'You do not have the necessary permissions to edit the schema' );
			return false;
		}

		return true;
	}

	private function getStatus(): int {
		$responseData = $this->saveSchemaPresenter->getJsonArray();
		if (
			empty( $responseData ) ||
			( isset( $responseData[ 'success' ] ) &&
			$responseData[ 'success' ] === false )
		) {
			return 403;
		} else {
			if ( $this->isFirstSaving ) {
				return 201;
			}
			return 200;
		}
	}

	public function getParamSettings(): array {
		return [
			'schemaName' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
