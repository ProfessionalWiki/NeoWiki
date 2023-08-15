<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class GetSchemasApi extends SimpleHandler {

	public function run(): Response {
		$schemas = NeoWikiExtension::getInstance()->newGetSchemasQuery()->execute();

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $schemas ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [];
	}

}
