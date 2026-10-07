<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use SpecialPageExecutor;

/**
 * Which docs page a special page links to is left to the page: DocsPathReferencesTest checks that it exists.
 */
trait HelpLinkAssertions {

	private function assertHelpLinkLeadsToTheDocs( OutputPage $output ): void {
		$this->assertStringContainsString(
			'href="https://neowiki.ai/docs/',
			$output->getIndicators()['mw-helplink'] ?? ''
		);
	}

	/**
	 * Reads what the page shows beside its heading, which the HTML of its body leaves out. In English, since
	 * in qqx every `<page>-helppage` message exists, and such a message replaces the link.
	 */
	private function outputOf( SpecialPage $page, ?Authority $performer = null ): OutputPage {
		( new SpecialPageExecutor() )->executeSpecialPage( $page, '', null, 'en', $performer );

		return $page->getOutput();
	}

}
