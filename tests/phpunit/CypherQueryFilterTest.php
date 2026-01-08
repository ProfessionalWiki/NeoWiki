<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\CypherQueryFilter;

/**
 * @covers \ProfessionalWiki\NeoWiki\CypherQueryFilter
 */
class CypherQueryFilterTest extends TestCase {

	private function isReadQuery( string $query ): bool {
		return ( new CypherQueryFilter() )->isReadQuery( $query );
	}

	public function testRejectsSimpleWriteOperation(): void {
		$query = "CREATE (n:Person {name: 'Alice'})";
		$this->assertFalse( $this->isReadQuery( $query ), 'Filter should reject a simple CREATE operation' );
	}

	public function testRejectsComplexWriteOperation(): void {
		$query = "MATCH (n:Person) WHERE n.name = 'Alice' SET n.age = 30";
		$this->assertFalse( $this->isReadQuery( $query ), 'Filter should reject a complex write operation' );
	}

	public function testRejectsSimpleFunctionCall(): void {
		$query = "RETURN toUpper('hello')";
		$this->assertFalse( $this->isReadQuery( $query ), 'Filter should reject a simple function call' );
	}

	public function testRejectsNestedFunctionCall(): void {
		$query = "RETURN size(split(toString(42), ''))";
		$this->assertFalse( $this->isReadQuery( $query ), 'Filter should reject nested function calls' );
	}

	public function testAllowsValidReadQuery(): void {
		$query = "MATCH (n:Person) WHERE n.name = 'Alice' RETURN n";
		$this->assertTrue( $this->isReadQuery( $query ), 'Filter should allow a valid read query' );
	}

	public function testAllowsParenthesesInStrings(): void {
		$query = "MATCH (n:Person) WHERE n.name = '(Alice)' RETURN n";
		$this->assertTrue( $this->isReadQuery( $query ), 'Filter should allow parentheses in strings' );
	}

	/**
	 * @dataProvider writeOperationProvider
	 */
	public function testRejectsVariousWriteOperations( string $keyword ): void {
		$query = "$keyword (n:Label)";
		$this->assertFalse( $this->isReadQuery( $query ), "Filter should reject '$keyword' operation" );
	}

	public static function writeOperationProvider(): array {
		return [
			[ 'CREATE' ],
			[ 'SET' ],
			[ 'DELETE' ],
			[ 'REMOVE' ],
			[ 'MERGE' ],
			[ 'DROP' ],
		];
	}

	public function testAllowsWriteKeywordInString(): void {
		$query = "MATCH (n) WHERE n.action = 'CREATE' RETURN n";
		$this->assertTrue( $this->isReadQuery( $query ), 'Filter should allow write keyword in a string' );
	}

	public function testAllowsPartialKeywordMatch(): void {
		$query = "MATCH (n) WHERE n.name CONTAINS 'create' RETURN n";
		$this->assertTrue(
			$this->isReadQuery( $query ),
			'Filter should allow partial matches of write operation keywords'
		);
	}

	public function testRejectsFunctionCallWithEmptyParentheses(): void {
		$query = 'RETURN rand()';
		$this->assertFalse(
			$this->isReadQuery( $query ),
			'Filter should reject function calls with empty parentheses'
		);
	}

	public function testAllowsNonFunctionParentheses(): void {
		$query = "MATCH (n:Person) WHERE n.age > 30 AND (n.name = 'Alice' OR n.name = 'Bob') RETURN n";
		$this->assertTrue(
			$this->isReadQuery( $query ),
			'Filter should allow non-function parentheses in queries'
		);
	}

	public function testHandlesComplexNestedStructures(): void {
		$query = "MATCH (n:Person) WHERE n.age > 30 AND (n.name = 'Alice' OR n.name = 'Bob') AND NOT (n.city = 'New York' AND n.job = 'Teacher') RETURN n";
		$this->assertTrue(
			$this->isReadQuery( $query ),
			'Filter should handle complex nested structures without false positives'
		);
	}

	public function testHandlesCommentsCorrectly(): void {
		$query = "MATCH (n:Person) // This is an inline comment\n" .
			"WHERE n.age > 30 /* This is a\n" .
			'multi-line comment */ RETURN n';
		$this->assertTrue( $this->isReadQuery( $query ), 'Filter should handle comments correctly' );
	}

	public function testRejectsFunctionInWhereClause(): void {
		$query = "MATCH (n:Person) WHERE toUpper(n.name) = 'ALICE' RETURN n";
		$this->assertFalse(
			$this->isReadQuery( $query ),
			'Filter should reject function calls in WHERE clause'
		);
	}

	/**
	 * @dataProvider maliciousQueryProvider
	 */
	public function testRejectsMaliciousQueries( string $query ): void {
		$this->assertFalse( $this->isReadQuery( $query ) );
	}

	public static function maliciousQueryProvider(): iterable {
		yield 'Hiding in string literals' => [
				'MATCH (n)
WITH n, "CREATE (m:Malicious)" AS q
CALL apoc.cypher.run(q, {}) YIELD value
RETURN n, value'
		];

		yield 'Whitespace' => [
			'MATCH (n) RETURN n\u000A\u000DCREATE (m:Malicious)'
		];

		yield 'Subqueries' => [
			'MATCH (n)
WHERE n.id IN [x IN range(1,10) |
  HEAD([(WITH x CREATE (m:Hidden) RETURN x)[0]])]
RETURN n'
		];

		yield 'Procedure calls' => [
			"MATCH (n)
CALL dbms.procedures() YIELD name
WITH n, name WHERE name = 'dbms.security.changePassword'
CALL dbms.security.changePassword('newpassword')
RETURN n"
		];

		yield 'Dynamic property name' => [
			'MATCH (n)
SET n["RETURN"] = "Malicious"
RETURN n'
		];
	}

}
