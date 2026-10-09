<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Needs the database because parsing a page reads the link cache.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContentHandler
 * @group Database
 */
class SchemaContentHandlerParserOutputTest extends MediaWikiIntegrationTestCase {

	private const VALID_SCHEMA = '{"propertyDefinitions": {"Name": {"type": "text"}}}';
	private const INVALID_SCHEMA = '{"description": 5, "propertyDefinitions": {"Name": {"type": "text"}}}';

	public function testInvalidSchemaNamesEachProblemOnThePage(): void {
		$this->assertStringContainsString( '/description', $this->render( self::INVALID_SCHEMA ) );
	}

	public function testInvalidSchemaIsInTheTrackingCategory(): void {
		$this->assertContains(
			$this->trackingCategory(),
			$this->parserOutput( self::INVALID_SCHEMA )->getCategoryNames()
		);
	}

	public function testInvalidSchemaIsInTheTrackingCategoryWhenNoHtmlIsGenerated(): void {
		$this->assertContains(
			$this->trackingCategory(),
			$this->parserOutput( self::INVALID_SCHEMA, generateHtml: false )->getCategoryNames()
		);
	}

	public function testValidSchemaShowsNoNotice(): void {
		$this->assertSame( '', $this->render( self::VALID_SCHEMA ) );
	}

	public function testValidSchemaIsNotInTheTrackingCategory(): void {
		$this->assertNotContains(
			$this->trackingCategory(),
			$this->parserOutput( self::VALID_SCHEMA )->getCategoryNames()
		);
	}

	private function render( string $json ): string {
		return $this->parserOutput( $json )->getContentHolderText();
	}

	private function parserOutput( string $json, bool $generateHtml = true ): ParserOutput {
		$page = Title::makeTitle( NeoWikiExtension::NS_SCHEMA, 'Person' )->toPageIdentity();

		return ( new SchemaContentHandler( SchemaContent::CONTENT_MODEL_ID ) )->getParserOutput(
			new SchemaContent( $json ),
			new ContentParseParams( $page, null, null, $generateHtml )
		);
	}

	private function trackingCategory(): string {
		return Title::makeTitle(
			NS_CATEGORY,
			wfMessage( 'neowiki-schema-invalid-category' )->inContentLanguage()->text()
		)->getDBkey();
	}

}
