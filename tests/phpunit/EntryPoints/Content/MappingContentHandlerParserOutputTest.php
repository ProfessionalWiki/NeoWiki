<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\MainConfigNames;
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
		return $this->parserOutput( $json, $name )->getContentHolderText();
	}

	public function testMappingJsonIsVisibleOnTheReadTab(): void {
		$this->assertStringContainsString( 'edm:Agent', $this->render( $this->edm() ) );
	}

	public function testPerSchemaSubtreeIsRenderedAsAJsonTable(): void {
		$personSection = $this->schemaSection( $this->render( $this->edm() ), 'Person' );

		$this->assertStringContainsString( 'mw-json', $personSection );
		$this->assertStringContainsString( 'rdaGr2:dateOfBirth', $personSection );
	}

	public function testSchemaSectionContainsOnlyItsOwnSubtree(): void {
		$citySection = $this->schemaSection( $this->render( $this->edm() ), 'City' );

		$this->assertStringContainsString( 'edm:Place', $citySection );
		$this->assertStringNotContainsString( 'rdaGr2:dateOfBirth', $citySection );
	}

	public function testEachSchemaSectionHasADeepLinkableId(): void {
		$html = $this->render( $this->edm() );

		$this->assertStringContainsString( 'id="ext-neowiki-mapping-schema-Person"', $html );
		$this->assertStringContainsString( 'id="ext-neowiki-mapping-schema-City"', $html );
	}

	public function testSchemaSectionsFollowDocumentOrder(): void {
		$html = $this->render( $this->edm() );

		$person = strpos( $html, 'id="ext-neowiki-mapping-schema-Person"' );
		$city = strpos( $html, 'id="ext-neowiki-mapping-schema-City"' );

		// Guarded, because assertLessThan( int, false ) compares as bool and would pass.
		$this->assertIsInt( $person );
		$this->assertIsInt( $city );
		$this->assertLessThan( $city, $person );
	}

	public function testOverviewRowsShowTheTargetClassAndMappedPropertyCount(): void {
		$html = $this->render( $this->edm() );

		$this->assertStringContainsString( '>Person</a></td><td>edm:Agent</td><td>4</td>', $html );
		$this->assertStringContainsString( '>City</a></td><td>edm:Place</td><td>0</td>', $html );
	}

	public function testOverviewCountsContributedPropertiesOfAContributesOnlyEntry(): void {
		// No target class of its own, but it does map two properties — onto the Subjects it points at.
		$this->assertStringContainsString(
			'>Birth</a></td><td></td><td>2</td>',
			$this->render( $this->structural() )
		);
	}

	private function structural(): string {
		return <<<JSON
			{
				"version": 1,
				"prefixes": {
					"crm": "http://www.cidoc-crm.org/cidoc-crm/",
					"rdaGr2": "http://rdvocab.info/ElementsGr2/"
				},
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": {
							"birth": { "class": "crm:E67_Birth", "linkPredicate": "crm:P98i_was_born" }
						},
						"properties": {
							"Birth place": { "predicate": "crm:P7_took_place_at", "node": "birth" }
						}
					},
					"Birth": {
						"contributions": {
							"Brought into life": {
								"Date": { "predicate": "rdaGr2:dateOfBirth" },
								"Took place at": { "predicate": "rdaGr2:placeOfBirth" }
							}
						}
					}
				}
			}
			JSON;
	}

	public function testFormatVersionIsRenderedAsASubtleLine(): void {
		$this->assertStringContainsString(
			'ext-neowiki-mapping-page__version',
			$this->render( $this->edm() )
		);
	}

	public function testHeaderTextUsesTheContentLanguageRatherThanTheViewerLanguage(): void {
		$this->setUserLang( 'qqx' );

		$this->assertStringContainsString( 'Mapped schemas', $this->render( $this->edm() ) );
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

	public function testPrefixLinksHonorTheWikiExternalLinkConfiguration(): void {
		// Both directions, so an anchor that simply never emits rel cannot pass.
		$this->overrideConfigValue( MainConfigNames::NoFollowLinks, true );
		$this->assertStringContainsString( 'rel="nofollow"', $this->render( $this->edm() ) );

		$this->overrideConfigValue( MainConfigNames::NoFollowLinks, false );
		$html = $this->render( $this->edm() );

		$this->assertStringContainsString( 'href="http://www.europeana.eu/schemas/edm/"', $html );
		$this->assertStringNotContainsString( 'rel="nofollow"', $html );
	}

	public function testPrefixIriWithAnUnsafeSchemeIsNotLinkified(): void {
		$json = (string)json_encode( [
			'version' => 1,
			'prefixes' => [ 'evil' => 'javascript:alert(1)' ],
			'schemas' => [ 'Person' => [ 'subject' => [ 'class' => 'edm:Agent' ], 'properties' => (object)[] ] ],
		] );

		$parserOutput = $this->parserOutput( $json );

		$this->assertStringNotContainsString( 'href="javascript:', $parserOutput->getContentHolderText() );
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
		$this->assertStringNotContainsString( 'redlink', $parserOutput->getContentHolderText() );
	}

	/**
	 * Save validation rejects a name that is not its Schema page's title, but XML import does not. Such a
	 * name resolves to an existing Schema page while the projector never matches it, so linking it would
	 * claim a mapping that does not exist.
	 */
	public function testSchemaNameThatIsNotTheCanonicalPageTitleIsNotLinked(): void {
		// The page the non-canonical name normalizes to, so without the guard this would be a blue link
		// claiming a mapping the projector will never make, rather than a red one.
		$this->createSchemaPage( 'Person x' );
		$this->getServiceContainer()->getLinkCache()->clear();

		$parserOutput = $this->parserOutput( $this->mappingWithSchema( 'Person_x' ) );

		$this->assertStringContainsString( '<span>Person_x</span>', $parserOutput->getContentHolderText() );
		$this->assertStringNotContainsString( 'Schema:Person_x', $parserOutput->getContentHolderText() );
		$this->assertFalse( $this->registersLocalLink( $parserOutput, NeoWikiExtension::NS_SCHEMA, 'Person_x' ) );
	}

	public function testEmptySchemasShowAFriendlyEmptyState(): void {
		$html = $this->render( '{ "version": 1, "prefixes": {}, "schemas": {} }' );

		$this->assertStringContainsString( 'ext-neowiki-mapping-page__empty', $html );
		$this->assertStringNotContainsString( 'mw-json', $html );
	}

	public function testAnUnsupportedFormatVersionFallsBackToTheDefaultJsonTable(): void {
		$html = $this->render( '{ "version": 2, "schemas": {} }' );

		$this->assertStringContainsString( 'mw-json', $html );
		$this->assertStringNotContainsString( 'ext-neowiki-mapping-page', $html );
	}

	/**
	 * Raw JSON on its own looks like an ordinary Mapping page, so the version it is written in — the
	 * reason it defines no projection and fails every export — has to be said out loud.
	 */
	public function testAnUnsupportedFormatVersionIsNamedAboveTheRawJson(): void {
		$html = $this->render( '{ "version": 2, "schemas": {} }' );

		$this->assertStringContainsString( 'format version 2', $html );
		$this->assertLessThan( strpos( $html, 'mw-json' ), strpos( $html, 'format version 2' ) );
	}

	public function testContentThatIsNotAMappingDocumentGetsNoVersionNotice(): void {
		$this->assertStringNotContainsString( 'format version', $this->render( '[ 1, 2, 3 ]' ) );
	}

	public function testInvalidJsonFallsBackToTheDefaultRendering(): void {
		$this->assertStringNotContainsString(
			'ext-neowiki-mapping-page',
			$this->render( 'this is not valid json' )
		);
	}

	/**
	 * The rendered HTML from the section heading of $name up to the next schema section, so assertions
	 * about a schema's own subtree cannot be satisfied by another schema's section or by the header.
	 */
	private function schemaSection( string $html, string $name ): string {
		$start = strpos( $html, 'id="ext-neowiki-mapping-schema-' . $name . '"' );
		$this->assertIsInt( $start, "No section rendered for schema $name" );

		$next = strpos( $html, 'id="ext-neowiki-mapping-schema-', $start + 1 );

		return $next === false ? substr( $html, $start ) : substr( $html, $start, $next - $start );
	}

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
