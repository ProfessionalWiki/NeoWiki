<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteSubjectApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function run( string $subjectId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		// getValidatedBody() returns null when the request has no body.
		$body = $this->getValidatedBody() ?? [];
		$comment = $body['comment'] ?? null;

		$extension = NeoWikiExtension::getInstance();

		try {
			$extension->newDeleteSubjectAction( $this->getAuthority() )->deleteSubject(
				$extension->getSubjectIdParser()->parse( $subjectId ),
				$comment
			);
		} catch ( \RuntimeException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		return new Response();
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject: a bare local id ("s" + 14 characters) or a source-qualified id ("source:localId").',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
		];
	}

}
