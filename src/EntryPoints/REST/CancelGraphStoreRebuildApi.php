<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NothingToCancelException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\UnknownGraphStoreException;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Calls off the rebuild a graph store has queued or going. What it has already projected stays: a
 * cancelled run is resumable, and rebuilding is safe to repeat either way.
 */
class CancelGraphStoreRebuildApi extends SimpleHandler {

	use GraphStoreAdminAccess;

	public function __construct(
		private readonly CsrfValidator $csrfValidator,
	) {
	}

	public function run( string $name ): Response {
		$refusal = $this->refuseWithoutTheAdminRight() ?? $this->refuseWhileTheWikiIsReadOnly();

		if ( $refusal !== null ) {
			return $refusal;
		}

		$this->csrfValidator->verifyCsrfToken();

		try {
			$run = NeoWikiExtension::getInstance()->newGraphRebuildCoordinator()->cancel( $name );
		} catch ( UnknownGraphStoreException $e ) {
			return $this->errorResponse( 404, 'unknownStore', $e->getMessage() );
		} catch ( NothingToCancelException $e ) {
			return $this->errorResponse( 404, 'noRebuildToCancel', $e->getMessage() );
		}

		return $this->getResponseFactory()->createJson( $this->newSerializer()->runToArray( $run ) );
	}

	public function getParamSettings(): array {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the graph store whose rebuild to cancel, as configured.',
			],
		];
	}

}
