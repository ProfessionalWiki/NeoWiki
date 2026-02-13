<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\CompositeCypherQueryValidator;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyCypherQueryValidator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\CompositeCypherQueryValidator
 */
class CompositeCypherQueryValidatorTest extends TestCase {

	public function testAllowsWhenAllValidatorsAllow(): void {
		$composite = new CompositeCypherQueryValidator( [
			$this->newAllowingValidator(),
			$this->newAllowingValidator(),
		] );

		$this->assertTrue( $composite->queryIsAllowed( 'MATCH (n) RETURN n' ) );
	}

	private function newAllowingValidator(): CypherQueryValidator {
		return new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				return true;
			}
		};
	}

	public function testRejectsWhenFirstValidatorRejects(): void {
		$composite = new CompositeCypherQueryValidator( [
			$this->newRejectingValidator(),
			$this->newAllowingValidator(),
		] );

		$this->assertFalse( $composite->queryIsAllowed( 'CREATE (n)' ) );
	}

	private function newRejectingValidator(): CypherQueryValidator {
		return new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				return false;
			}
		};
	}

	public function testRejectsWhenSecondValidatorRejects(): void {
		$composite = new CompositeCypherQueryValidator( [
			$this->newAllowingValidator(),
			$this->newRejectingValidator(),
		] );

		$this->assertFalse( $composite->queryIsAllowed( 'MATCH (n) RETURN n' ) );
	}

	public function testShortCircuitsOnFirstRejection(): void {
		$spy = new SpyCypherQueryValidator();

		$composite = new CompositeCypherQueryValidator( [
			$this->newRejectingValidator(),
			$spy,
		] );

		$composite->queryIsAllowed( 'CREATE (n)' );

		$this->assertSame( 0, $spy->callCount );
	}

	public function testAllowsWithNoValidators(): void {
		$composite = new CompositeCypherQueryValidator( [] );

		$this->assertTrue( $composite->queryIsAllowed( 'MATCH (n) RETURN n' ) );
	}

}
