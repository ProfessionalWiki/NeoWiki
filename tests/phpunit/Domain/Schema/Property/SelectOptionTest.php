<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption
 */
class SelectOptionTest extends TestCase {

	public function testStoresId(): void {
		$option = new SelectOption( 'EVNrDCjgVpv9oC', 'Draft' );

		$this->assertSame( 'EVNrDCjgVpv9oC', $option->getId() );
	}

	public function testStoresLabel(): void {
		$option = new SelectOption( 'x', 'Draft' );

		$this->assertSame( 'Draft', $option->getLabel() );
	}

	public function testThrowsOnEmptyId(): void {
		$this->expectException( InvalidArgumentException::class );
		new SelectOption( '', 'Draft' );
	}

	public function testThrowsOnEmptyLabel(): void {
		$this->expectException( InvalidArgumentException::class );
		new SelectOption( 'x', '' );
	}

	public function testThrowsOnWhitespaceOnlyLabel(): void {
		$this->expectException( InvalidArgumentException::class );
		new SelectOption( 'x', '   ' );
	}

	public function testToJsonReturnsIdAndLabel(): void {
		$option = new SelectOption( 'abc', 'Draft' );

		$this->assertSame( [ 'id' => 'abc', 'label' => 'Draft' ], $option->toJson() );
	}

	public function testFromJsonBuildsFromIdAndLabel(): void {
		$option = SelectOption::fromJson( [ 'id' => 'abc', 'label' => 'Draft' ] );

		$this->assertEquals( new SelectOption( 'abc', 'Draft' ), $option );
	}

	public function testFromJsonThrowsOnMissingId(): void {
		$this->expectException( InvalidArgumentException::class );
		SelectOption::fromJson( [ 'label' => 'Draft' ] );
	}

	public function testFromJsonThrowsOnMissingLabel(): void {
		$this->expectException( InvalidArgumentException::class );
		SelectOption::fromJson( [ 'id' => 'abc' ] );
	}

	public function testFromJsonThrowsOnLegacyStringShape(): void {
		$this->expectException( InvalidArgumentException::class );
		SelectOption::fromJson( 'Draft' );
	}

	public function testEqualsComparesAllFields(): void {
		$a = new SelectOption( 'abc', 'Draft' );
		$b = new SelectOption( 'abc', 'Draft' );
		$c = new SelectOption( 'abc', 'Review' );
		$d = new SelectOption( 'xyz', 'Draft' );

		$this->assertTrue( $a->equals( $b ) );
		$this->assertFalse( $a->equals( $c ) );
		$this->assertFalse( $a->equals( $d ) );
	}

	public function testNormalizedLabelTrimsAndLowercases(): void {
		$option = new SelectOption( 'abc', '  DrAfT  ' );

		$this->assertSame( 'draft', $option->normalizedLabel() );
	}

}
