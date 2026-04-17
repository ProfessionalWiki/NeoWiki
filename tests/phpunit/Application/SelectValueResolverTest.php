<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\SelectValueResolver
 */
class SelectValueResolverTest extends TestCase {

	private function newProperty(): SelectProperty {
		return new SelectProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			options: [
				new SelectOption( id: 'opt1', label: 'Draft' ),
				new SelectOption( id: 'opt2', label: 'In Review' ),
				new SelectOption( id: 'opt3', label: 'Approved' ),
			],
			multiple: false,
		);
	}

	private function newResolver(): SelectValueResolver {
		return new SelectValueResolver();
	}

	public function testAcceptsOptionId(): void {
		$id = $this->newResolver()->resolve( $this->newProperty(), 'opt2' );

		$this->assertSame( 'opt2', $id );
	}

	public function testResolvesLabelToId(): void {
		$id = $this->newResolver()->resolve( $this->newProperty(), 'In Review' );

		$this->assertSame( 'opt2', $id );
	}

	public function testResolvesLabelCaseInsensitively(): void {
		$id = $this->newResolver()->resolve( $this->newProperty(), 'draft' );

		$this->assertSame( 'opt1', $id );
	}

	public function testResolvesLabelWithSurroundingWhitespace(): void {
		$id = $this->newResolver()->resolve( $this->newProperty(), '  Approved  ' );

		$this->assertSame( 'opt3', $id );
	}

	public function testAcceptsConsistentIdLabelObject(): void {
		$id = $this->newResolver()->resolve(
			$this->newProperty(),
			[ 'id' => 'opt1', 'label' => 'Draft' ]
		);

		$this->assertSame( 'opt1', $id );
	}

	public function testAcceptsConsistentIdLabelObjectWithCaseInsensitiveLabel(): void {
		$id = $this->newResolver()->resolve(
			$this->newProperty(),
			[ 'id' => 'opt1', 'label' => '  draft  ' ]
		);

		$this->assertSame( 'opt1', $id );
	}

	public function testRejectsInconsistentIdLabelObject(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Select value id/label mismatch' );

		$this->newResolver()->resolve(
			$this->newProperty(),
			[ 'id' => 'opt1', 'label' => 'WrongName' ]
		);
	}

	public function testRejectsUnknownValue(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Nonexistent' );

		$this->newResolver()->resolve( $this->newProperty(), 'Nonexistent' );
	}

	public function testRejectsObjectWithoutIdOrLabel(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->newResolver()->resolve( $this->newProperty(), [] );
	}

	public function testAcceptsObjectWithOnlyId(): void {
		$id = $this->newResolver()->resolve(
			$this->newProperty(),
			[ 'id' => 'opt3' ]
		);

		$this->assertSame( 'opt3', $id );
	}

	public function testObjectWithOnlyIdDoesNotFallBackToLabelMatch(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Draft' );

		$this->newResolver()->resolve(
			$this->newProperty(),
			[ 'id' => 'Draft' ]
		);
	}

	public function testAcceptsObjectWithOnlyLabel(): void {
		$id = $this->newResolver()->resolve(
			$this->newProperty(),
			[ 'label' => 'Approved' ]
		);

		$this->assertSame( 'opt3', $id );
	}

}
