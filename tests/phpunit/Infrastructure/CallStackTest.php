<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\TextContentHandler;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Infrastructure\CallStack;

/**
 * Needs the database, which rendering reads to know whether the page exists.
 *
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\CallStack
 * @group Database
 */
class CallStackTest extends MediaWikiIntegrationTestCase {

	public function testCodeRunOutsideAParseIsNotInsideOne(): void {
		$this->assertFalse( CallStack::isInsideParse() );
	}

	public function testCodeRunByTheParserIsInsideAParse(): void {
		$insideParse = null;
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setHook( 'probe', static function () use ( &$insideParse ): string {
			$insideParse = CallStack::isInsideParse();
			return '';
		} );

		$parser->parse( '<probe/>', Title::newFromText( 'CallStackTest' ), ParserOptions::newFromAnon() );

		$this->assertTrue( $insideParse );
	}

	/**
	 * Content models other than wikitext render without the parser, into output the parser cache keeps all the same.
	 */
	public function testCodeRunWhileContentRendersWithoutTheParserIsInsideAParse(): void {
		$handler = new class( CONTENT_MODEL_TEXT ) extends TextContentHandler {

			public ?bool $insideParse = null;

			protected function fillParserOutput( $content, ContentParseParams $cpoParams, ParserOutput &$output ): void {
				$this->insideParse = CallStack::isInsideParse();
				parent::fillParserOutput( $content, $cpoParams, $output );
			}

		};

		$handler->getParserOutput(
			new TextContent( 'text' ),
			new ContentParseParams( Title::newFromText( 'CallStackTest' ), null, ParserOptions::newFromAnon() )
		);

		$this->assertTrue( $handler->insideParse );
	}

	/**
	 * Post-cache transforms run after the parse returns, and MediaWiki 1.47 can cache their output for
	 * every reader of the page.
	 */
	public function testCodeRunWhileParserOutputIsTransformedForViewingIsInsideAParse(): void {
		$insideParse = null;
		$this->setTemporaryHook( 'ParserOutputPostCacheTransform', static function () use ( &$insideParse ): void {
			$insideParse = CallStack::isInsideParse();
		} );

		$this->getServiceContainer()->getDefaultOutputPipeline()->run(
			new ParserOutput( 'text' ),
			ParserOptions::newFromAnon(),
			[]
		);

		$this->assertTrue( $insideParse );
	}

}
