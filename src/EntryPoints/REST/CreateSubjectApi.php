<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

class CreateSubjectApi extends SimpleHandler implements CreateSubjectPresenter {

	private array $apiResponse = [];

	public function __construct(
		private readonly bool $isMainSubject,
	) {
	}

	public function run( int $pageId ): Response {
		// TODO: format validation
		$request = json_decode( $this->getRequest()->getBody()->getContents(), true );

		NeoWikiExtension::getInstance()->newCreateSubjectAction( $this )->createSubject(
			new CreateSubjectRequest(
				pageId: $pageId,
				isMainSubject: $this->isMainSubject,
				label: $request['label'],
				schemaId: $request['schema'],
				properties: $request['properties'],
			)
		);

		return $this->buildResponseObject();
	}

	private function buildResponseObject(): Response {
		$response = $this->getResponseFactory()->createJson( $this->apiResponse );
		$response->setStatus( 201 );
		return $response;
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	public function presentCreated( string $subjectId ): void {
		$this->apiResponse = [
			'status' => 'created',
			'subjectId' => $subjectId,
		];
	}

	public function presentInvalidRequest(): void {
		$this->apiResponse = [
			'status' => 'error',
		];
	}

}
