<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\LabelTemplate;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\LabelTemplate
 */
class LabelTemplateTest extends TestCase {

	/**
	 * @param array<string, string> $values
	 */
	private function render( string $template, array $values ): ?string {
		return ( new LabelTemplate( $template ) )->render(
			fn( PropertyName $name ): string => $values[$name->text] ?? ''
		);
	}

	public function testTextAroundPlaceholdersIsKept(): void {
		$this->assertSame(
			'Rijksmuseum attendance 2024',
			$this->render( '{Museum} attendance {Year}', [ 'Museum' => 'Rijksmuseum', 'Year' => '2024' ] )
		);
	}

	public function testPlaceholderWithoutValueRendersEmptyWhileAnotherHasOne(): void {
		$this->assertSame(
			'Smith',
			$this->render( '{Given name} {Family name}', [ 'Family name' => 'Smith' ] )
		);
	}

	public function testLastPlaceholderWithoutValueStillLeavesTheLabelOfTheOthers(): void {
		$this->assertSame(
			'Ada',
			$this->render( '{Given name} {Family name}', [ 'Given name' => 'Ada' ] )
		);
	}

	public function testNoLabelWhenNoPlaceholderHasAValue(): void {
		$this->assertNull( $this->render( '{Museum} attendance {Year}', [] ) );
	}

	public function testValuesOfOnlyWhitespaceGiveNoLabel(): void {
		$this->assertNull( $this->render( '{Given name} {Family name}', [ 'Given name' => '  ', 'Family name' => "\t" ] ) );
	}

	public function testRunsOfWhitespaceCollapseToOneSpace(): void {
		$this->assertSame(
			'Ada Lovelace',
			$this->render( '{Given name} {Middle name} {Family name}', [ 'Given name' => "Ada\t", 'Family name' => 'Lovelace' ] )
		);
	}

	public function testSpaceInsideBracesIsNotPartOfThePropertyName(): void {
		$this->assertSame( 'Rijksmuseum', $this->render( '{ Museum }', [ 'Museum' => 'Rijksmuseum' ] ) );
	}

	public function testEmptyBracesAreLiteralText(): void {
		$this->assertSame( 'Rijksmuseum {}', $this->render( '{Museum} {}', [ 'Museum' => 'Rijksmuseum' ] ) );
	}

	public function testPropertyNamesAreThoseOfItsPlaceholdersInOrder(): void {
		$this->assertEquals(
			[ new PropertyName( 'Museum' ), new PropertyName( 'Year' ) ],
			( new LabelTemplate( '{Museum} attendance {Year} ({Museum}) {}' ) )->getPropertyNames()
		);
	}

	public function testTextWithoutPlaceholdersNamesNoProperties(): void {
		$this->assertSame( [], ( new LabelTemplate( 'Attendance' ) )->getPropertyNames() );
	}

}
