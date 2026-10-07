<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SchemaPageActionApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Runs the Schema write endpoints as their callers do. For test cases that also use HandlerTestTrait.
 */
trait RunsSchemaPageActions {

	/**
	 * The action module runs as the main request context's user while the handler checks reads as its own
	 * Authority; on the wiki both are the requesting user. An error comes back as the response the caller
	 * would get.
	 */
	private function executeAs( Authority $authority, SchemaPageActionApi $api, RequestData $request ): ResponseInterface {
		if ( $authority instanceof User ) {
			RequestContext::getMain()->setUser( $authority );
		}

		try {
			return $this->executeHandler( $api, $request, authority: $authority );
		} catch ( HttpException $exception ) {
			return $api->getResponseFactory()->createFromException( $exception );
		}
	}

	private function newCsrfValidatorStub(): CsrfValidator {
		$csrfValidator = $this->createStub( CsrfValidator::class );
		$csrfValidator->method( 'verifyCsrfToken' )->willReturn( true );
		return $csrfValidator;
	}

	private function newTokenlessCsrfValidator(): CsrfValidator {
		$request = new FauxRequest();
		return new CsrfValidator( $request, new CsrfTokenSet( $request ) );
	}

	private function schemaTitle( string $schemaName ): Title {
		return Title::makeTitle( NeoWikiExtension::NS_SCHEMA, $schemaName );
	}

	private function schemaPageExists( string $schemaName ): bool {
		return $this->schemaTitle( $schemaName )->exists( IDBAccessObject::READ_LATEST );
	}

}
