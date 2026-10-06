<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use MockHttpTrait;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\LayoutContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\MappingContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\PageContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SchemaContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SubjectPageSource;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\Persistence\ImportedPageTitlesLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ImportPresenterSpy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\PageDeleterSpy;
use TestLogger;

/**
 * Saves the imported pages to the database, since saving a page is what projects it into the graph stores.
 *
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction
 * @group Database
 */
class ImportPagesProjectionTest extends NeoWikiIntegrationTestCase {

	use MockHttpTrait;

	private const string MAPPING_JSON = '{"version":1,"schemas":{}}';

	public function testNoProjectionFailureIsLoggedWhenAGraphStoreProjectsAnImportedMapping(): void {
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 200 ) );
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [
			[ 'updateUrl' => 'https://sparql.example/update', 'projection' => 'EDM' ],
		] );
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );
		$presenter = new ImportPresenterSpy();

		$this->runWithoutGraphBackend( fn () => $this->newImportAction( $presenter )->import() );

		$this->assertEqualsCanonicalizing(
			[ 'Mapping:EDM', 'Mapping:CIDOC-CRM', 'Schema:Artwork', 'Layout:ArtworkCard' ],
			$presenter->created
		);
		$this->assertSame( [], self::loggedErrors( $logger ) );
	}

	private function newImportAction( ImportPresenterSpy $presenter ): ImportPagesAction {
		return new ImportPagesAction(
			presenter: $presenter,
			pageContentSaver: new PageContentSaver(
				$this->getServiceContainer()->getWikiPageFactory(),
				$this->getTestSysop()->getUser(),
			),
			importedPageTitlesLookup: $this->createMock( ImportedPageTitlesLookup::class ),
			pageDeleter: new PageDeleterSpy(),
			schemaContentSource: $this->createConfiguredMock(
				SchemaContentSource::class,
				[ 'getSchemas' => [ 'Artwork' => new SchemaContent( '{"propertyDefinitions":{}}' ) ] ]
			),
			subjectPageSource: $this->createMock( SubjectPageSource::class ),
			pageContentSource: $this->createMock( PageContentSource::class ),
			moduleContentSource: $this->createMock( PageContentSource::class ),
			mediaWikiContentSource: $this->createMock( PageContentSource::class ),
			layoutContentSource: $this->createConfiguredMock(
				LayoutContentSource::class,
				[ 'getLayouts' => [ 'ArtworkCard' => new LayoutContent( '{"schema":"Artwork","type":"infobox"}' ) ] ]
			),
			mappingContentSource: $this->createConfiguredMock(
				MappingContentSource::class,
				[
					'getMappings' => [
						'CIDOC-CRM' => new MappingContent( self::MAPPING_JSON ),
						'EDM' => new MappingContent( self::MAPPING_JSON ),
					],
				]
			),
			graphStoreProjections: [ 'EDM' ],
		);
	}

}
