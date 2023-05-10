<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Permissions\Authority;
use TitleParser;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MalformedTitleException;
use Content;

class PageContentFetcherTest extends TestCase {

	private $pageContentFetcher;
	private $titleParser;
	private $revisionLookup;
	private $authority;
	private $revisionRecord;
	private $content;

	public function setUp(): void {
		$this->titleParser = $this->createMock( TitleParser::class );
		$this->revisionLookup = $this->createMock( RevisionLookup::class );
		$this->authority = $this->createMock( Authority::class );
		$this->revisionRecord = $this->createMock( RevisionRecord::class );
		$this->content = $this->createMock( Content::class );

		$this->pageContentFetcher = new PageContentFetcher( $this->titleParser, $this->revisionLookup, $this->authority );
	}

	public function testGetPageContentWithGivenAuthority(): void {
		$title = $this->createMock( \Title::class );
		$this->titleParser->method( 'parseTitle' )->willReturn( $title );

		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( $this->revisionRecord );

		$this->revisionRecord->method( 'getContent' )->willReturn( $this->content );

		$authority = $this->createMock( Authority::class );
		$content = $this->pageContentFetcher->getPageContent( 'test title', null, NS_MAIN );

		$this->assertSame( $this->content, $content );
	}

	public function testGetPageContentWithDefaultAuthority(): void {
		$title = $this->createMock( \Title::class );
		$this->titleParser->method( 'parseTitle' )->willReturn( $title );
		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( $this->revisionRecord );
		$this->revisionRecord->method( 'getContent' )->willReturn( $this->content );

		$content = $this->pageContentFetcher->getPageContent( 'test title', null, NS_MAIN );

		$this->assertSame( $this->content, $content );
	}

	public function testGetPageContentWithMalformedTitle(): void {
		$this->titleParser->method( 'parseTitle' )->willThrowException( new MalformedTitleException( "Mock malformed title exception" ) );

		$content = $this->pageContentFetcher->getPageContent( 'test title', null, NS_MAIN );

		$this->assertNull( $content );
	}

	public function testGetPageContentWithNonExistentTitle(): void {
		$title = $this->createMock( \Title::class );
		$this->titleParser->method( 'parseTitle' )->willReturn( $title );

		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( null );

		$content = $this->pageContentFetcher->getPageContent( 'test title', null, NS_MAIN );

		$this->assertNull( $content );
	}

	public function testGetPageContentWithDefaultNamespace(): void {
		$title = $this->createMock( \Title::class );
		$this->titleParser->method( 'parseTitle' )->with( $this->equalTo( 'test title' ), $this->equalTo( NS_MAIN ) )->willReturn( $title );
		$this->revisionLookup->method( 'getRevisionByTitle' )->willReturn( $this->revisionRecord );
		$this->revisionRecord->method( 'getContent' )->willReturn( $this->content );

		$content = $this->pageContentFetcher->getPageContent( 'test title', null, NS_MAIN );

		$this->assertSame( $this->content, $content );
	}
}
