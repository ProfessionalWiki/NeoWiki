<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;

/**
 * Answers what a page id identifies: the page's current title and namespace. For callers that hold a
 * page id, where {@see PageIdentifiersLookup} is for callers that hold a Subject id.
 */
interface PageIdentifiersResolver {

	/**
	 * Null when no page carries this id.
	 */
	public function getIdentifiersOfPage( PageId $pageId ): ?PageIdentifiers;

	/**
	 * Null when no page carries this title, and when the text is not a title at all.
	 */
	public function getIdentifiersOfTitle( string $pageTitle ): ?PageIdentifiers;

	/**
	 * The main-namespace page the given text titles, as the wiki stores that title. Normalized, so
	 * that a wiki which capitalizes page titles gets a page reachable under the name answered here
	 * rather than one nothing can link to.
	 *
	 * Null when the text titles no page that could be created here: it is empty, holds characters
	 * no title may, names another namespace or another wiki, or carries a fragment, which is a
	 * place on a page rather than a page.
	 */
	public function getMainNamespaceTitle( string $text ): ?string;

}
