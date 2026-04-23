<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\RestSetMainSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class SetMainSubjectApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run( int $pageId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		// getValidatedBody() collapses an explicit null to "absent" via ??, so we can't use it to
		// tell "subjectId not sent" apart from "subjectId sent as null". Read the raw parsed body
		// to detect presence, then read validated values for the typed fields.
		$parsedBody = $this->getRequest()->getParsedBody() ?? [];

		if ( !array_key_exists( 'subjectId', $parsedBody ) ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => 'Missing required field: subjectId',
			] );
		}

		$validatedBody = $this->getValidatedBody() ?? [];
		'@phan-var array $validatedBody';

		// For a non-null subjectId the validator guarantees it is a string; for an explicit null
		// the field is absent from the validated body. Either way, reading through the validated
		// path keeps every value pulled from the typed side.
		$subjectId = $validatedBody['subjectId'] ?? null;
		$comment = $validatedBody['comment'] ?? null;

		$presenter = new RestSetMainSubjectPresenter();

		try {
			NeoWikiExtension::getInstance()
				->newSetMainSubjectAction( $presenter, $this->getAuthority() )
				->setMainSubject( new SetMainSubjectRequest(
					pageId: $pageId,
					subjectId: $subjectId,
					comment: $comment,
				) );
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( \RuntimeException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		$response = $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
		$response->setStatus( $presenter->getStatusCode() );
		return $response;
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Subject ID (15 characters, starting with "s") to promote to Main Subject, or null to clear the Main Subject.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
		];
	}

}
