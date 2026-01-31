<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\KeywordCypherQueryValidator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\KeywordCypherQueryValidator
 */
class KeywordCypherQueryValidatorTest extends TestCase {

	private function queryIsAllowed( string $query ): bool {
		return ( new KeywordCypherQueryValidator() )->queryIsAllowed( $query );
	}

	public function testRejectsSimpleWriteOperation(): void {
		$query = "CREATE (n:Person {name: 'Alice'})";
		$this->assertFalse( $this->queryIsAllowed( $query ), 'Filter should reject a simple CREATE operation' );
	}

	public function testRejectsComplexWriteOperation(): void {
		$query = "MATCH (n:Person) WHERE n.name = 'Alice' SET n.age = 30";
		$this->assertFalse( $this->queryIsAllowed( $query ), 'Filter should reject a complex write operation' );
	}

	public function testAllowsSimpleFunctionCall(): void {
		$query = "RETURN toUpper('hello')";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow read-only function calls' );
	}

	public function testAllowsNestedFunctionCall(): void {
		$query = "RETURN size(split(toString(42), ''))";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow nested function calls' );
	}

	public function testAllowsValidReadQuery(): void {
		$query = "MATCH (n:Person) WHERE n.name = 'Alice' RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow a valid read query' );
	}

	public function testAllowsParenthesesInStrings(): void {
		$query = "MATCH (n:Person) WHERE n.name = '(Alice)' RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow parentheses in strings' );
	}

	/**
	 * @dataProvider writeOperationProvider
	 */
	public function testRejectsVariousWriteOperations( string $keyword ): void {
		$query = "$keyword (n:Label)";
		$this->assertFalse( $this->queryIsAllowed( $query ), "Filter should reject '$keyword' operation" );
	}

	public static function writeOperationProvider(): array {
		return [
			[ 'CREATE' ],
			[ 'SET' ],
			[ 'DELETE' ],
			[ 'REMOVE' ],
			[ 'MERGE' ],
			[ 'DROP' ],
			[ 'CALL' ],
			[ 'LOAD' ],
			[ 'FOREACH' ],
			[ 'GRANT' ],
			[ 'DENY' ],
			[ 'REVOKE' ],
			[ 'SHOW' ],
		];
	}

	public function testAllowsWriteKeywordInString(): void {
		$query = "MATCH (n) WHERE n.action = 'CREATE' RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow write keyword in a string' );
	}

	public function testAllowsAdminKeywordInString(): void {
		$query = "MATCH (n) WHERE n.action = 'GRANT' RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow admin keyword in a string' );
	}

	public function testAllowsShowKeywordInString(): void {
		$query = "MATCH (n) WHERE n.type = 'SHOW' RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow SHOW keyword in a string' );
	}

	public function testAllowsPartialKeywordMatch(): void {
		$query = "MATCH (n) WHERE n.name CONTAINS 'create' RETURN n";
		$this->assertTrue(
			$this->queryIsAllowed( $query ),
			'Filter should allow partial matches of write operation keywords'
		);
	}

	public function testAllowsFunctionCallWithEmptyParentheses(): void {
		$query = 'RETURN rand()';
		$this->assertTrue(
			$this->queryIsAllowed( $query ),
			'Filter should allow read-only function calls'
		);
	}

	public function testAllowsNonFunctionParentheses(): void {
		$query = "MATCH (n:Person) WHERE n.age > 30 AND (n.name = 'Alice' OR n.name = 'Bob') RETURN n";
		$this->assertTrue(
			$this->queryIsAllowed( $query ),
			'Filter should allow non-function parentheses in queries'
		);
	}

	public function testHandlesComplexNestedStructures(): void {
		$query = "MATCH (n:Person) WHERE n.age > 30 AND (n.name = 'Alice' OR n.name = 'Bob') AND NOT (n.city = 'New York' AND n.job = 'Teacher') RETURN n";
		$this->assertTrue(
			$this->queryIsAllowed( $query ),
			'Filter should handle complex nested structures without false positives'
		);
	}

	public function testHandlesCommentsCorrectly(): void {
		$query = "MATCH (n:Person) // This is an inline comment\n" .
			"WHERE n.age > 30 /* This is a\n" .
			'multi-line comment */ RETURN n';
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should handle comments correctly' );
	}

	public function testAllowsFunctionInWhereClause(): void {
		$query = "MATCH (n:Person) WHERE toUpper(n.name) = 'ALICE' RETURN n";
		$this->assertTrue(
			$this->queryIsAllowed( $query ),
			'Filter should allow read-only function calls in WHERE clause'
		);
	}

	/**
	 * @dataProvider writeQueryProvider
	 */
	public function testRejectsWriteQueries( string $query, string $description ): void {
		$this->assertFalse( $this->queryIsAllowed( $query ), $description );
	}

	public static function writeQueryProvider(): iterable {
		// Standard write operations
		yield 'Simple CREATE' => [
			"CREATE (n:Person {name: 'Alice'})",
			'Should reject simple CREATE'
		];

		yield 'CREATE with MATCH' => [
			"MATCH (a:Person) CREATE (b:Person)-[:KNOWS]->(a)",
			'Should reject CREATE after MATCH'
		];

		yield 'Simple SET' => [
			"MATCH (n:Person) SET n.age = 30",
			'Should reject SET operation'
		];

		yield 'SET with multiple properties' => [
			"MATCH (n) SET n.a = 1, n.b = 2",
			'Should reject SET with multiple properties'
		];

		yield 'Simple DELETE' => [
			"MATCH (n:Person) DELETE n",
			'Should reject DELETE operation'
		];

		yield 'DETACH DELETE' => [
			"MATCH (n:Person) DETACH DELETE n",
			'Should reject DETACH DELETE'
		];

		yield 'Simple REMOVE' => [
			"MATCH (n:Person) REMOVE n.age",
			'Should reject REMOVE operation'
		];

		yield 'REMOVE label' => [
			"MATCH (n:Person) REMOVE n:Person",
			'Should reject REMOVE label'
		];

		yield 'Simple MERGE' => [
			"MERGE (n:Person {name: 'Alice'})",
			'Should reject MERGE operation'
		];

		yield 'MERGE with ON CREATE' => [
			"MERGE (n:Person {name: 'Alice'}) ON CREATE SET n.created = timestamp()",
			'Should reject MERGE with ON CREATE'
		];

		yield 'Simple DROP' => [
			"DROP CONSTRAINT constraint_name",
			'Should reject DROP operation'
		];

		// Case variations
		yield 'Lowercase create' => [
			"create (n:Person)",
			'Should reject lowercase create'
		];

		yield 'Mixed case CrEaTe' => [
			"CrEaTe (n:Person)",
			'Should reject mixed case create'
		];

		yield 'Lowercase set' => [
			"match (n) set n.x = 1",
			'Should reject lowercase set'
		];

		yield 'Lowercase delete' => [
			"match (n) delete n",
			'Should reject lowercase delete'
		];

		// CALL operations (procedure calls)
		yield 'Simple CALL' => [
			"CALL db.labels()",
			'Should reject CALL operation'
		];

		yield 'CALL with YIELD' => [
			"CALL db.labels() YIELD label RETURN label",
			'Should reject CALL with YIELD'
		];

		yield 'CALL apoc procedure' => [
			"CALL apoc.cypher.run('CREATE (n)', {})",
			'Should reject APOC procedure calls'
		];

		yield 'CALL dbms procedure' => [
			"CALL dbms.security.changePassword('newpass')",
			'Should reject dbms procedure calls'
		];

		yield 'Lowercase call' => [
			"call db.labels()",
			'Should reject lowercase call'
		];

		// LOAD operations
		yield 'LOAD CSV' => [
			"LOAD CSV FROM 'file:///etc/passwd' AS row RETURN row",
			'Should reject LOAD CSV'
		];

		yield 'LOAD CSV WITH HEADERS' => [
			"LOAD CSV WITH HEADERS FROM 'http://example.com/data.csv' AS row RETURN row",
			'Should reject LOAD CSV WITH HEADERS'
		];

		yield 'Lowercase load' => [
			"load csv from 'file.csv' as row return row",
			'Should reject lowercase load'
		];

		// Unicode escape obfuscation attempts
		yield 'Unicode escape before CREATE' => [
			'MATCH (n) RETURN n\u000ACREATE (m:Malicious)',
			'Should reject CREATE hidden after unicode escape'
		];

		yield 'Unicode escape CRLF before CREATE' => [
			'MATCH (n) RETURN n\u000D\u000ACREATE (m:Malicious)',
			'Should reject CREATE after CRLF unicode escapes'
		];

		yield 'Unicode escape before CALL' => [
			'MATCH (n)\u000ACALL db.labels()',
			'Should reject CALL hidden after unicode escape'
		];

		yield 'Unicode escape at start of query' => [
			'\u000ACREATE (n:Malicious)',
			'Should reject CREATE after unicode escape at query start'
		];

		yield 'Unicode escape before SET' => [
			'MATCH (n)\u0009SET n.x = 1',
			'Should reject SET hidden after unicode tab escape'
		];

		yield 'Unicode escape before DELETE' => [
			'MATCH (n)\u000BDELETE n',
			'Should reject DELETE hidden after unicode escape'
		];

		yield 'Multiple unicode escapes before keyword' => [
			'MATCH (n)\u000A\u000D\u0009\u000BCREATE (m)',
			'Should reject CREATE after multiple unicode escapes'
		];

		yield 'Unicode escape with uppercase hex' => [
			'MATCH (n)\u000ACREATE (m)',
			'Should reject with uppercase hex digits'
		];

		yield 'Unicode escape with lowercase hex' => [
			'MATCH (n)\u000acreate (m)',
			'Should reject with lowercase hex digits'
		];

		yield 'Unicode escape before LOAD' => [
			"\u000ALOAD CSV FROM 'file.csv' AS row",
			'Should reject LOAD hidden after unicode escape'
		];

		yield 'Unicode escape before FOREACH' => [
			"MATCH (n)\u000AFOREACH (x IN [1] | SET n.x = x)",
			'Should reject FOREACH hidden after unicode escape'
		];

		yield 'CREATE in subquery' => [
			"MATCH (n) WHERE n.id IN [x IN range(1,10) | HEAD([(WITH x CREATE (m:Hidden) RETURN x)[0]])] RETURN n",
			'Should reject CREATE in subquery'
		];

		yield 'CALL in complex query' => [
			"MATCH (n) WITH n CALL apoc.cypher.run('CREATE (m)', {}) YIELD value RETURN n, value",
			'Should reject CALL in complex query'
		];

		yield 'SET via dynamic property' => [
			'MATCH (n) SET n["property"] = "value" RETURN n',
			'Should reject SET with dynamic property'
		];

		yield 'Multiple statements with semicolon' => [
			"MATCH (n) RETURN n; CREATE (m:Malicious)",
			'Should reject multiple statements with CREATE'
		];

		yield 'FOREACH with SET' => [
			"MATCH (n) FOREACH (x IN [1,2,3] | SET n.prop = x)",
			'Should reject FOREACH with SET'
		];

		yield 'FOREACH with CREATE' => [
			"FOREACH (name IN ['Alice', 'Bob'] | CREATE (:Person {name: name}))",
			'Should reject FOREACH with CREATE'
		];

		// Admin keywords
		yield 'GRANT role' => [
			"GRANT ROLE admin TO user1",
			'Should reject GRANT operation'
		];

		yield 'DENY read' => [
			"DENY READ {prop} ON GRAPH * TO role1",
			'Should reject DENY operation'
		];

		yield 'REVOKE role' => [
			"REVOKE ROLE admin FROM user1",
			'Should reject REVOKE operation'
		];

		yield 'Lowercase grant' => [
			"grant role admin to user1",
			'Should reject lowercase grant'
		];

		yield 'Lowercase deny' => [
			"deny read {prop} on graph * to role1",
			'Should reject lowercase deny'
		];

		yield 'Lowercase revoke' => [
			"revoke role admin from user1",
			'Should reject lowercase revoke'
		];

		yield 'Mixed case GrAnT' => [
			"GrAnT ROLE admin TO user1",
			'Should reject mixed case grant'
		];

		yield 'Mixed case DeNy' => [
			"DeNy READ {prop} ON GRAPH * TO role1",
			'Should reject mixed case deny'
		];

		yield 'Mixed case ReVoKe' => [
			"ReVoKe ROLE admin FROM user1",
			'Should reject mixed case revoke'
		];

		// SHOW keywords (information disclosure)
		yield 'SHOW DATABASES' => [
			"SHOW DATABASES",
			'Should reject SHOW DATABASES'
		];

		yield 'SHOW USERS' => [
			"SHOW USERS",
			'Should reject SHOW USERS'
		];

		yield 'Lowercase show' => [
			"show databases",
			'Should reject lowercase show'
		];

		yield 'Mixed case ShOw' => [
			"ShOw DATABASES",
			'Should reject mixed case show'
		];

		// Comment-based attempts (should still be caught after comment removal)
		yield 'CREATE after inline comment' => [
			"MATCH (n) // comment\nCREATE (m)",
			'Should reject CREATE after inline comment'
		];

		yield 'CREATE after block comment' => [
			"MATCH (n) /* block comment */ CREATE (m)",
			'Should reject CREATE after block comment'
		];
	}

}
