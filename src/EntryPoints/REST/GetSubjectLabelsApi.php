<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectLabelsApi extends SimpleHandler {

	public function run(): Response {
		$params = $this->getValidatedParams();

		$subjects = array_map(
			function ( SubjectLabelLookupResult $result ): array {
				return [
					'id' => $result->id,
					'label' => $result->label,
				];
			},
			NeoWikiExtension::getInstance()->getSubjectLabelLookup()->getSubjectLabelsMatching(
				$params['search'],
				$params['limit'],
				$params['schema'],
			)
		);

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $subjects ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'search' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
			],
			'schema' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
			],
		];
	}

}
