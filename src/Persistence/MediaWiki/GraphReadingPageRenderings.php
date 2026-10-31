<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageRenderings;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphReads;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class GraphReadingPageRenderings implements PageRenderings {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly HTMLCacheUpdater $htmlCacheUpdater,
	) {
	}

	public function expireAfterProjection( PageId $pageId, ?ParserOutput $rendering ): void {
		if ( $rendering !== null && !GraphReads::happenedIn( $rendering ) ) {
			return;
		}

		$title = $this->titleFactory->newFromID( $pageId->id, IDBAccessObject::READ_LATEST );

		if ( $title === null ) {
			return;
		}

		// A cached rendering expires only when the page was touched after it, to the second.
		$title->invalidateCache( ( new ConvertibleTimestamp( ConvertibleTimestamp::time() + 1 ) )->getTimestamp( TS_MW ) );
		$this->htmlCacheUpdater->purgeTitleUrls( $title, HTMLCacheUpdater::PURGE_INTENT_TXROUND_REFLECTED );
	}

}
