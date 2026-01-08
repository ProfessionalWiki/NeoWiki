<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use Content;
use MalformedTitleException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use Title;
use TitleParser;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher
 */
class PageContentFetcherTest extends TestCase {

	private PageContentFetcher $pageContentFetcher;
	private TitleParser $titleParser;
	private RevisionLookup $revisionLookup;
	private Authority $authority;
	private RevisionRecord $revisionRecord;
	private Content $content;

	public function setUp(): void {
		$this->titleParser = $this->createMock( TitleParser::class );
		$this->revisionLookup = $this->createMock( RevisionLookup::class );
		$this->authority = $this->createMock( Authority::class );
		$this->revisionRecord = $this->createMock( RevisionRecord::class );
		$this->content = $this->createMock( Content::class );

		$this->pageContentFetcher = new PageContentFetcher( $this->titleParser, $this->revisionLookup );
	}

	public function testGetPageContentWithGivenAuthority(): void {
		$this->titleParser->method( 'parseTitle' )->willReturn( Title::newFromText( 'test title' )->getTitleValue() );

		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( $this->revisionRecord );

		$this->revisionRecord->method( 'getContent' )->willReturn( $this->content );

		$content = $this->pageContentFetcher->getPageContent( 'test title', $this->authority );

		$this->assertSame( $this->content, $content );
	}

	public function testGetPageContentWithDefaultAuthority(): void {
		$this->titleParser->method( 'parseTitle' )->willReturn( Title::newFromText( 'test title' )->getTitleValue() );
		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( $this->revisionRecord );
		$this->revisionRecord->method( 'getContent' )->willReturn( $this->content );

		$content = $this->pageContentFetcher->getPageContent( 'test title', $this->authority );

		$this->assertSame( $this->content, $content );
	}

	public function testGetPageContentWithMalformedTitle(): void {
		$this->titleParser->method( 'parseTitle' )->willThrowException( new MalformedTitleException( "Mock malformed title exception" ) );

		$content = $this->pageContentFetcher->getPageContent( 'test title', $this->authority );

		$this->assertNull( $content );
	}

	public function testGetPageContentWithNonExistentRevision(): void {
		$this->titleParser->method( 'parseTitle' )->willReturn( Title::newFromText( 'test title' )->getTitleValue() );

		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( null );

		$content = $this->pageContentFetcher->getPageContent( 'test title', $this->authority );

		$this->assertNull( $content );
	}

	public function testGetPageContentWithDefaultNamespace(): void {
		$this->titleParser->method( 'parseTitle' )
			->with( $this->equalTo( 'test title' ), $this->equalTo( NS_MAIN ) )
			->willReturn( Title::newFromText( 'test title' )->getTitleValue() );

		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( $this->revisionRecord );
		$this->revisionRecord->method( 'getContent' )->willReturn( $this->content );

		$content = $this->pageContentFetcher->getPageContent( 'test title', $this->authority );

		$this->assertSame( $this->content, $content );
	}

}
