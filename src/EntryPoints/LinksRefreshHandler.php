<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Deferred\LinksUpdate\LinksDeletionUpdate;
use MediaWiki\Deferred\LinksUpdate\LinksTable;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Page\PageReference;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * MediaWiki refreshes a page's links without saving it after an edit to a template or module the page
 * uses, on a null edit, and from refreshLinks.php. No revision hook fires for those, so the categories
 * and page properties such a refresh changes would otherwise stay out of the graph until the next edit.
 */
class LinksRefreshHandler {

	/**
	 * @see LinksUpdateCompleteHook
	 */
	public static function onLinksUpdateComplete( LinksUpdate $linksUpdate, mixed $ticket ): void {
		if ( !self::isRefresh( $linksUpdate ) || !self::changedCategoriesOrPageProperties( $linksUpdate ) ) {
			return;
		}

		$title = $linksUpdate->getTitle();

		DeferredUpdates::addCallableUpdate( static function () use ( $title ): void {
			NeoWikiExtension::getInstance()->newPageRebuilder()->rebuild( $title );
		} );
	}

	/**
	 * A save that changed the page's content runs a recursive links update, which refreshes the pages
	 * transcluding it as well, and has projected the page itself. A deletion runs its links update once the
	 * page has left the graph, and by then another page can hold the title.
	 */
	private static function isRefresh( LinksUpdate $linksUpdate ): bool {
		return !$linksUpdate->isRecursive() && !( $linksUpdate instanceof LinksDeletionUpdate );
	}

	private static function changedCategoriesOrPageProperties( LinksUpdate $linksUpdate ): bool {
		return self::changedCategories( $linksUpdate )
			|| $linksUpdate->getAddedProperties() !== []
			|| $linksUpdate->getRemovedProperties() !== [];
	}

	/**
	 * MediaWiki removes and adds again a category whose sort key changed, and every category of a moved page.
	 */
	private static function changedCategories( LinksUpdate $linksUpdate ): bool {
		$added = self::categoryNames( $linksUpdate, LinksTable::INSERTED );
		$removed = self::categoryNames( $linksUpdate, LinksTable::DELETED );

		return array_diff( $added, $removed ) !== [] || array_diff( $removed, $added ) !== [];
	}

	/**
	 * @return string[]
	 */
	private static function categoryNames( LinksUpdate $linksUpdate, int $setType ): array {
		return array_map(
			static fn ( PageReference $category ): string => $category->getDBkey(),
			$linksUpdate->getPageReferenceArray( 'categorylinks', $setType )
		);
	}

}
