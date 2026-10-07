<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Content\Content;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaContentUnavailableException;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaJsonLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaJsonLookup
 */
class WikiPageSchemaJsonLookupTest extends TestCase {

	public function testReturnsTheStoredJson(): void {
		$lookup = $this->newLookup( new SchemaContent( '{"description":"desc"}' ) );

		$this->assertSame( '{"description":"desc"}', $lookup->getSchemaJson( $this->schemaPage() ) );
	}

	public function testThrowsWhenTheContentCouldNotBeRead(): void {
		// Distinct from content that does not deserialize: this outcome is transient, so callers
		// that cache by revision id must not store it.
		$lookup = $this->newLookup( content: null );

		$this->expectException( SchemaContentUnavailableException::class );

		$lookup->getSchemaJson( $this->schemaPage() );
	}

	private function schemaPage(): Title {
		return Title::makeTitle( NeoWikiExtension::NS_SCHEMA, 'Person' );
	}

	private function newLookup( ?Content $content ): WikiPageSchemaJsonLookup {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->method( 'getPageContent' )->willReturn( $content );

		return new WikiPageSchemaJsonLookup(
			pageContentFetcher: $fetcher,
			authority: $this->createMock( Authority::class )
		);
	}

}
