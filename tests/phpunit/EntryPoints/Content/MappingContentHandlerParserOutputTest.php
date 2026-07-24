<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContentHandler;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContentHandler
 * @covers \ProfessionalWiki\NeoWiki\Presentation\MappingPageHtmlBuilder
 * @group Database
 */
class MappingContentHandlerParserOutputTest extends MediaWikiIntegrationTestCase {

	private function parserOutput( string $json, string $name = 'EDM' ): ParserOutput {
		$handler = new MappingContentHandler( MappingContent::CONTENT_MODEL_ID );
		$page = Title::makeTitle( NeoWikiExtension::NS_MAPPING, $name )->toPageIdentity();
		$cpoParams = new ContentParseParams( $page, null, null, true );

		return $handler->getParserOutput( new MappingContent( $json ), $cpoParams );
	}

	private function render( string $json, string $name = 'EDM' ): string {
		return $this->parserOutput( $json, $name )->getRawText();
	}

	public function testMappingJsonIsVisibleOnTheReadTab(): void {
		$this->assertStringContainsString( 'edm:Agent', $this->render( $this->edm() ) );
	}

	public function testPerSchemaSubtreeIsRenderedAsAJsonTable(): void {
		$html = $this->render( $this->edm() );

		$this->assertStringContainsString( 'mw-json', $html );
		$this->assertStringContainsString( 'rdaGr2:dateOfBirth', $html );
	}

	public function testEachSchemaSectionHasADeepLinkableId(): void {
		$this->assertStringContainsString(
			'id="ext-neowiki-mapping-schema-Person"',
			$this->render( $this->edm() )
		);
	}

	public function testFormatVersionIsRenderedAsASubtleLine(): void {
		$this->assertStringContainsString(
			'ext-neowiki-mapping-page__version',
			$this->render( $this->edm() )
		);
	}

	public function testMappedSchemaPagesAreRegisteredAsLinks(): void {
		$parserOutput = $this->parserOutput( $this->edm() );

		$this->assertTrue( $this->registersLocalLink( $parserOutput, NeoWikiExtension::NS_SCHEMA, 'Person' ) );
		$this->assertTrue( $this->registersLocalLink( $parserOutput, NeoWikiExtension::NS_SCHEMA, 'City' ) );
	}

	public function testPrefixNamespaceIrisAreRegisteredAsExternalLinks(): void {
		$externalLinks = $this->parserOutput( $this->edm() )->getExternalLinks();

		$this->assertArrayHasKey( 'http://www.europeana.eu/schemas/edm/', $externalLinks );
		$this->assertArrayHasKey( 'http://xmlns.com/foaf/0.1/', $externalLinks );
	}

	public function testPrefixesAreRenderedAsClickableExternalLinks(): void {
		$html = $this->render( $this->edm() );

		$this->assertStringContainsString( 'ext-neowiki-mapping-page__prefixes', $html );
		$this->assertStringContainsString( 'href="http://www.europeana.eu/schemas/edm/"', $html );
	}

	public function testPrefixIriWithAnUnsafeSchemeIsNotLinkified(): void {
		$json = (string)json_encode( [
			'version' => 1,
			'prefixes' => [ 'evil' => 'javascript:alert(1)' ],
			'schemas' => [ 'Person' => [ 'subject' => [ 'class' => 'edm:Agent' ], 'properties' => (object)[] ] ],
		] );

		$parserOutput = $this->parserOutput( $json );

		$this->assertStringNotContainsString( 'href="javascript:', $parserOutput->getRawText() );
		$this->assertArrayNotHasKey( 'javascript:alert(1)', $parserOutput->getExternalLinks() );
	}

	public function testJsonContentAndNeoWikiStyleModulesAreAdded(): void {
		$moduleStyles = $this->parserOutput( $this->edm() )->getModuleStyles();

		$this->assertContains( 'mediawiki.content.json', $moduleStyles );
		$this->assertContains( 'ext.neowiki.styles', $moduleStyles );
	}

	public function testMissingSchemaIsLinkedAsARedLink(): void {
		$this->assertStringContainsString(
			'redlink=1',
			$this->render( $this->mappingWithSchema( 'Ghost' ) )
		);
	}

	public function testExistingSchemaIsLinkedAsABlueLink(): void {
		$this->createSchemaPage( 'Person' );
		$this->getServiceContainer()->getLinkCache()->clear();

		$parserOutput = $this->parserOutput( $this->mappingWithSchema( 'Person' ) );

		$this->assertTrue( $this->registersLocalLink( $parserOutput, NeoWikiExtension::NS_SCHEMA, 'Person' ) );
		$this->assertStringNotContainsString( 'redlink', $parserOutput->getRawText() );
	}

	public function testEmptySchemasShowAFriendlyEmptyState(): void {
		$html = $this->render( '{ "version": 1, "prefixes": {}, "schemas": {} }' );

		$this->assertStringContainsString( 'ext-neowiki-mapping-page__empty', $html );
		$this->assertStringNotContainsString( 'mw-json', $html );
	}

	public function testNonV1ShapeFallsBackToTheDefaultJsonTable(): void {
		$html = $this->render( '{ "version": 2, "schemas": {} }' );

		$this->assertStringContainsString( 'mw-json', $html );
		$this->assertStringNotContainsString( 'ext-neowiki-mapping-page', $html );
	}

	public function testInvalidJsonFallsBackToTheDefaultRendering(): void {
		$this->assertStringNotContainsString(
			'ext-neowiki-mapping-page',
			$this->render( 'this is not valid json' )
		);
	}

	/**
	 * @param array<int, array{link: LinkTarget, pageid?: int}> $links
	 */
	private function registersLocalLink( ParserOutput $parserOutput, int $namespace, string $dbKey ): bool {
		$matches = array_filter(
			$parserOutput->getLinkList( ParserOutputLinkTypes::LOCAL ),
			static fn ( array $link ): bool =>
				$link['link']->getNamespace() === $namespace && $link['link']->getDBkey() === $dbKey
		);

		return $matches !== [];
	}

	private function createSchemaPage( string $name ): void {
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle(
			Title::makeTitle( NeoWikiExtension::NS_SCHEMA, $name )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new SchemaContent( '{ "title": "' . $name . '", "propertyDefinitions": {} }' ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Test schema' ) );
	}

	private function mappingWithSchema( string $schemaName ): string {
		return (string)json_encode( [
			'version' => 1,
			'prefixes' => [ 'edm' => 'http://www.europeana.eu/schemas/edm/' ],
			'schemas' => [
				$schemaName => [
					'subject' => [ 'class' => 'edm:Agent' ],
					'properties' => (object)[],
				],
			],
		] );
	}

	private function edm(): string {
		return <<<JSON
			{
				"version": 1,
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"rdaGr2": "http://rdvocab.info/ElementsGr2/",
					"skos": "http://www.w3.org/2004/02/skos/core#",
					"foaf": "http://xmlns.com/foaf/0.1/"
				},
				"schemas": {
					"Person": {
						"subject": {
							"class": "edm:Agent"
						},
						"properties": {
							"Gender": { "predicate": "rdaGr2:gender" },
							"Birth date": { "predicate": "rdaGr2:dateOfBirth" },
							"Birth place": { "predicate": "rdaGr2:placeOfBirth" },
							"Description": { "predicate": "skos:note", "lang": "en" }
						}
					},
					"City": {
						"subject": {
							"class": "edm:Place"
						},
						"properties": {}
					}
				}
			}
			JSON;
	}

}
