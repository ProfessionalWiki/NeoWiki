<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MockMessageLocalizer;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml
 */
class SubjectLabelHtmlTest extends TestCase {

	private function withId( string $label ): string {
		return SubjectLabelHtml::withId( new MockMessageLocalizer( 'en' ), $label, 's1zz1111111azz4' );
	}

	public function testReadsAsTheLabelWithTheIdInParentheses(): void {
		$this->assertSame( 'Ada Lovelace (s1zz1111111azz4)', strip_tags( $this->withId( 'Ada Lovelace' ) ) );
	}

	/**
	 * Not every language puts a space between words.
	 */
	public function testTheLabelAndTheIdAreSeparatedAsTheLanguageSeparatesWords(): void {
		$html = SubjectLabelHtml::withId( new MockMessageLocalizer( 'qqx' ), 'Ada Lovelace', 's1zz1111111azz4' );

		$this->assertStringContainsString( '</bdi>(word-separator)<span', $html );
	}

	public function testTheLabelIsEscaped(): void {
		$this->assertStringContainsString( '&lt;b', $this->withId( '<b>Ada</b>' ) );
	}

	/**
	 * A label in one script direction would otherwise reorder the id beside it.
	 */
	public function testTheLabelAndTheIdAreEachIsolated(): void {
		$html = $this->withId( 'Ada Lovelace' );

		$this->assertMatchesRegularExpression( '/<bdi[^>]*>Ada Lovelace<\/bdi>/', $html );
		$this->assertMatchesRegularExpression( '/<bdi[^>]*>s1zz1111111azz4<\/bdi>/', $html );
	}

	public function testTheIdCarriesTheClassItIsStyledBy(): void {
		$this->assertStringContainsString( 'class="ext-neowiki-subject-label-id"', $this->withId( 'Ada Lovelace' ) );
	}

}
