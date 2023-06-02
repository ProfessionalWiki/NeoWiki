<?php

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;
use PHPUnit\Framework\TestCase;

class SubjectPropertiesTest extends TestCase {

	/**
	 * @dataProvider provideTestData
	 */
	public function testConstructorFiltersOutEmptyProperties( array $input, array $expected ): void {
		$subjectProperties = new SubjectProperties( $input );

		$this->assertEquals( $expected, $subjectProperties->asMap() );
	}

	public function provideTestData(): array {
		return [
			'empty string property' => [
				[
					'Empty String' => '',
					'Non-empty String' => 'I am not empty',
				],
				[
					'Non-empty String' => 'I am not empty',
				]
			],
			'empty array property' => [
				[
					'Empty Array' => [],
					'Non-empty Array' => [ 'I am not empty' ],
				],
				[
					'Non-empty Array' => [ 'I am not empty' ],
				]
			],
		];
	}
}
