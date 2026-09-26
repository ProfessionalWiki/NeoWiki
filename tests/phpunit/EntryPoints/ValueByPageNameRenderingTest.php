<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
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

	private const string RENDERING_PAGE = 'ValueByPageNameRenderingTestPage';
	private const string RENDERING_PAGE_MOTTO = 'Motto of the rendering page';

	/**
	 * The page the value is rendered on has a Motto of its own, which a value falling back to that page
	 * would show.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->createPageWithSubjects(
			self::RENDERING_PAGE,
			TestSubject::build( statements: new StatementList( [
				new Statement( new PropertyName( 'Motto' ), 'text', new StringValue( self::RENDERING_PAGE_MOTTO ) ),
			] ) )
		);
	}

	private function renderValueFrom( string $pageName ): string {
		return $this->parseWikitextOn(
			self::RENDERING_PAGE,
			'{{#neowiki_value: Motto | page=' . $pageName . ' }}'
		);
	}

	public static function titleThatCannotBeAPageProvider(): iterable {
		yield 'special page' => [ 'Special:Version' ];
		yield 'media link' => [ 'Media:Example.jpg' ];
		yield 'section link without a page' => [ '#History' ];
	}

	/**
	 * @dataProvider titleThatCannotBeAPageProvider
	 */
	public function testValueFromATitleThatCannotBeAPageRendersAsFromAMissingPage( string $title ): void {
		$html = $this->renderValueFrom( $title );

		$this->assertSame( $this->renderValueFrom( 'ValueByPageNameRenderingTestMissingPage' ), $html );
		$this->assertStringNotContainsString( self::RENDERING_PAGE_MOTTO, $html );
	}

}
