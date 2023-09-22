<?php

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema\CreateSchemaPresenter;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use Wikimedia\ParamValidator\ParamValidator;

class CreateSchemaApi extends SimpleHandler {

	public function __construct(
		private readonly CreateSchemaPresenter $presenter,
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run( string $schemaName ): Response {
		$this->csrfValidator->verifyCsrfToken();

		NeoWikiExtension::getInstance()
			->newCreateSchemaAction( $this->presenter, $this->getAuthority() )
			->execute( $schemaName, $this->getRequest()->getBody()->getContents() );

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $this->presenter->getJson() ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
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
