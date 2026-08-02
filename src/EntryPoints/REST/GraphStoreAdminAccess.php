<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\GraphStoreStatusSerializer;

/**
 * The access rule and error shape the graph-store endpoints share.
 *
 * They are gated on one right and nothing else: what they report and change is the installation's own
 * machinery, so there is no page whose permissions could stand in for it.
 */
trait GraphStoreAdminAccess {

	private function refuseWithoutTheAdminRight(): ?Response {
		if ( $this->getAuthority()->isAllowed( NeoWikiExtension::ADMIN_RIGHT ) ) {
			return null;
		}

		return $this->errorResponse(
			403,
			'permissionDenied',
			'You do not have permission to administer NeoWiki\'s graph stores.'
		);
	}

	private function errorResponse( int $status, string $errorType, string $message ): Response {
		$response = $this->getResponseFactory()->createJson( [
			'errorType' => $errorType,
			'message' => $message,
		] );
		$response->setStatus( $status );

		return $response;
	}

	private function newSerializer(): GraphStoreStatusSerializer {
		return new GraphStoreStatusSerializer();
	}

}
