<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Permissions\Authority;
use MediaWikiIntegrationTestCase;
use Psr\Log\Test\TestLogger;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiWikiConfigSource;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageContentFetcher;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiWikiConfigSource
 */
class MediaWikiWikiConfigSourceTest extends MediaWikiIntegrationTestCase {

	private function newSource( ?Content $content, ?TestLogger $logger = null, bool $throw = false ): MediaWikiWikiConfigSource {
		return new MediaWikiWikiConfigSource(
			new StubPageContentFetcher( $content, $throw ),
			$this->createMock( Authority::class ),
			'NeoWiki',
			$logger ?? new TestLogger()
		);
	}

	public function testReturnsTheDecodedObjectFromAJsonPage(): void {
		$source = $this->newSource( new JsonContent( '{ "dereferenceSubjectsToDataTab": true }' ) );

		$this->assertSame( [ 'dereferenceSubjectsToDataTab' => true ], $source->readConfig() );
	}

	public function testReturnsNullWhenThePageIsMissing(): void {
		$this->assertNull( $this->newSource( null )->readConfig() );
	}

	public function testReturnsNullForNonJsonContent(): void {
		$this->assertNull( $this->newSource( new WikitextContent( '{ "x": 1 }' ) )->readConfig() );
	}

	public function testReturnsNullAndWarnsWhenThePageIsNotAJsonObject(): void {
		$logger = new TestLogger();

		$this->assertNull( $this->newSource( new JsonContent( '[ 1, 2 ]' ), $logger )->readConfig() );
		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testReturnsNullWhenTheDatabaseIsUnavailable(): void {
		$this->assertNull( $this->newSource( null, throw: true )->readConfig() );
	}

	public function testReadsThePageAtMostOnce(): void {
		$fetcher = new StubPageContentFetcher( new JsonContent( '{}' ) );
		$source = new MediaWikiWikiConfigSource( $fetcher, $this->createMock( Authority::class ), 'NeoWiki', new TestLogger() );

		$source->readConfig();
		$source->readConfig();

		$this->assertSame( 1, $fetcher->fetchCount );
	}

}
