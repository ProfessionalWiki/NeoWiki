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

	public function run( string $search ): Response {
		$subjects = array_map(
			function ( SubjectLabelLookupResult $result ): array {
				return [
					'id' => $result->id,
					'label' => $result->label,
				];
			},
			NeoWikiExtension::getInstance()->getSubjectLabelLookup()->getSubjectLabelsMatching( $search )
		);

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $subjects ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'search' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			],
		];
	}

}
