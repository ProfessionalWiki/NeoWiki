<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PublishedPageJsonLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageMappingLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPageContentFetcher;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageMappingLookup
 */
class WikiPageMappingLookupTest extends TestCase {

	private const MAPPING_JSON = <<<'JSON'
		{
			"version": 1,
			"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
			"schemas": {
				"Person": { "subject": { "class": "edm:Agent" }, "properties": {} }
			}
		}
		JSON;

	public function testChecksReadPermissionOnEveryCall(): void {
		$authorizer = new StubPageReadAuthorizer( allowed: true );

		$lookup = $this->newLookup( $this->newFetcher(), readAuthorizer: $authorizer );
		$this->assertNotNull( $lookup->getMapping( new MappingName( 'EDM' ) ) );

		$authorizer->allowed = false;

		$this->assertNull( $lookup->getMapping( new MappingName( 'EDM' ) ) );
	}

	public function testReadableMappingIsDeserialized(): void {
		$mapping = $this->newLookup( $this->newFetcher() )->getMapping( new MappingName( 'EDM' ) );

		$this->assertSame( 'edm:Agent', $mapping?->forSchema( new SchemaName( 'Person' ) )?->subject?->class );
	}

	public function testNamesTheMappingAfterItsPageRatherThanTheRequest(): void {
		// MediaWiki capitalizes a title's first letter, so "eDM" and "EDM" are one page and must
		// yield one Mapping name.
		$mapping = $this->newLookup( $this->newFetcher() )->getMapping( new MappingName( 'eDM' ) );

		$this->assertSame( 'EDM', $mapping?->name->getText() );
	}

	public function testUndeserializableMappingIsNull(): void {
		$lookup = $this->newLookup( new SpyPageContentFetcher( new MappingContent( 'not json' ) ) );

		$this->assertNull( $lookup->getMapping( new MappingName( 'EDM' ) ) );
	}

	public function testPageOutsideTheMappingNamespaceIsNoMapping(): void {
		$lookup = $this->newLookup( $this->newFetcher(), titleFactory: $this->newTitleFactory( NS_USER ) );

		$this->assertNull( $lookup->getMapping( new MappingName( 'User:Alice/mapping.json' ) ) );
	}

	public function testUnreadableMappingContentIsNull(): void {
		$lookup = $this->newLookup( new SpyPageContentFetcher( null ) );

		$this->assertNull( $lookup->getMapping( new MappingName( 'EDM' ) ) );
	}

	private function newLookup(
		SpyPageContentFetcher $fetcher,
		?PageReadAuthorizer $readAuthorizer = null,
		?TitleFactory $titleFactory = null
	): WikiPageMappingLookup {
		return new WikiPageMappingLookup(
			jsonLookup: new PublishedPageJsonLookup(
				$fetcher,
				$this->createMock( Authority::class ),
				new WANObjectCache( [ 'cache' => new HashBagOStuff() ] )
			),
			mappingDeserializer: new MappingPersistenceDeserializer(),
			titleFactory: $titleFactory ?? $this->newTitleFactory( NeoWikiExtension::NS_MAPPING ),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
		);
	}

	private function newFetcher(): SpyPageContentFetcher {
		return new SpyPageContentFetcher( new MappingContent( self::MAPPING_JSON ) );
	}

	private function newTitleFactory( int $namespace ): TitleFactory {
		$title = $this->createMock( Title::class );
		$title->method( 'getNamespace' )->willReturn( $namespace );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getLatestRevID' )->willReturn( 100 );
		$title->method( 'getText' )->willReturn( 'EDM' );

		$factory = $this->createMock( TitleFactory::class );
		$factory->method( 'newFromText' )->willReturn( $title );
		return $factory;
	}

}
