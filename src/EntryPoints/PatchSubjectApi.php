<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use Wikimedia\ParamValidator\ParamValidator;

class PatchSubjectApi extends SimpleHandler {

	public function __construct(
		private PatchSubjectAction $patchSubjectAction
	) {
	}

	public function run( string $subjectId ): Response {
		$this->patchSubjectAction->patch(
			new SubjectId( $subjectId ),
			[] // TODO: get patch from request
		);

		return new Response();
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			// TODO: define patch format
		];
	}

}
