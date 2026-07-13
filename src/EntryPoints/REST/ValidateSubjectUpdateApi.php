<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\ViolationSerializer;
use Wikimedia\ParamValidator\ParamValidator;

class ValidateSubjectUpdateApi extends SimpleHandler {

	public function run( string $subjectId ): Response {
		$query = NeoWikiExtension::getInstance()->newValidateSubjectUpdateQuery( $this->getAuthority() );
		$body = $this->getValidatedBody();

		try {
			$violations = $query->validate(
				$subjectId,
				is_string( $body['label'] ) ? $body['label'] : '',
				$body['statements'],
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
		}

		return $this->getResponseFactory()->createJson( [
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
				self::PARAM_DESCRIPTION => 'Proposed display label for the Subject. May be empty; an empty label produces a label-required violation rather than a 400.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Proposed Statements (property/value pairs) for the proposed update. Shape matches docs/api/subject-format.md.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Accepted for parity with PUT /subject/{id} but ignored — this endpoint never persists.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
