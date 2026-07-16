<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction;

use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction\SparqlRawParserFunction;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FakeSparqlQueryEndpoint;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction\SparqlRawParserFunction
 */
class SparqlRawParserFunctionTest extends TestCase {

	private const string RESULTS = '{"head":{"vars":["label"]},"results":{"bindings":[{"label":{"type":"literal","value":"Bach"}}]}}';

	/**
	 * A Parser whose msg() renders any key as "[key]" (params appended as "[key|p1|p2]"), so a test can
	 * assert which message key the production code selected without a full message-resolution context.
	 */
	private function createParser(): Parser {
		$parser = $this->createMock( Parser::class );
		$parser->method( 'msg' )->willReturnCallback(
			function ( string $key, ...$params ): Message {
				$rendered = $params === [] ? "[$key]" : '[' . $key . '|' . implode( '|', $params ) . ']';
				$message = $this->createMock( Message::class );
				$message->method( 'text' )->willReturn( $rendered );
				return $message;
			}
		);
		return $parser;
	}

	private function parserFunction( FakeSparqlQueryEndpoint $endpoint ): SparqlRawParserFunction {
		return new SparqlRawParserFunction( new SparqlQueryService( $endpoint ) );
	}

	/**
	 * The HTML the parser function hands back, from either its result or its error shape.
	 *
	 * @param string|array{0: string, noparse: true, isHTML: true} $result
	 */
	private function html( string|array $result ): string {
		return is_array( $result ) ? $result[0] : $result;
	}

	public function testEmptyQueryShowsLocalizedError(): void {
		$result = $this->parserFunction( FakeSparqlQueryEndpoint::returning( self::RESULTS ) )
			->handle( $this->createParser(), '   ' );

		$this->assertStringContainsString( '[neowiki-sparql-error-empty-query]', $this->html( $result ) );
	}

	public function testStoreRejectionShowsLocalizedSyntaxErrorWithDetail(): void {
		$result = $this->parserFunction(
			FakeSparqlQueryEndpoint::failingWith( new SparqlQueryFailedException( 'https://s.example', 400, 'bad token FOO' ) )
		)->handle( $this->createParser(), 'FOO' );

		$this->assertStringContainsString( '[neowiki-sparql-error-syntax|', $this->html( $result ) );
		$this->assertStringContainsString( 'bad token FOO', $this->html( $result ) );
	}

	public function testStoreFailureShowsLocalizedStoreUnavailableError(): void {
		$result = $this->parserFunction(
			FakeSparqlQueryEndpoint::failingWith( new SparqlQueryFailedException( 'https://s.example', 0, '' ) )
		)->handle( $this->createParser(), 'SELECT * WHERE { ?s ?p ?o }' );

		$this->assertStringContainsString( '[neowiki-sparql-error-store-unavailable]', $this->html( $result ) );
	}

	public function testValidQueryReturnsFormattedJsonResult(): void {
		$result = $this->parserFunction( FakeSparqlQueryEndpoint::returning( self::RESULTS ) )
			->handle( $this->createParser(), 'SELECT ?label WHERE { ?s ?p ?label }' );

		$this->assertStringContainsString( '<div class="mw-neowiki-sparql-result"><pre>', $this->html( $result ) );
		$this->assertStringContainsString( 'Bach', $this->html( $result ) );
		$this->assertStringContainsString( 'bindings', $this->html( $result ) );
	}

	public function testOutputIsHtmlEscaped(): void {
		$results = '{"head":{"vars":["v"]},"results":{"bindings":[{"v":{"type":"literal","value":"<script>alert(1)</script>"}}]}}';

		$result = $this->parserFunction( FakeSparqlQueryEndpoint::returning( $results ) )
			->handle( $this->createParser(), 'SELECT ?v WHERE { ?s ?p ?v }' );

		$this->assertStringNotContainsString( '<script>alert', $this->html( $result ) );
		$this->assertStringContainsString( '&lt;script&gt;', $this->html( $result ) );
	}

	/**
	 * Without isHTML the returned text is wikitext, so MediaWiki autolinks the IRIs that every
	 * results document carries, swallowing the JSON's closing quote into the URL as %22 and
	 * injecting anchors into the <pre>. The document must reach the reader as written.
	 */
	public function testResultIsArmouredAgainstWikitextTransformation(): void {
		$result = $this->parserFunction( FakeSparqlQueryEndpoint::returning( self::RESULTS ) )
			->handle( $this->createParser(), 'SELECT ?label WHERE { ?s ?p ?label }' );

		$this->assertIsArray( $result, 'Expected an isHTML array so the parser leaves the JSON alone.' );
		$this->assertTrue( $result['isHTML'] );
		$this->assertTrue( $result['noparse'] );
	}

	public function testErrorIsArmouredAgainstWikitextTransformation(): void {
		$result = $this->parserFunction(
			FakeSparqlQueryEndpoint::failingWith( new SparqlQueryFailedException( 'https://s.example', 400, 'see https://s.example/docs' ) )
		)->handle( $this->createParser(), 'FOO' );

		$this->assertIsArray( $result, 'Error detail carries the endpoint URL, so it needs the same armour.' );
		$this->assertTrue( $result['isHTML'] );
		$this->assertTrue( $result['noparse'] );
	}

}
