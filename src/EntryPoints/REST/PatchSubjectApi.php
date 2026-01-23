<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;

class PatchSubjectApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function run( string $subjectId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		// TODO: format validation?
		$request = json_decode( $this->getRequest()->getBody()->getContents(), true );

		// TODO: replace try-catch with presenter. See CreateSubjectApi for example.
		try {
			NeoWikiExtension::getInstance()->newPatchSubjectAction( $this->getAuthority() )->patch(
				new SubjectId( $subjectId ),
				$request['label'] ?? null,
				$request['statements'], // TODO: support property removal. https://github.com/ProfessionalWiki/NeoWiki/issues/280
				$request['comment'] ?? null
			);
		} catch ( \RuntimeException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		return new Response( json_encode( $request ) );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

}
