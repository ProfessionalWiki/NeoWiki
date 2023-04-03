<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use Wikimedia\ParamValidator\ParamValidator;

class PatchSubjectApi extends SimpleHandler {

	public function __construct(
		private PatchSubjectAction $patchSubjectAction
	) {
	}

	public function run( string $subjectId ): Response {
		// TODO: format validation?
		$request = json_decode( $this->getRequest()->getBody()->getContents(), true );

		$this->patchSubjectAction->patch(
			new SubjectId( $subjectId ),
			$request['properties'] // TODO: support property removal. Maybe second list. Maybe null values. Maybe other approach?
		);

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
