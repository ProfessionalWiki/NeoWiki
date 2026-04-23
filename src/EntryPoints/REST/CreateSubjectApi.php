<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use Wikimedia\ParamValidator\ParamValidator;

class CreateSubjectApi extends SimpleHandler implements CreateSubjectPresenter {

	private array $apiResponse = [];

	public function __construct(
		private readonly bool $isMainSubject,
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run( int $pageId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		$body = $this->getValidatedBody();
		'@phan-var array $body';

		try {
			NeoWikiExtension::getInstance()->newCreateSubjectAction( $this, $this->getAuthority() )->createSubject(
				new CreateSubjectRequest(
					pageId: $pageId,
					isMainSubject: $this->isMainSubject,
					label: $body['label'],
					schemaName: $body['schema'],
					statements: $body['statements'],
					comment: $body['comment'] ?? null,
				)
			);

			return $this->buildResponseObject();
		} catch ( \RuntimeException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}
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
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Display label for the Subject.',
			],
			'schema' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the Schema this Subject is an instance of.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'List of Statements (property/value pairs) for the Subject. Nested shape matches the subject JSON format documented in docs/SubjectFormat.md.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
		];
	}

	public function presentCreated( string $subjectId ): void {
		$this->apiResponse = [
			'status' => 'created',
			'subjectId' => $subjectId,
		];
	}

	public function presentSubjectAlreadyExists(): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'Subject already exists',
		];
	}
}
