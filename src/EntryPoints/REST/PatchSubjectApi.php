<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
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

		$body = $this->getValidatedBody();

		// TODO: replace try-catch with presenter. See CreateSubjectApi for example.
		try {
			NeoWikiExtension::getInstance()->newPatchSubjectAction( $this->getAuthority() )->patch(
				new SubjectId( $subjectId ),
				$body['label'] ?? null,
				$body['statements'], // TODO: support property removal. https://github.com/ProfessionalWiki/NeoWiki/issues/280
				$body['comment'] ?? null
			);
		} catch ( \RuntimeException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		return new Response( json_encode( $body ) );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject. 15 characters, starting with "s".',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'New display label. Omit to leave unchanged.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'List of Statements (property/value pairs) to set on the Subject. Nested shape matches the subject JSON format documented in docs/SubjectFormat.md.',
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
