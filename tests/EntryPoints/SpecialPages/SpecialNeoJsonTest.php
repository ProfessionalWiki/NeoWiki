<?php

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoJson;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\JsonFilter;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoJson
 */
class SpecialNeoJsonTest extends SpecialPageTestBase {
	protected function newSpecialPage(): SpecialNeoJson {
		return new SpecialNeoJson();
	}

	public function testPageExists(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'(neojson-summary)',
			$output
		);
	}

	/**
	 * @dataProvider provideJsonData
	 */
	public function testOnSubmitFiltersEmptyProperties( string $json, array $expectedProperties ): void {
		$jsonFilter = new JsonFilter();
		$filteredJson = $jsonFilter->filterJson( $json );
		$filteredData = json_decode( $filteredJson, true );

		foreach ( $expectedProperties as $property ) {
			$this->assertArrayHasKey( $property, $filteredData['properties'] );
		}

		$this->assertCount( count( $expectedProperties ), $filteredData['properties'] );
	}

	public function provideJsonData(): array {
		return [
			'empty string property' => [
				json_encode( [
					'label' => 'Test Label',
					'schema' => 'Test Schema',
					'properties' => [
						'Empty String' => '',
						'Non-empty String' => 'I am not empty',
					]
				] ),
				[ 'Non-empty String' ]
			],
			'empty array property' => [
				json_encode( [
					'label' => 'Test Label',
					'schema' => 'Test Schema',
					'properties' => [
						'Empty Array' => [],
						'Non-empty Array' => [ 'I am not empty' ]
					]
				] ),
				[ 'Non-empty Array' ]
			],
		];
	}	
}
