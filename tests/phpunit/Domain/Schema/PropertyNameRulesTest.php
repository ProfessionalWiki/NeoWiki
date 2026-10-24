<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameProblem;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameRule;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameRules;
use ProfessionalWiki\NeoWiki\Tests\Data\PropertyNameVectors;

/**
 * The shared vectors in tests/vectors/property-names.json, which the TypeScript suite runs against its own rules, so
 * the Schema editor refuses what the wiki refuses.
 *
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameRules
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameProblem
 */
class PropertyNameRulesTest extends TestCase {

	/**
	 * @dataProvider validNameProvider
	 */
	public function testAcceptsName( string $name ): void {
		$this->assertNull( PropertyNameRules::problemWith( $name ) );
	}

	public static function validNameProvider(): iterable {
		foreach ( PropertyNameVectors::valid() as $vector ) {
			yield $vector['case'] => [ $vector['name'] ];
		}
	}

	/**
	 * @dataProvider invalidNameProvider
	 */
	public function testNamesTheFirstRuleTheNameBreaks( string $name, PropertyNameProblem $problem ): void {
		$this->assertEquals( $problem, PropertyNameRules::problemWith( $name ) );
	}

	public static function invalidNameProvider(): iterable {
		foreach ( PropertyNameVectors::invalid() as $vector ) {
			yield $vector['case'] => [ $vector['name'], self::problemFrom( $vector['name'], $vector['problem'] ) ];
		}
	}

	/**
	 * @param array{rule: string, details: string[]} $problem
	 */
	private static function problemFrom( string $name, array $problem ): PropertyNameProblem {
		return new PropertyNameProblem( $name, PropertyNameRule::from( $problem['rule'] ), $problem['details'] );
	}

	/**
	 * @dataProvider schemaProvider
	 */
	public function testFindsEveryProblemWithTheNamesOfOneSchema( array $names, array $problems ): void {
		$this->assertEquals( $problems, PropertyNameRules::problemsInSchema( $names ) );
	}

	public static function schemaProvider(): iterable {
		foreach ( PropertyNameVectors::schemas() as $vector ) {
			yield $vector['case'] => [
				$vector['names'],
				array_map( static fn ( array $problem ) => self::problemFrom( $problem['name'], $problem ), $vector['problems'] ),
			];
		}
	}

	public function testRefusesNameBreakingARuleSayingWhichRule(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Property name "id" is reserved' );

		PropertyNameRules::assertValid( 'id' );
	}

	public function testRefusalOfNameToRespellNamesItsInvisibleCharacters(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( "Property name \"Birth\u{200B}date\" must be written as \"Birthdate\": it contains U+200B" );

		PropertyNameRules::assertValid( "Birth\u{200B}date" );
	}

}
