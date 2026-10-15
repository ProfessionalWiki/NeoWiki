<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;
use Wikimedia\ParamValidator\ParamValidator;

trait TakesDependentSubjects {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function dependentSubjectsParamSettings(): array {
		return [
			'dependentSubjects' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Dependent Subjects to write in the same revision, each an object with '
					. 'an `id` (pre-minted with POST /neowiki/v0/subject-ids), a `schema` whose Subjects are '
					. 'dependent, and `statements`. When replacing a Subject, the `id` of a Dependent Subject of the '
					. 'same Schema that it points at, directly or through other Dependent Subjects, replaces that '
					. 'Dependent Subject; any other `id` in use answers 409. See '
					. DocumentationUrl::SubjectFormat->value . '.',
			],
		];
	}

	private function subjectIdInUseResponse( SubjectIdInUseException $exception ): Response {
		return $this->getResponseFactory()->createHttpError( 409, [
			'status' => 'error',
			'message' => $exception->getMessage(),
		] );
	}

}
