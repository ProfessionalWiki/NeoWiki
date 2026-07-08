<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\RestSetSubjectsOrderingPresenter;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;

class SetSubjectsOrderingApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run( int $pageId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		$request = $this->buildRequest( $pageId );
		if ( $request instanceof Response ) {
			return $request;
		}

		$presenter = new RestSetSubjectsOrderingPresenter();

		try {
			NeoWikiExtension::getInstance()
				->newSetSubjectsOrderingAction( $presenter, $this->getAuthority() )
				->setOrdering( $request );
		} catch ( InvalidArgumentException $e ) {
			return $this->errorResponse( 400, $e->getMessage() );
		} catch ( RuntimeException $e ) {
			return $this->errorResponse( 403, $e->getMessage() );
		}

		$response = $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
		$response->setStatus( $presenter->getStatusCode() );
		return $response;
	}

	private function buildRequest( int $pageId ): SetSubjectsOrderingRequest|Response {
		// Use the raw parsed body so an explicit null mainSubjectId is distinguishable from
		// the field being absent. getValidatedBody() collapses both via ??.
		$parsedBody = $this->getRequest()->getParsedBody() ?? [];

		if ( !array_key_exists( 'mainSubjectId', $parsedBody ) ) {
			return $this->errorResponse( 400, 'Missing required field: mainSubjectId' );
		}

		$validatedBody = $this->getValidatedBody() ?? [];
		$childSubjectIds = $validatedBody['childSubjectIds'] ?? null;

		if ( !is_array( $childSubjectIds ) ) {
			return $this->errorResponse( 400, 'Missing required field: childSubjectIds' );
		}

		foreach ( $childSubjectIds as $id ) {
			if ( !is_string( $id ) ) {
				return $this->errorResponse( 400, 'childSubjectIds must be a list of strings' );
			}
		}

		return new SetSubjectsOrderingRequest(
			pageId: $pageId,
			mainSubjectId: $validatedBody['mainSubjectId'] ?? null,
			childSubjectIds: $childSubjectIds,
			comment: $validatedBody['comment'] ?? null,
		);
	}

	private function errorResponse( int $status, string $message ): Response {
		return $this->getResponseFactory()->createHttpError( $status, [
			'status' => 'error',
			'message' => $message,
		] );
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
			'mainSubjectId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Subject ID (bare local or source-qualified form) to be the Main Subject, or null to clear it.',
			],
			'childSubjectIds' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Ordered list of child Subject IDs. Together with mainSubjectId (if non-null) this must exactly equal the current set of Subject IDs on the page.',
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
