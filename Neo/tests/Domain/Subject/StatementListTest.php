<?php

use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use PHPUnit\Framework\TestCase;

class StatementListTest extends TestCase {

	/**
	 * @dataProvider provideTestData
	 */
	public function testConstructorFiltersOutEmptyProperties( array $input, array $expected ): void {
		$subjectProperties = new StatementList( $input );

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
			'null property' => [
				[
					'Null' => null,
				],
				[
					'Null' => null,
				]
			],
			'number property' => [
				[
					'Zero' => 0,
					'Non-zero' => 1,
				],
				[
					'Zero' => 0,
					'Non-zero' => 1,
				]
			],
			'boolean property' => [
				[
					'False' => false,
					'True' => true,
				],
				[
					'False' => false,
					'True' => true,
				]
			],
		];
	}

}
