<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteSubjectApi extends SimpleHandler {

	public function __construct(
		private DeleteSubjectAction $deleteSubjectAction
	) {
	}

	public function run( string $subjectId ): Response {
		try {
			$this->deleteSubjectAction->deleteSubject(
				new SubjectId( $subjectId )
			);	
		} catch ( \RuntimeException $e ) {
			return $this->getResponseFactory()->createJson( [
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
			],
		];
	}

}
