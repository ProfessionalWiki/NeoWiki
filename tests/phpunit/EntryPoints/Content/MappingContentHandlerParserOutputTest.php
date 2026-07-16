<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContentHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContentHandler
 * @group Database
 */
class MappingContentHandlerParserOutputTest extends MediaWikiIntegrationTestCase {

	private function render( string $json, string $name = 'Person to EDM' ): string {
		$handler = new MappingContentHandler( MappingContent::CONTENT_MODEL_ID );
		$page = Title::makeTitle( NeoWikiExtension::NS_MAPPING, $name )->toPageIdentity();
		$cpoParams = new ContentParseParams( $page, null, null, true );

		return $handler->getParserOutput( new MappingContent( $json ), $cpoParams )->getRawText();
	}

	public function testMappingJsonIsVisibleOnTheReadTab(): void {
		$this->assertStringContainsString( 'edm:Agent', $this->render( $this->personToEdm() ) );
	}

	private function personToEdm(): string {
		return <<<JSON
			{
				"version": 1,
				"schema": "Person",
				"target": "edm",
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"rdaGr2": "http://rdvocab.info/ElementsGr2/",
					"skos": "http://www.w3.org/2004/02/skos/core#"
				},
				"subject": {
					"class": "edm:Agent"
				},
				"properties": {
					"Gender": { "predicate": "rdaGr2:gender" },
					"Birth date": { "predicate": "rdaGr2:dateOfBirth" },
					"Birth place": { "predicate": "rdaGr2:placeOfBirth" },
					"Description": { "predicate": "skos:note", "lang": "en" }
				}
			}
			JSON;
	}

}
