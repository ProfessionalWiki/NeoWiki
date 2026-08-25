<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubject\ValidateSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaNotFoundException;
use ProfessionalWiki\NeoWiki\Presentation\ViolationSerializer;
use Wikimedia\ParamValidator\ParamValidator;

class ValidateSubjectApi extends SimpleHandler {

	public function __construct(
		private readonly ValidateSubjectQuery $query,
	) {
	}

	public function run(): Response {
		$body = $this->getValidatedBody();

		try {
			$violations = $this->query->validate(
				$body['schema'],
				$body['statements'],
			);
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SchemaNotFoundException $e ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		return $this->getResponseFactory()->createJson( [
			'violations' => ViolationSerializer::serializeMany( $violations ),
		] );
	}

	public function getBodyParamSettings(): array {
		return [
			'schema' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the Schema to validate the proposed Subject against.',
			],
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Accepted for parity with the create endpoints but ignored: a label is '
					. 'optional, so nothing about it can be violated.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Proposed Statements (property/value pairs). Shape matches the subject JSON format documented at https://neowiki.ai/docs/api/subject-format.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
