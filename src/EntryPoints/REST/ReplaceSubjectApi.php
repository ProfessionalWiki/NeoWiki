<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\ViolationSerializer;
use Wikimedia\ParamValidator\ParamValidator;

class ReplaceSubjectApi extends SimpleHandler {

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

		try {
			$violations = NeoWikiExtension::getInstance()->newReplaceSubjectAction( $this->getAuthority() )->replace(
				new SubjectId( $subjectId ),
				$body['label'],
				$body['statements'],
				$body['comment'] ?? null
			);
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SubjectNotFoundException $e ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SubjectEditNotAuthorizedException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		return $this->getResponseFactory()->createJson( [
			'status' => 'updated',
			'subjectId' => $subjectId,
			'violations' => ViolationSerializer::serializeMany( $violations ),
		] );
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
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'New display label. Required, must be non-empty.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Map of property names to Statements. Replaces the Subject\'s statement list entirely; omitted property names are deleted. Pass `{}` to delete all statements. Nested shape matches the subject JSON format documented in docs/reference/subject-format.md.',
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
