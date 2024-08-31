<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\Neo\CypherQueryFilter;

#[CoversClass( CypherQueryFilter::class )]
class CypherQueryFilterTest extends TestCase {

	private CypherQueryFilter $filter;

	protected function setUp(): void {
		parent::setUp();
		$this->filter = new CypherQueryFilter();
	}

	public function testFilterQueryRejectsSimpleWriteOperation(): void {
		$query = "CREATE (n:Person {name: 'Alice'})";
		$this->assertFalse( $this->filter->filterQuery( $query ), 'Filter should reject a simple CREATE operation' );
	}

	public function testFilterQueryRejectsComplexWriteOperation(): void {
		$query = "MATCH (n:Person) WHERE n.name = 'Alice' SET n.age = 30";
		$this->assertFalse( $this->filter->filterQuery( $query ), 'Filter should reject a complex write operation' );
	}

	public function testFilterQueryRejectsSimpleFunctionCall(): void {
		$query = "RETURN toUpper('hello')";
		$this->assertFalse( $this->filter->filterQuery( $query ), 'Filter should reject a simple function call' );
	}

	public function testFilterQueryRejectsNestedFunctionCall(): void {
		$query = "RETURN size(split(toString(42), ''))";
		$this->assertFalse( $this->filter->filterQuery( $query ), 'Filter should reject nested function calls' );
	}

	public function testFilterQueryAllowsValidReadQuery(): void {
		$query = "MATCH (n:Person) WHERE n.name = 'Alice' RETURN n";
		$this->assertTrue( $this->filter->filterQuery( $query ), 'Filter should allow a valid read query' );
	}

	public function testFilterQueryAllowsParenthesesInStrings(): void {
		$query = "MATCH (n:Person) WHERE n.name = '(Alice)' RETURN n";
		$this->assertTrue( $this->filter->filterQuery( $query ), 'Filter should allow parentheses in strings' );
	}

	#[DataProvider( 'writeOperationProvider' )]
	public function testFilterQueryRejectsVariousWriteOperations( string $keyword ): void {
		$query = "$keyword (n:Label)";
		$this->assertFalse( $this->filter->filterQuery( $query ), "Filter should reject '$keyword' operation" );
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

	public function testFilterQueryAllowsWriteKeywordInString(): void {
		$query = "MATCH (n) WHERE n.action = 'CREATE' RETURN n";
		$this->assertTrue( $this->filter->filterQuery( $query ), 'Filter should allow write keyword in a string' );
	}

	public function testFilterQueryAllowsPartialKeywordMatch(): void {
		$query = "MATCH (n) WHERE n.name CONTAINS 'create' RETURN n";
		$this->assertTrue(
			$this->filter->filterQuery( $query ),
			'Filter should allow partial matches of write operation keywords'
		);
	}

	public function testFilterQueryRejectsFunctionCallWithEmptyParentheses(): void {
		$query = 'RETURN rand()';
		$this->assertFalse(
			$this->filter->filterQuery( $query ),
			'Filter should reject function calls with empty parentheses'
		);
	}

	public function testFilterQueryAllowsNonFunctionParentheses(): void {
		$query = "MATCH (n:Person) WHERE n.age > 30 AND (n.name = 'Alice' OR n.name = 'Bob') RETURN n";
		$this->assertTrue(
			$this->filter->filterQuery( $query ),
			'Filter should allow non-function parentheses in queries'
		);
	}

	public function testFilterQueryHandlesComplexNestedStructures(): void {
		$query = "MATCH (n:Person) WHERE n.age > 30 AND (n.name = 'Alice' OR n.name = 'Bob') AND NOT (n.city = 'New York' AND n.job = 'Teacher') RETURN n";
		$this->assertTrue(
			$this->filter->filterQuery( $query ),
			'Filter should handle complex nested structures without false positives'
		);
	}

	public function testFilterQueryHandlesCommentsCorrectly(): void {
		$query = "MATCH (n:Person) // This is an inline comment\n" .
			"WHERE n.age > 30 /* This is a\n" .
			'multi-line comment */ RETURN n';
		$this->assertTrue( $this->filter->filterQuery( $query ), 'Filter should handle comments correctly' );
	}

	public function testFilterQueryRejectsFunctionInWhereClause(): void {
		$query = "MATCH (n:Person) WHERE toUpper(n.name) = 'ALICE' RETURN n";
		$this->assertFalse(
			$this->filter->filterQuery( $query ),
			'Filter should reject function calls in WHERE clause'
		);
	}

	#[DataProvider( 'maliciousQueryProvider' )]
	public function testRejectsMaliciousQueries( string $query ): void {
		$this->assertFalse( $this->filter->filterQuery( $query ) );
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
