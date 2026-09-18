<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\Application\Search\MatchedSearchLine;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchLanding;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchMatch;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Presentation\SubjectSearchHitHtmlBuilder;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * A Subject's labels, values, property names and Schema names are whatever an editor typed, so this
 * covers what becomes of them on the way into a search result row.
 *
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectSearchHitHtmlBuilder
 */
class SubjectSearchHitHtmlBuilderTest extends NeoWikiIntegrationTestCase {

	private const string SCRIPT = '<script>alert("x")</script>';

	public function testAMatchedStretchOfAValueIsEscaped(): void {
		$html = $this->newBuilder()->buildExtract( $this->matchOn( 'City', [ 'Open ', self::SCRIPT, '' ] ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script', $html );
	}

	public function testTheRestOfAValueIsEscaped(): void {
		$html = $this->newBuilder()->buildExtract( $this->matchOn( 'City', [ self::SCRIPT, 'Amsterdam', '' ] ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script', $html );
	}

	public function testAPropertyNameIsEscaped(): void {
		$html = $this->newBuilder()->buildExtract( $this->matchOn( self::SCRIPT, [ '', 'Amsterdam', '' ] ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script', $html );
	}

	public function testASchemaNameIsEscaped(): void {
		$html = $this->newBuilder()->buildExtract(
			new SubjectSearchMatch( self::SCRIPT, null, [ new MatchedSearchLine( null, [ '', 'A', '' ] ) ] )
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script', $html );
	}

	public function testASubjectNameIsEscaped(): void {
		$html = $this->newBuilder()->buildExtract(
			new SubjectSearchMatch( 'Museum', self::SCRIPT, [ new MatchedSearchLine( 'City', [ '', 'A', '' ] ) ] )
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script', $html );
	}

	public function testTheHeadingNamesTheSchemaWhenTheRowCarriesTheSubjectsName(): void {
		$html = $this->newBuilder()->buildExtract( $this->matchOn( 'City', [ '', 'Amsterdam', '' ] ) );

		$this->assertStringContainsString( '<div class="ext-neowiki-searchresult-subject">Museum</div>', $html );
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

	public function testAValueIsCutBackToWhatFitsAroundTheMatch(): void {
		$farFromTheMatch = 'Deventer' . str_repeat( ' filler', 40 ) . ' ';

		$html = $this->newBuilder()->buildExtract(
			$this->matchOn( 'Description', [ $farFromTheMatch, 'Amsterdam', '' ] )
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

	public function testTheLinkTextOfANameNobodyChoseIsMarkedAsAStandIn(): void {
		$this->assertSame(
			'(neowiki-subject-generated-name: Museum)',
			$this->newBuilder()->buildLinkText( $this->landingNamed( 'Museum', true ) )
		);
	}

	public function testTheLinkTextOfANameSomebodyChoseIsThatName(): void {
		$this->assertSame(
			self::SCRIPT,
			$this->newBuilder()->buildLinkText( $this->landingNamed( self::SCRIPT, false ) )
		);
	}

	/**
	 * @param string[] $parts
	 */
	private function matchOn( string $propertyName, array $parts ): SubjectSearchMatch {
		return new SubjectSearchMatch( 'Museum', null, [ new MatchedSearchLine( $propertyName, $parts ) ] );
	}

	private function landingNamed( string $name, bool $isGenerated ): SubjectSearchLanding {
		return new SubjectSearchLanding( new SubjectId( 's11111111111111' ), $name, $isGenerated, false );
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
