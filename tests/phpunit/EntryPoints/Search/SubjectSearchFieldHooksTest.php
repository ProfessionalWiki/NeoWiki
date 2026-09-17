<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Search;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubSearchEngine;
use SearchEngine;
use WikiPage;

/**
 * The field a search engine that takes fields from extensions is given for a page, driven through
 * MediaWiki's own hook plumbing — the path CirrusSearch indexes a page by.
 *
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSearchIndexFields
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSearchDataForIndex2
 */
class SubjectSearchFieldHooksTest extends NeoWikiIntegrationTestCase {

	public function testTheSubjectTextFieldIsDeclared(): void {
		$this->assertArrayHasKey( 'neowiki_text', $this->newEngine()->getSearchIndexFields() );
	}

	public function testThePagesSubjectsFillTheField(): void {
		$this->insertPage( 'Museum page', 'A museum.' );
		$this->changeSubjectsOfPage( 'Museum page', TestSubject::build(
			label: 'Rijksmuseum',
			statements: new StatementList( [ TestStatement::build( property: 'City', value: 'Amsterdam' ) ] )
		) );

		$this->assertSame( "Rijksmuseum\nAmsterdam", $this->searchIndexDataOf( 'Museum page' )['neowiki_text'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function searchIndexDataOf( string $pageName ): array {
		$page = $this->newWikiPage( $pageName );
		$handler = $this->handlerOf( $page );

		return $handler->getDataForSearchIndex(
			$page,
			$handler->getParserOutputForIndexing( $page ),
			$this->newEngine(),
			$page->getRevisionRecord()
		);
	}

	private function newWikiPage( string $pageName ): WikiPage {
		return $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
	}

	private function handlerOf( WikiPage $page ): ContentHandler {
		return $this->getServiceContainer()->getContentHandlerFactory()
			->getContentHandler( $page->getContentModel() );
	}

	private function newEngine(): SearchEngine {
		$engine = new StubSearchEngine();
		$engine->setHookContainer( $this->getServiceContainer()->getHookContainer() );

		return $engine;
	}

}
