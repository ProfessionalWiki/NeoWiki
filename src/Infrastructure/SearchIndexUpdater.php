<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Search\SearchUpdate;

class SearchIndexUpdater {

	private const string SEARCH_UPDATE_BEFORE_1_44 = 'MediaWiki\\Deferred\\SearchUpdate';

	/**
	 * MediaWiki 1.44 moved SearchUpdate to MediaWiki\Search without an alias and stopped it being a
	 * DeferrableUpdate, so the class this wiki has is named at runtime and run directly.
	 */
	public static function updateFor( RevisionRecord $revision ): void {
		$searchUpdate = class_exists( SearchUpdate::class ) ? SearchUpdate::class : self::SEARCH_UPDATE_BEFORE_1_44;

		( new $searchUpdate(
			$revision->getPageId(),
			$revision->getPage(),
			$revision->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getContent()
		) )->doUpdate();
	}

}
