<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

class GetSchemaNamesApi extends SimpleHandler {

	public function run( string $search ): Response {
		$schemaNames = array_map(
			function ( TitleValue $title ): string {
				return $title->getText();
			},
			NeoWikiExtension::getInstance()->getSchemaNameLookup()->getSchemaNamesMatching( $search )
		);

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $schemaNames ) ) );
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
