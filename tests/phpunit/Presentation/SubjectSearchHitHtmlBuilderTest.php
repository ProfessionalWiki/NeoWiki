<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\Application\Search\MatchedSearchLine;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchMatch;
use ProfessionalWiki\NeoWiki\Presentation\SubjectSearchHitHtmlBuilder;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * A Subject's labels, values, property names and Schema names are whatever an editor typed, so this
 * covers what becomes of them on the way into a search result row.
 *
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectSearchHitHtmlBuilder
 */
class SubjectSearchHitHtmlBuilderTest extends NeoWikiIntegrationTestCase {

	private const string SCRIPT = '<script>alert("x")</script>';

	public function testAMatchedStretchOfAValueIsEscaped(): void {
		$this->assertEscaped( $this->matchOn( 'City', [ 'Open ', self::SCRIPT, '' ] ) );
	}

	public function testTheRestOfAValueIsEscaped(): void {
		$this->assertEscaped( $this->matchOn( 'City', [ self::SCRIPT, 'Amsterdam', '' ] ) );
	}

	public function testAPropertyNameIsEscaped(): void {
		$this->assertEscaped( $this->matchOn( self::SCRIPT, [ '', 'Amsterdam', '' ] ) );
	}

	public function testASchemaNameIsEscaped(): void {
		$this->assertEscaped(
			new SubjectSearchMatch( self::SCRIPT, null, [ new MatchedSearchLine( null, [ '', 'A', '' ] ) ] )
		);
	}

	public function testASubjectNameIsEscaped(): void {
		$this->assertEscaped(
			new SubjectSearchMatch( 'Museum', self::SCRIPT, [ new MatchedSearchLine( 'City', [ '', 'A', '' ] ) ] )
		);
	}

	public function testTheHeadingNamesTheSchemaWhenTheRowCarriesTheSubjectsName(): void {
		$html = $this->newBuilder()->buildExtract( $this->matchOn( 'City', [ '', 'Amsterdam', '' ] ) );

		$this->assertStringContainsString( 'Museum', $html );
		$this->assertStringNotContainsString( '(neowiki-search-hit-subject:', $html );
	}

	public function testTheHeadingNamesTheSubjectAndItsSchema(): void {
		$html = $this->newBuilder()->buildExtract(
			new SubjectSearchMatch( 'Museum', 'Van Gogh Museum', [ new MatchedSearchLine( 'City', [ '', 'A', '' ] ) ] )
		);

		$this->assertStringContainsString( '(neowiki-search-hit-subject: Van Gogh Museum, Museum)', $html );
	}

	public function testAMatchedStretchIsMarkedTheWayMediaWikiMarksItsOwn(): void {
		$html = $this->newBuilder()->buildExtract( $this->matchOn( 'City', [ 'Open ', 'Amsterdam', ' daily' ] ) );

		$this->assertStringContainsString( 'Open <span class="searchmatch">Amsterdam</span> daily', $html );
	}

	public function testAMatchedLabelIsShownWithoutAPropertyName(): void {
		$html = $this->newBuilder()->buildExtract(
			new SubjectSearchMatch( 'Museum', null, [ new MatchedSearchLine( null, [ '', 'Rijksmuseum', '' ] ) ] )
		);

		$this->assertStringContainsString( '<span class="searchmatch">Rijksmuseum</span>', $html );
		$this->assertStringNotContainsString( '(colon-separator)', $html );
	}

	public function testEveryMatchedLineIsShownInOrder(): void {
		$html = $this->newBuilder()->buildExtract( new SubjectSearchMatch( 'Museum', null, [
			new MatchedSearchLine( 'City', [ '', 'Amsterdam', '' ] ),
			new MatchedSearchLine( 'Country', [ 'The ', 'Netherlands', '' ] ),
		] ) );

		$this->assertStringContainsString( 'City(colon-separator)<span class="searchmatch">Amsterdam</span>', $html );
		$this->assertStringContainsString( 'Country(colon-separator)The <span class="searchmatch">Netherlands</span>', $html );
		$this->assertLessThan( strpos( $html, 'Netherlands' ), strpos( $html, 'Amsterdam' ) );
	}

	public function testAValueIsCutBackToWhatFitsBeforeTheMatch(): void {
		$farFromTheMatch = 'Deventer' . str_repeat( ' filler', 40 ) . ' ';

		$html = $this->newBuilder()->buildExtract(
			$this->matchOn( 'Description', [ $farFromTheMatch, 'Amsterdam', '' ] )
		);

		$this->assertStringContainsString( '<span class="searchmatch">Amsterdam</span>', $html );
		$this->assertStringNotContainsString( 'Deventer', $html );
	}

	public function testAValueIsCutBackToWhatFitsAfterTheMatch(): void {
		$farFromTheMatch = ' ' . str_repeat( 'filler ', 40 ) . 'Deventer';

		$html = $this->newBuilder()->buildExtract(
			$this->matchOn( 'Description', [ '', 'Amsterdam', $farFromTheMatch ] )
		);

		$this->assertStringContainsString( '<span class="searchmatch">Amsterdam</span>', $html );
		$this->assertStringNotContainsString( 'Deventer', $html );
	}

	public function testAValueThatFitsIsShownWhole(): void {
		$html = $this->newBuilder()->buildExtract(
			$this->matchOn( 'City', [ 'Nearby ', 'Amsterdam', ' in the west' ] )
		);

		$this->assertStringContainsString( 'Nearby <span class="searchmatch">Amsterdam</span> in the west', $html );
	}

	private function assertEscaped( SubjectSearchMatch $match ): void {
		$html = $this->newBuilder()->buildExtract( $match );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script', $html );
	}

	/**
	 * @param string[] $parts
	 */
	private function matchOn( string $propertyName, array $parts ): SubjectSearchMatch {
		return new SubjectSearchMatch( 'Museum', null, [ new MatchedSearchLine( $propertyName, $parts ) ] );
	}

	private function newBuilder(): SubjectSearchHitHtmlBuilder {
		$context = new RequestContext();
		$context->setLanguage( 'qqx' );

		return new SubjectSearchHitHtmlBuilder(
			$context,
			MediaWikiServices::getInstance()->getContentLanguage()
		);
	}

}
