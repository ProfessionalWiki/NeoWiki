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

	public function testBothEmptyReturnsEmpty(): void {
		$this->assertSame( [], ViolationDiff::newViolations( [], [] ) );
	}

	public function testSingleViolationWithNoPriorReturnsIt(): void {
		$violation = new Violation( propertyName: new PropertyName( 'Website' ), code: 'invalid-url' );

		$result = ViolationDiff::newViolations( [ $violation ], [] );

		$this->assertEquals( [ $violation ], $result );
	}

	public function testIdenticalSetsReturnEmpty(): void {
		$v1 = new Violation( propertyName: new PropertyName( 'Age' ), code: 'below-minimum' );
		$v2 = new Violation( propertyName: new PropertyName( 'Website' ), code: 'invalid-url' );

		$result = ViolationDiff::newViolations( [ $v1, $v2 ], [ $v1, $v2 ] );

		$this->assertSame( [], $result );
	}

	public function testProposedIsProperSubsetOfPriorReturnsEmpty(): void {
		$v1 = new Violation( propertyName: new PropertyName( 'Age' ), code: 'below-minimum' );
		$v2 = new Violation( propertyName: new PropertyName( 'Website' ), code: 'invalid-url' );

		$result = ViolationDiff::newViolations( [ $v1 ], [ $v1, $v2 ] );

		$this->assertSame( [], $result );
	}

	public function testProposedIsSupersetOfPriorReturnsDifference(): void {
		$v1 = new Violation( propertyName: new PropertyName( 'Age' ), code: 'below-minimum' );
		$v2 = new Violation( propertyName: new PropertyName( 'Website' ), code: 'invalid-url' );
		$v3 = new Violation( propertyName: new PropertyName( 'Name' ), code: 'too-long' );

		$result = ViolationDiff::newViolations( [ $v1, $v2, $v3 ], [ $v1 ] );

		$this->assertEquals( [ $v2, $v3 ], $result );
	}

	public function testSameCodeOnDifferentPropertiesAreDistinct(): void {
		$v1 = new Violation( propertyName: new PropertyName( 'Website' ), code: 'required' );
		$v2 = new Violation( propertyName: new PropertyName( 'Email' ), code: 'required' );

		$result = ViolationDiff::newViolations( [ $v1, $v2 ], [ $v1 ] );

		$this->assertEquals( [ $v2 ], $result );
	}

	public function testSamePropertyWithDifferentCodesAreDistinct(): void {
		$v1 = new Violation( propertyName: new PropertyName( 'Age' ), code: 'below-minimum' );
		$v2 = new Violation( propertyName: new PropertyName( 'Age' ), code: 'above-maximum' );

		$result = ViolationDiff::newViolations( [ $v1, $v2 ], [ $v1 ] );

		$this->assertEquals( [ $v2 ], $result );
	}

	public function testSubjectLevelViolationDistinctFromPropertyLevel(): void {
		$subjectLevel = new Violation( propertyName: null, code: 'required' );
		$propertyLevel = new Violation( propertyName: new PropertyName( 'Name' ), code: 'required' );

		$result = ViolationDiff::newViolations( [ $subjectLevel, $propertyLevel ], [ $subjectLevel ] );

		$this->assertEquals( [ $propertyLevel ], $result );
	}

	public function testArgsDifferencesDoNotDistinguish(): void {
		$proposed = new Violation( propertyName: new PropertyName( 'Age' ), code: 'below-minimum', args: [ 'min' => 18 ] );
		$prior = new Violation( propertyName: new PropertyName( 'Age' ), code: 'below-minimum', args: [ 'min' => 21 ] );

		$result = ViolationDiff::newViolations( [ $proposed ], [ $prior ] );

		$this->assertSame( [], $result );
	}

	public function testValuePartIndexDifferencesDoNotDistinguish(): void {
		$proposed = new Violation( propertyName: new PropertyName( 'Website' ), code: 'invalid-url', valuePartIndex: 0 );
		$prior = new Violation( propertyName: new PropertyName( 'Website' ), code: 'invalid-url', valuePartIndex: 1 );

		$result = ViolationDiff::newViolations( [ $proposed ], [ $prior ] );

		$this->assertSame( [], $result );
	}

}
