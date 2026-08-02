<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\ProjectionChangeTimeLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Reads a projection's last change off the Mapping page that defines it.
 *
 * A projection whose Mapping page does not exist reads as never changed rather than as changed just
 * now: a store configured for a projection nothing defines is a misconfiguration, and reporting it as
 * perpetually out of date would say nothing an operator could act on.
 */
class MappingPageChangeTimeLookup implements ProjectionChangeTimeLookup {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly RevisionLookup $revisionLookup,
	) {
	}

	public function getLastChangeTime( string $projection ): ?string {
		$title = $this->titleFactory->newFromText( $projection, NeoWikiExtension::NS_MAPPING );

		if ( $title === null ) {
			return null;
		}

		return $this->revisionLookup->getRevisionByTitle( $title )?->getTimestamp();
	}

}
