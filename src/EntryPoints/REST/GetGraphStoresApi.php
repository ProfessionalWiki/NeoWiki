<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphStoreStatus;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\GraphStoreStatusSerializer;

/**
 * Reports every configured graph store: what it holds, how far that is from the wiki, and what
 * rebuilding it has done about that.
 */
class GetGraphStoresApi extends SimpleHandler {

	use GraphStoreAdminAccess;
	use ReadOnlyEndpoint;

	public function run(): Response {
		$refusal = $this->refuseWithoutTheAdminRight();

		if ( $refusal !== null ) {
			return $refusal;
		}

		$serializer = new GraphStoreStatusSerializer();

		return $this->getResponseFactory()->createJson( [
			'stores' => array_map(
				static fn ( GraphStoreStatus $status ): array => $serializer->storeToArray( $status ),
				NeoWikiExtension::getInstance()->newGraphStoreStatusLookup()->getStatuses()
			),
		] );
	}

}
