<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Api\IApiMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Handler\ActionModuleBasedHandler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\ResponseException;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Changes a Schema page through one of MediaWiki's action modules rather than a NeoWiki save path, so that
 * the change is authorized, validated and filtered exactly like editing or deleting the page in MediaWiki.
 */
abstract class SchemaPageActionApi extends ActionModuleBasedHandler {

	/**
	 * The action modules report a refusal by error code only. These codes fit a status other than 400.
	 */
	private const array STATUS_BY_ERROR_CODE = [
		'autoblocked' => 403,
		'bigdelete' => 403,
		'blocked' => 403,
		'cantcreate' => 403,
		'cantcreate-anon' => 403,
		'cantchangecontentmodel' => 403,
		'cantedit' => 403,
		'cascadeprotected' => 403,
		'confirmemail' => 403,
		'permissiondenied' => 403,
		'protectednamespace' => 403,
		'protectedpage' => 403,
		'protectedtitle' => 403,
		'missingtitle' => 404,
		'editconflict' => 409,
		'ratelimited' => 429,
		'readonly' => 503,
	];

	private Title $schemaPage;

	public function __construct(
		private readonly CsrfValidator $csrfValidator,
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function execute(): Response {
		$this->csrfValidator->verifyCsrfToken();

		$schemaPage = $this->newSchemaPage();

		if ( $schemaPage === null ) {
			$this->refuse( 400, 'Invalid Schema name: ' . $this->getSchemaName() );
		}

		if ( !$this->mayActOn( $schemaPage ) ) {
			$this->refuse( 404, 'Schema not found: ' . $this->getSchemaName() );
		}

		$this->schemaPage = $schemaPage;

		return parent::execute();
	}

	private function getSchemaName(): string {
		return $this->getValidatedParams()['schemaName'];
	}

	private function newSchemaPage(): ?Title {
		$pageTitle = NeoWikiExtension::getInstance()->getPageIdentifiersResolver()->getTitleInNamespace(
			NeoWikiExtension::NS_SCHEMA,
			$this->getSchemaName()
		);

		return $pageTitle === null ? null : MediaWikiServices::getInstance()->getTitleFactory()->newFromText( $pageTitle );
	}

	/**
	 * A Schema the caller may not read is answered exactly like one that does not exist.
	 */
	protected function mayActOn( Title $schemaPage ): bool {
		return NeoWikiExtension::getInstance()
			->newPageReadAuthorizer( $this->getAuthority() )
			->authorizeReadByPageTitle( $schemaPage );
	}

	/**
	 * @throws ResponseException
	 */
	private function refuse( int $status, string $message ): never {
		throw new ResponseException( $this->getResponseFactory()->createHttpError( $status, [
			'status' => 'error',
			'message' => $message,
		] ) );
	}

	protected function getSchemaPage(): Title {
		return $this->schemaPage;
	}

	protected function getActionModuleParameters(): array {
		return $this->getActionParameters() + [
			'title' => $this->schemaPage->getPrefixedDBkey(),
			// The X-CSRF-TOKEN header was verified in execute(); the action module checks a token of its own.
			'token' => $this->getUser()->getEditToken(),
		];
	}

	/**
	 * The action module to run and its parameters, other than the page and the token. A null parameter is
	 * left out, so that the module's own default applies, such as the generated deletion reason.
	 *
	 * @return array<string, ?string>
	 */
	abstract protected function getActionParameters(): array;

	protected function getComment(): ?string {
		return ( $this->getValidatedBody() ?? [] )['comment'] ?? null;
	}

	protected function throwHttpExceptionForActionModuleError( IApiMessage $msg, $statusCode = 400 ): never {
		parent::throwHttpExceptionForActionModuleError(
			$msg,
			self::STATUS_BY_ERROR_CODE[$msg->getApiCode()] ?? $statusCode
		);
	}

	public function getParamSettings(): array {
		return [
			'schemaName' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Schema name (e.g. "Person"), in any spelling that names its Schema page.',
			],
		];
	}

}
