<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
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
	 * The user is both the handler's Authority and the main request context's user, whom the action module
	 * acts as; on the wiki they are one requesting user. An error comes back as the response the caller
	 * would get.
	 */
	private function executeAs( User $user, SchemaPageActionApi $api, RequestData $request ): ResponseInterface {
		RequestContext::getMain()->setUser( $user );

		try {
			return $this->executeHandler( $api, $request, authority: $user );
		} catch ( HttpException $exception ) {
			return $api->getResponseFactory()->createFromException( $exception );
		}
	}

	/**
	 * Denies everyone reading that one Schema page, as an access control restricting pages one by one does.
	 */
	private function denyReadingSchemaPage( string $schemaName ): void {
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( $title, $user, $action, &$result ) use ( $schemaName ): bool {
				if ( $action === 'read'
					&& $title->getNamespace() === NeoWikiExtension::NS_SCHEMA
					&& $title->getDBkey() === $schemaName
				) {
					$result = 'badaccess-group0';
					return false;
				}

				return true;
			}
		);
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

	private function bodyOf( ResponseInterface $response ): string {
		$response->getBody()->rewind();
		return $response->getBody()->getContents();
	}

}
