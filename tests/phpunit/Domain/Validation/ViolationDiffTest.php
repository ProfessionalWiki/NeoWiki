<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Validation\ViolationDiff
 */
class ViolationDiffTest extends TestCase {

	public function testEmptyProposedReturnsEmpty(): void {
		$this->assertSame( [], ViolationDiff::newViolations( [], [] ) );
		$this->assertSame( [], ViolationDiff::newViolations( [], [ $this->required( 'Status' ) ] ) );
	}

	public function testEmptyPriorReturnsAllProposed(): void {
		$proposed = [ $this->required( 'Status' ), $this->required( 'Name' ) ];
		$this->assertSame( $proposed, ViolationDiff::newViolations( $proposed, [] ) );
	}

	public function testIdenticalProposedAndPriorReturnsEmpty(): void {
		$violations = [ $this->required( 'Status' ), $this->required( 'Name' ) ];
		$this->assertSame( [], ViolationDiff::newViolations( $violations, $violations ) );
	}

	public function testReturnsOnlyProposedNotInPrior(): void {
		$alpha = $this->required( 'Alpha' );
		$beta = $this->required( 'Beta' );
		$gamma = $this->required( 'Gamma' );

		$result = ViolationDiff::newViolations(
			proposed: [ $alpha, $beta, $gamma ],
			prior: [ $alpha ],
		);

		$this->assertSame( [ $beta, $gamma ], $result );
	}

	public function testMatchingIgnoresOrderAndExtraPrior(): void {
		$alpha = $this->required( 'Alpha' );
		$beta = $this->required( 'Beta' );

		$result = ViolationDiff::newViolations(
			proposed: [ $beta ],
			prior: [ $alpha, $beta ],
		);

		$this->assertSame( [], $result );
	}

	public function testSamePropertyDifferentCodeIsConsideredNew(): void {
		$required = $this->required( 'Status' );
		$tooShort = new Violation( new PropertyName( 'Status' ), 'too-short' );

		$result = ViolationDiff::newViolations(
			proposed: [ $tooShort ],
			prior: [ $required ],
		);

		$this->assertSame( [ $tooShort ], $result );
	}

	public function testSameCodeDifferentPropertyIsConsideredNew(): void {
		$alpha = $this->required( 'Alpha' );
		$beta = $this->required( 'Beta' );

		$result = ViolationDiff::newViolations(
			proposed: [ $beta ],
			prior: [ $alpha ],
		);

		$this->assertSame( [ $beta ], $result );
	}

	public function testNullPropertyNameMatchesNullPropertyName(): void {
		$schemaNotFound = new Violation( propertyName: null, code: 'schema-not-found' );

		$result = ViolationDiff::newViolations(
			proposed: [ $schemaNotFound ],
			prior: [ $schemaNotFound ],
		);

		$this->assertSame( [], $result );
	}

	private function required( string $property ): Violation {
		return new Violation( new PropertyName( $property ), 'required' );
	}

}
