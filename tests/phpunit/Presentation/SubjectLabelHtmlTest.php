<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MockMessageLocalizer;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml;
use ProfessionalWiki\NeoWiki\Tests\ReadsMarkedSubjectLabel;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml
 */
class SubjectLabelHtmlTest extends TestCase {

	use ReadsMarkedSubjectLabel;

	private function withId( string $label ): string {
		return SubjectLabelHtml::withId( new MockMessageLocalizer( 'en' ), $label, 's1zz1111111azz4' );
	}

	public function testReadsAsTheLabelWithTheIdInParentheses(): void {
		$this->assertSame( 'Ada Lovelace (s1zz1111111azz4)', strip_tags( $this->withId( 'Ada Lovelace' ) ) );
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

	public function testASubjectWithoutALabelReadsAsNoLabelDefinedWithTheId(): void {
		$html = SubjectLabelHtml::withId( new MockMessageLocalizer( 'qqx' ), null, 's1zz1111111azz4' );

		$this->assertSame( '(neowiki-subject-no-label)(word-separator)(parentheses: s1zz1111111azz4)', strip_tags( $html ) );
	}

	/**
	 * @dataProvider labelProvider
	 */
	public function testMarksTheLabelApartFromTheId( ?string $label, string $shownLabel ): void {
		$html = SubjectLabelHtml::withId( new MockMessageLocalizer( 'qqx' ), $label, 's1zz1111111azz4' );

		$this->assertSame( $shownLabel, self::markedSubjectLabel( $html ) );
	}

	public static function labelProvider(): iterable {
		yield 'a label' => [ 'Ada Lovelace', 'Ada Lovelace' ];
		yield 'no label' => [ null, '(neowiki-subject-no-label)' ];
	}

	public function testNoLabelDefinedCarriesTheClassItIsStyledBy(): void {
		$html = SubjectLabelHtml::withId( new MockMessageLocalizer( 'qqx' ), null, 's1zz1111111azz4' );

		$this->assertStringContainsString( '<span class="ext-neowiki-subject-label-none">(neowiki-subject-no-label)</span>', $html );
	}

}
