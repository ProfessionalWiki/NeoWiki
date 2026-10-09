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
	private const INVALID_SCHEMA = '{"description": 5, "propertyDefinitions": {"Name": {"type": 5}}}';

	public function testInvalidSchemaNamesEachProblemOnThePage(): void {
		$text = $this->visibleText( self::INVALID_SCHEMA );

		$this->assertStringContainsString( '/description', $text );
		$this->assertStringContainsString( '/propertyDefinitions/Name/type', $text );
		$this->assertStringContainsString( 'must match the type', $text );
	}

	public function testProblemsShowPropertyNamesLiterally(): void {
		$this->assertStringContainsString(
			'/propertyDefinitions/{{SITENAME}}<b>/type',
			$this->visibleText( '{"propertyDefinitions": {"{{SITENAME}}<b>": {"type": 5}}}' )
		);
	}

	public function testNoticeUsesTheContentLanguageRatherThanTheViewerLanguage(): void {
		$this->setUserLang( 'qqx' );

		$this->assertStringNotContainsString( '(neowiki-schema-invalid-notice', $this->render( self::INVALID_SCHEMA ) );
	}

	public function testInvalidSchemaIsInTheTrackingCategory(): void {
		$this->assertContains(
			$this->trackingCategory(),
			$this->parserOutput( self::INVALID_SCHEMA, generateHtml: true )->getCategoryNames()
		);
	}

	public function testInvalidSchemaIsInTheTrackingCategoryWhenNoHtmlIsGenerated(): void {
		$this->assertContains(
			$this->trackingCategory(),
			$this->parserOutput( self::INVALID_SCHEMA, generateHtml: false )->getCategoryNames()
		);
	}

	public function testValidSchemaShowsNoNotice(): void {
		$this->assertStringNotContainsString( 'cdx-message', $this->render( self::VALID_SCHEMA ) );
	}

	public function testValidSchemaIsNotInTheTrackingCategory(): void {
		$this->assertNotContains(
			$this->trackingCategory(),
			$this->parserOutput( self::VALID_SCHEMA, generateHtml: true )->getCategoryNames()
		);
	}

	private function visibleText( string $json ): string {
		return html_entity_decode( strip_tags( $this->render( $json ) ) );
	}

	private function render( string $json ): string {
		return $this->parserOutput( $json, generateHtml: true )->getContentHolderText();
	}

	private function parserOutput( string $json, bool $generateHtml ): ParserOutput {
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
