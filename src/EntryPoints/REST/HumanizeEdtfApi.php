<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\EdtfHumanizer;
use Wikimedia\ParamValidator\ParamValidator;

class HumanizeEdtfApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run(): Response {
		$params = $this->getValidatedParams();

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( [
			'value' => $params['value'],
			'humanized' => ( new EdtfHumanizer() )->humanize( $params['value'], $params['language'] ),
		] ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'value' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'EDTF value to humanize, e.g. "1985?" or "{2010,2015..2020}".',
			],
			'language' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 'en',
				self::PARAM_DESCRIPTION => 'Language of the humanized reading. Falls back to English.',
			],
		];
	}

}
