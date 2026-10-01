<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\SubjectSummaries;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\InvalidSubjectSummaryCursorException;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SortDirection;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryCursor;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummarySort;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryCursor
 */
class SubjectSummaryCursorTest extends TestCase {

	private const string SUBJECT_ID = 'sTestSSC1111111';

	/**
	 * @dataProvider sortValueProvider
	 */
	public function testDecodesWhatItEncoded( SubjectSummarySort $sort, string|int|null $sortValue ): void {
		$cursor = new SubjectSummaryCursor( $sort, SortDirection::Ascending, $sortValue, self::SUBJECT_ID );

		$this->assertEquals(
			$cursor,
			SubjectSummaryCursor::decode( $cursor->encode(), $sort, SortDirection::Ascending )
		);
	}

	public static function sortValueProvider(): iterable {
		yield 'a name' => [ SubjectSummarySort::Name, 'zx spectrum 48k' ];
		yield 'a generated name' => [ SubjectSummarySort::Name, null ];
		yield 'an edit time' => [ SubjectSummarySort::Edited, 1790866800 ];
	}

	public function testRejectsGarbage(): void {
		$this->expectException( InvalidSubjectSummaryCursorException::class );

		SubjectSummaryCursor::decode( 'not a cursor', SubjectSummarySort::Newest, SortDirection::Descending );
	}

	public function testRejectsACursorWrittenForAnotherSort(): void {
		$encoded = ( new SubjectSummaryCursor(
			SubjectSummarySort::Name, SortDirection::Ascending, 'apple', self::SUBJECT_ID
		) )->encode();

		$this->expectException( InvalidSubjectSummaryCursorException::class );

		SubjectSummaryCursor::decode( $encoded, SubjectSummarySort::Page, SortDirection::Ascending );
	}

	public function testRejectsACursorWrittenForTheOtherDirection(): void {
		$encoded = ( new SubjectSummaryCursor(
			SubjectSummarySort::Name, SortDirection::Ascending, 'apple', self::SUBJECT_ID
		) )->encode();

		$this->expectException( InvalidSubjectSummaryCursorException::class );

		SubjectSummaryCursor::decode( $encoded, SubjectSummarySort::Name, SortDirection::Descending );
	}

	/**
	 * @dataProvider mistypedSortValueProvider
	 */
	public function testRejectsASortValueOfTheWrongTypeForItsSort( SubjectSummarySort $sort, string|int $sortValue ): void {
		$encoded = ( new SubjectSummaryCursor( $sort, SortDirection::Ascending, $sortValue, self::SUBJECT_ID ) )->encode();

		$this->expectException( InvalidSubjectSummaryCursorException::class );

		SubjectSummaryCursor::decode( $encoded, $sort, SortDirection::Ascending );
	}

	public static function mistypedSortValueProvider(): iterable {
		yield 'text for an edit time' => [ SubjectSummarySort::Edited, 'yesterday' ];
		yield 'a number for a name' => [ SubjectSummarySort::Name, 5 ];
		yield 'any value for newest first' => [ SubjectSummarySort::Newest, 'apple' ];
	}

	public function testRejectsACursorWithAnInvalidSubjectId(): void {
		$encoded = ( new SubjectSummaryCursor(
			SubjectSummarySort::Newest, SortDirection::Descending, null, 'not-an-id'
		) )->encode();

		$this->expectException( InvalidSubjectSummaryCursorException::class );

		SubjectSummaryCursor::decode( $encoded, SubjectSummarySort::Newest, SortDirection::Descending );
	}

}
