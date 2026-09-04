<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject\MoveSubjectRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\RestMoveSubjectPresenter;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;

class MoveSubjectApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run( string $subjectId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		// targetPageId is a required body param, so a request without it is refused by the
		// framework's validator before run() is entered.
		$validatedBody = $this->getValidatedBody() ?? [];

		$presenter = new RestMoveSubjectPresenter();

		try {
			NeoWikiExtension::getInstance()
				->newMoveSubjectAction( $presenter, $this->getAuthority() )
				->moveSubject( new MoveSubjectRequest(
					subjectId: $subjectId,
					targetPageId: $validatedBody['targetPageId'],
					makeMainSubject: $validatedBody['makeMainSubject'] ?? false,
					comment: $validatedBody['comment'] ?? null,
				) );
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( RuntimeException $e ) {
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
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Subject ID (15 characters, starting with "s").',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'targetPageId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID of the page to move the Subject to.',
			],
			'makeMainSubject' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Make the moved Subject the target page\'s Main Subject, demoting the page\'s current Main Subject to a child Subject.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary, used for the edit to both pages.',
			],
		];
	}

}
