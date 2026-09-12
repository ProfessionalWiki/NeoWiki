<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage\CreateSubjectPageRequest;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\RestCreateSubjectPagePresenter;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Creates a Subject together with a page of its own, in one revision. For the endpoints that add a
 * Subject to a page that already exists, see POST /neowiki/v0/page/{pageId}/mainSubject and
 * /childSubjects.
 */
class CreateSubjectPageApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run(): Response {
		$this->csrfValidator->verifyCsrfToken();

		$body = $this->getValidatedBody();

		$presenter = new RestCreateSubjectPagePresenter();

		try {
			NeoWikiExtension::getInstance()
				->newCreateSubjectPageAction( $presenter, $this->getAuthority() )
				->createSubjectPage( new CreateSubjectPageRequest(
					label: $body['label'] ?? null,
					schemaName: $body['schema'],
					statements: $body['statements'],
					comment: $body['comment'] ?? null,
				) );
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( RuntimeException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		$response = $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
		$response->setStatus( $presenter->getStatusCode() );
		return $response;
	}

	public function getBodyParamSettings(): array {
		return [
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Display label for the Subject, which also titles the page created '
					. 'for it. Optional: omit it, or pass an empty string, to create a Subject nobody named, '
					. 'whose page is titled after its Subject ID. A label that is not a main-namespace page '
					. 'title titles no page either, and is stored as the label all the same.',
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
				self::PARAM_DESCRIPTION => 'List of Statements (property/value pairs) for the Subject. Nested shape matches the subject JSON format documented at https://neowiki.ai/docs/api/subject-format.',
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
