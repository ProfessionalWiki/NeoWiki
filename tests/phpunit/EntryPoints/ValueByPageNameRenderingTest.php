<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * What a reader sees of a value read by page name. It takes MediaWiki's own parser and page factory,
 * and the database they read pages from, as it is the page factory that refuses a title that cannot be
 * a page.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiValueParserFunction
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectContentRepository
 * @group Database
 */
class ValueByPageNameRenderingTest extends NeoWikiIntegrationTestCase {

	private function renderValueFrom( string $pageName ): string {
		return $this->parseWikitextOn(
			'ValueByPageNameRenderingTestPage',
			'{{#neowiki_value: Motto | page=' . $pageName . ' }}'
		);
	}

	public function testValueFromATitleThatCannotBeAPageRendersAsFromAMissingPage(): void {
		$this->assertSame(
			$this->renderValueFrom( 'ValueByPageNameRenderingTestMissingPage' ),
			$this->renderValueFrom( 'Special:Version' )
		);
	}

}
