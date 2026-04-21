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

		$body = json_decode( $this->getRequest()->getBody()->getContents(), true );

		if ( !is_array( $body ) || !array_key_exists( 'subjectId', $body ) ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => 'Missing required field: subjectId',
			] );
		}

		$subjectId = $body['subjectId'];

		if ( $subjectId !== null && !is_string( $subjectId ) ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => 'subjectId must be a string or null',
			] );
		}

		$comment = isset( $body['comment'] ) && is_string( $body['comment'] ) ? $body['comment'] : null;

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
			],
		];
	}

}
