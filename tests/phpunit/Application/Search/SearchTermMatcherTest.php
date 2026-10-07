<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Search;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher;
use ProfessionalWiki\NeoWiki\Tests\MarkedMatchesTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher
 */
class SearchTermMatcherTest extends TestCase {

	use MarkedMatchesTrait;

	// The terms are the ones MediaWiki's database engines hand their search hits: each already
	// escaped for a regex and fenced with word boundaries by SearchDatabase::regexTerm().

	public function testTextWithoutATermHasNoMatch(): void {
		$this->assertSame( 'Van Gogh Museum', $this->markedByTerms( 'Van Gogh Museum', '\bamsterdam\b' ) );
	}

	public function testTheTermIsMatched(): void {
		$this->assertSame(
			'Museum in [Amsterdam] since 1800',
			$this->markedByTerms( 'Museum in Amsterdam since 1800', '\bamsterdam\b' )
		);
	}

	public function testEveryTermMatches(): void {
		$this->assertSame(
			'[Rijks] museum in [Amsterdam]',
			$this->markedByTerms( 'Rijks museum in Amsterdam', '\brijks\b', '\bamsterdam\b' )
		);
	}

	public function testAnEngineThatNamedNoTermsHasNothingToMatchOn(): void {
		$this->assertNull( SearchTermMatcher::fromEngineTerms( [] ) );
	}

	public function testAnEngineThatNamedOnlyEmptyTermsHasNothingToMatchOn(): void {
		$this->assertNull( SearchTermMatcher::fromEngineTerms( [ '' ] ) );
	}

	public function testATermMatchesBeyondAsciiRegardlessOfCase(): void {
		$this->assertSame( '[Ärzte] ohne Grenzen', $this->markedByTerms( 'Ärzte ohne Grenzen', '\bärzte\b' ) );
	}

	// What an engine that names no terms, CirrusSearch above all, is guessed to have matched.
	public function testTextWithoutAQueryWordHasNoMatch(): void {
		$this->assertSame( 'Van Gogh Museum', $this->markedByQuery( 'amsterdam', 'Van Gogh Museum' ) );
	}

	public function testAQueryWordMatchesRegardlessOfCase(): void {
		$this->assertSame(
			'Museum in [Amsterdam] since 1800',
			$this->markedByQuery( 'amsterdam', 'Museum in Amsterdam since 1800' )
		);
	}

	public function testAQueryWordMatchesBeyondAsciiRegardlessOfCase(): void {
		$this->assertSame( '[Ärzte] ohne Grenzen', $this->markedByQuery( 'ärzte', 'Ärzte ohne Grenzen' ) );
	}

	public function testAQueryWordMatchesTheWholeWordItStarts(): void {
		$this->assertSame( 'The [Rijksmuseum] shop', $this->markedByQuery( 'rijks', 'The Rijksmuseum shop' ) );
	}

	public function testAQueryWordInsideAWordIsNotMatched(): void {
		$this->assertSame( 'The Rijksmuseum shop', $this->markedByQuery( 'museum', 'The Rijksmuseum shop' ) );
	}

	public function testEveryQueryWordMatches(): void {
		$this->assertSame(
			'[Rijks] museum in [Amsterdam]',
			$this->markedByQuery( 'rijks amsterdam', 'Rijks museum in Amsterdam' )
		);
	}

	public function testAQuotedPhraseMatchesThroughItsWords(): void {
		$this->assertSame( '[Van] [Gogh] Museum', $this->markedByQuery( '"van gogh"', 'Van Gogh Museum' ) );
	}

	public function testASearchOperatorMatchesThroughTheValueItRestrictsTo(): void {
		$this->assertSame( 'Museum in [Amsterdam]', $this->markedByQuery( 'intitle:amsterdam', 'Museum in Amsterdam' ) );
	}

	public function testTheKeywordOfASearchOperatorIsNotItselfMatched(): void {
		$this->assertSame( 'Every intitle here', $this->markedByQuery( 'intitle:amsterdam', 'Every intitle here' ) );
	}

	public function testAnExcludedWordIsNotAReasonThePageWasFound(): void {
		$this->assertSame( '[Museum] in Amsterdam', $this->markedByQuery( 'museum -amsterdam', 'Museum in Amsterdam' ) );
	}

	public function testPunctuationInTheQueryDoesNotMatchLiterally(): void {
		$this->assertSame( 'Open [monday] to friday', $this->markedByQuery( 'mon.day', 'Open monday to friday' ) );
	}

	public function testAQueryWordOfFewerThanThreeCharactersIsNotAReasonThePageWasFound(): void {
		$this->assertSame( 'Office of the [Museum]', $this->markedByQuery( 'of museum', 'Office of the Museum' ) );
	}

	public function testAQueryOfOnlyShortWordsHasNothingToMatchOn(): void {
		$this->assertNull( SearchTermMatcher::fromQuery( 'of an' ) );
	}

	public function testAQueryWithoutWordsHasNothingToMatchOn(): void {
		$this->assertNull( SearchTermMatcher::fromQuery( ' -- "" ' ) );
	}

	public function testAQueryOfOnlyExcludedWordsHasNothingToMatchOn(): void {
		$this->assertNull( SearchTermMatcher::fromQuery( '-amsterdam' ) );
	}

	private function markedByTerms( string $text, string ...$terms ): string {
		$matcher = SearchTermMatcher::fromEngineTerms( $terms );
		$this->assertNotNull( $matcher );

		return $this->marked( $matcher->splitOnMatches( $text ) );
	}

	private function markedByQuery( string $query, string $text ): string {
		$matcher = SearchTermMatcher::fromQuery( $query );
		$this->assertNotNull( $matcher );

		return $this->marked( $matcher->splitOnMatches( $text ) );
	}

}
