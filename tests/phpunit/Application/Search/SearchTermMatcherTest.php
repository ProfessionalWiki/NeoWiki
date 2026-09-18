<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Search;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher
 */
class SearchTermMatcherTest extends TestCase {

	// The terms are the ones MediaWiki's database engines hand their search hits: each already
	// escaped for a regex and fenced with word boundaries by SearchDatabase::regexTerm().

	public function testTextWithoutATermStaysOnePart(): void {
		$this->assertSame(
			[ 'Van Gogh Museum' ],
			$this->matcherFor( '\bamsterdam\b' )->splitOnMatches( 'Van Gogh Museum' )
		);
	}

	public function testTheMatchedTermBecomesItsOwnPart(): void {
		$this->assertSame(
			[ 'Museum in ', 'Amsterdam', ' since 1800' ],
			$this->matcherFor( '\bamsterdam\b' )->splitOnMatches( 'Museum in Amsterdam since 1800' )
		);
	}

	public function testTextOpeningWithAMatchStartsWithAnEmptyPart(): void {
		$this->assertSame(
			[ '', 'Amsterdam', ' museum' ],
			$this->matcherFor( '\bamsterdam\b' )->splitOnMatches( 'Amsterdam museum' )
		);
	}

	public function testATermFencedByWordBoundariesDoesNotMatchInsideAWord(): void {
		$this->assertSame(
			[ 'Amsterdammer' ],
			$this->matcherFor( '\bamsterdam\b' )->splitOnMatches( 'Amsterdammer' )
		);
	}

	public function testEveryTermMatches(): void {
		$this->assertSame(
			[ '', 'Rijks', ' museum in ', 'Amsterdam', '' ],
			$this->matcherFor( '\brijks\b', '\bamsterdam\b' )
				->splitOnMatches( 'Rijks museum in Amsterdam' )
		);
	}

	public function testAnEngineThatNamedNoTermsHasNothingToMatchOn(): void {
		$this->assertNull( SearchTermMatcher::fromEngineTerms( [] ) );
	}

	public function testAnEngineThatNamedOnlyEmptyTermsHasNothingToMatchOn(): void {
		$this->assertNull( SearchTermMatcher::fromEngineTerms( [ '' ] ) );
	}

	public function testARegexSpecialCharacterInATermMatchesOnlyItself(): void {
		$this->assertSame(
			[ 'Open monXday' ],
			$this->matcherFor( '\b' . preg_quote( 'mon.day', '/' ) . '\b' )->splitOnMatches( 'Open monXday' )
		);
	}

	// What an engine that names no terms, CirrusSearch above all, is guessed to have matched.
	public function testTextWithoutAQueryWordStaysOnePart(): void {
		$this->assertSame(
			[ 'Van Gogh Museum' ],
			$this->splitWith( 'amsterdam', 'Van Gogh Museum' )
		);
	}

	public function testAQueryWordMatchesRegardlessOfCase(): void {
		$this->assertSame(
			[ 'Museum in ', 'Amsterdam', ' since 1800' ],
			$this->splitWith( 'amsterdam', 'Museum in Amsterdam since 1800' )
		);
	}

	public function testAQueryWordMatchesTheWholeWordItStarts(): void {
		$this->assertSame(
			[ 'The ', 'Rijksmuseum', ' shop' ],
			$this->splitWith( 'rijks', 'The Rijksmuseum shop' )
		);
	}

	public function testAQueryWordInsideAWordIsNotMatched(): void {
		$this->assertSame(
			[ 'The Rijksmuseum shop' ],
			$this->splitWith( 'museum', 'The Rijksmuseum shop' )
		);
	}

	public function testEveryQueryWordMatches(): void {
		$this->assertSame(
			[ '', 'Rijks', ' museum in ', 'Amsterdam', '' ],
			$this->splitWith( 'rijks amsterdam', 'Rijks museum in Amsterdam' )
		);
	}

	public function testAQuotedPhraseMatchesThroughItsWords(): void {
		$this->assertSame(
			[ '', 'Van', ' ', 'Gogh', ' Museum' ],
			$this->splitWith( '"van gogh"', 'Van Gogh Museum' )
		);
	}

	public function testASearchOperatorMatchesThroughTheValueItRestrictsTo(): void {
		$this->assertSame(
			[ 'Museum in ', 'Amsterdam', '' ],
			$this->splitWith( 'intitle:amsterdam', 'Museum in Amsterdam' )
		);
	}

	public function testTheKeywordOfASearchOperatorIsNotItselfMatched(): void {
		$this->assertSame(
			[ 'Every intitle here' ],
			$this->splitWith( 'intitle:amsterdam', 'Every intitle here' )
		);
	}

	public function testAnExcludedWordIsNotAReasonThePageWasFound(): void {
		$this->assertSame(
			[ '', 'Museum', ' in Amsterdam' ],
			$this->splitWith( 'museum -amsterdam', 'Museum in Amsterdam' )
		);
	}

	public function testPunctuationInTheQueryDoesNotMatchLiterally(): void {
		$this->assertSame(
			[ 'Open ', 'monday', ' to friday' ],
			$this->splitWith( 'mon.day', 'Open monday to friday' )
		);
	}

	public function testAQueryWordOfFewerThanThreeCharactersIsNotAReasonThePageWasFound(): void {
		$this->assertSame(
			[ 'Office of the ', 'Museum', '' ],
			$this->splitWith( 'of museum', 'Office of the Museum' )
		);
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

	private function matcherFor( string ...$terms ): SearchTermMatcher {
		$matcher = SearchTermMatcher::fromEngineTerms( $terms );
		$this->assertNotNull( $matcher );

		return $matcher;
	}

	/**
	 * @return string[]
	 */
	private function splitWith( string $query, string $text ): array {
		$matcher = SearchTermMatcher::fromQuery( $query );
		$this->assertNotNull( $matcher );

		return $matcher->splitOnMatches( $text );
	}

}
