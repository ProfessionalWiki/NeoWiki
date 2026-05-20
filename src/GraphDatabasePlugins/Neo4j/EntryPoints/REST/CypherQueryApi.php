<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use Throwable;
use Wikimedia\ParamValidator\ParamValidator;

class CypherQueryApi extends SimpleHandler {

	public function __construct(
		private readonly Neo4jQueryService $queryService,
	) {
	}

	public function run(): Response {
		$authority = $this->getAuthority();

		if ( !$authority->isAllowed( 'neowiki-query' ) ) {
			return $this->errorResponse( 403, 'permissionDenied', 'You do not have permission to run queries.' );
		}

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromAuthority( $authority );

		if ( $user->pingLimiter( 'neowiki-query' ) ) {
			return $this->errorResponse( 429, 'rateLimitExceeded', 'Query rate limit exceeded.' );
		}

		$body = $this->getValidatedBody();

		try {
			$limits = Neo4jQueryLimits::forUser( $user );
		} catch ( Throwable $e ) {
			return $this->errorResponse( 500, 'internalError', $e->getMessage() );
		}

		try {
			$result = $this->queryService->execute(
				new Neo4jQueryRequest(
					cypher: $body['cypher'],
					parameters: $body['parameters'],
					limits: $limits,
				)
			);
		} catch ( QueryException $e ) {
			return $this->mapException( $e );
		}

		return $this->getResponseFactory()->createJson( [
			'columns' => $result->columns,
			'rows' => $result->rows,
			'truncated' => $result->truncated,
			'resultCount' => $result->resultCount,
			'durationMs' => $result->durationMs,
		] );
	}

	private function mapException( QueryException $e ): Response {
		$status = match ( true ) {
			$e instanceof EmptyQueryException         => 400,
			$e instanceof ParameterMissingException   => 400,
			$e instanceof CypherSyntaxException       => 400,
			$e instanceof WriteQueryRejectedException => 422,
			$e instanceof QueryTimeoutException       => 408,
			$e instanceof BackendUnavailableException => 503,
			$e instanceof InternalQueryException      => 500,
			// Defensive default for any future QueryException subclass added without updating this map.
			default                                   => 500,
		};

		return $this->errorResponse( $status, $e->errorType(), $e->getMessage() );
	}

	private function errorResponse( int $status, string $errorType, string $message ): Response {
		$response = $this->getResponseFactory()->createJson( [
			'errorType' => $errorType,
			'message' => $message,
		] );
		$response->setStatus( $status );
		return $response;
	}

	public function getBodyParamSettings(): array {
		return [
			'cypher' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'A read-only Cypher query. Single statement.',
			],
			'parameters' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => [],
				self::PARAM_DESCRIPTION => 'Optional parameter map. Reference parameters in the query as $name.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
