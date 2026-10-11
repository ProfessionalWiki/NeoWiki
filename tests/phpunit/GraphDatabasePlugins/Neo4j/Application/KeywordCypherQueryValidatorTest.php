<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator
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
			[ 'INSERT' ],
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

	public function testAllowsWriteKeywordInQuotedName(): void {
		$query = 'MATCH (a)-[:`Part of set`]->(b) RETURN a';
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow a write keyword in a quoted name' );
	}

	public function testAllowsWriteKeywordInStringHoldingEscapedQuote(): void {
		$query = "MATCH (n) WHERE n.title = 'Don\\'t set' RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow a write keyword after an escaped quote' );
	}

	public function testAllowsWriteKeywordInDoubleQuotedString(): void {
		$query = 'MATCH (n) WHERE n.label = "Data \\"set\\"" RETURN n';
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow a write keyword in a double-quoted string' );
	}

	public function testAllowsWriteKeywordInLongString(): void {
		$text = str_repeat( 'Lorem ipsum ', 1000 ) . 'set';
		$query = "MATCH (n) WHERE n.text = '$text' OR n.title = \"$text\" RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow a write keyword at the end of a long string' );
	}

	public function testAllowsWriteKeywordAfterManyEscapes(): void {
		$text = str_repeat( chr( 92 ) . 'n', 50000 ) . 'set';
		$name = str_repeat( 'a``', 50000 ) . 'set';
		$query = "MATCH (n) WHERE n.text = '$text' OR n.title = \"$text\" OR n.`$name` = 1 RETURN n";
		$this->assertTrue(
			$this->queryIsAllowed( $query ),
			'Filter should allow a write keyword after many escapes in a string or quoted name'
		);
	}

	/**
	 * PCRE without its JIT gives up on a query long enough, which a backtrack limit of 1 stands in for.
	 */
	public function testRefusesAQueryWhoseEscapesItCannotDecode(): void {
		$backtrackLimit = (string)ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.backtrack_limit', '1' );

		try {
			$allowed = $this->queryIsAllowed( 'MATCH (n) WITH n ' . chr( 92 ) . 'u0053ET n.checked = true' );
		} finally {
			ini_set( 'pcre.backtrack_limit', $backtrackLimit );
		}

		$this->assertFalse( $allowed );
	}

	public function testAllowsPropertyNamedAfterAKeywordAndAnEscapedZero(): void {
		$query = 'MATCH (n) RETURN n.set' . chr( 92 ) . 'u0030';
		$this->assertTrue(
			$this->queryIsAllowed( $query ),
			'Filter should allow a keyword that an escaped digit extends into a longer name'
		);
	}

	public function testAllowsWriteKeywordInCommentSpanningLines(): void {
		$query = "MATCH (n) /* set the\nflag later */ RETURN n";
		$this->assertTrue( $this->queryIsAllowed( $query ), 'Filter should allow a write keyword in a block comment over two lines' );
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
		$query = 'MATCH (n) WHERE n.created > 0 RETURN n.callNumber';
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

		yield 'Simple INSERT' => [
			"INSERT (n:Person {name: 'Alice'})",
			'Should reject INSERT, the CREATE synonym'
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

		yield 'Unicode escape inside a keyword' => [
			'MATCH (n) \u0053ET n.checked = true',
			'Should reject SET spelled with a unicode escape'
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

		// Quoted names, strings and comments, whose delimiters must not hide what follows them. Most of
		// these queries hold a later quote, which one misread would pair with.
		yield 'SET between quoted names' => [
			'MATCH (a)-[:`Part of set`]->(b) SET a.checked = true RETURN a.`Name`',
			'Should reject SET between quoted names'
		];

		yield 'SET after a quoted name holding a quote' => [
			"MATCH (n:`O'Brien`) SET n.name = 'Alice'",
			'Should reject SET after a quoted name holding a quote'
		];

		yield 'SET after a string holding a backtick' => [
			"MATCH (n) WHERE n.quote = '`' SET n.`Part of collection` = 1",
			'Should reject SET after a string holding a backtick'
		];

		yield 'SET after a string holding a comment marker' => [
			"MATCH (c) WHERE c.Website = 'https://pro.wiki' SET c.checked = true",
			'Should reject SET after a string holding a comment marker'
		];

		yield 'SET after a comment holding quotes' => [
			"MATCH (n) // don't quote `\nSET n.name = 'Alice', n.`Part of collection` = 1",
			'Should reject SET after a comment holding quotes'
		];

		yield 'SET after a unicode escape that closes a quoted name' => [
			'MATCH (n) WHERE n.`Part of collection\u0060 = 1 SET n.checked = true RETURN n.`Name`',
			'Should reject SET after a unicode escape that closes a quoted name'
		];

		yield 'SET after a quoted name holding an escaped backslash before u0060' => [
			'MATCH (n) WHERE n.`Part\\\\u0060 of collection` = 1 SET n.checked = true RETURN n.`Name`',
			'Should reject SET after a quoted name whose backslash before u0060 is escaped'
		];

		yield 'SET after a quoted name closed by an escape after a backslash pair' => [
			'MATCH (n) WHERE n.`Part\\\\\\u0060 = 1 SET n.checked = true RETURN n.`Name`',
			'Should reject SET after a unicode escape that follows an escaped backslash'
		];

		yield 'SET after a quoted name ending in a backslash' => [
			'MATCH (n) WHERE n.`Part\\` = 1 SET n.checked = true RETURN n.`Name`',
			'Should reject SET after a quoted name ending in a backslash, which escapes nothing there'
		];

		yield 'SET after an escaped character beyond ASCII' => [
			'MATCH (n) WITH n, 1 AS \u0127 SET n.checked = true RETURN \'done\'',
			'Should reject SET after an escaped character that is no quote'
		];

		yield 'SET spelled with an escape after a comment of many backslashes' => [
			'MATCH (n) // ' . str_repeat( chr( 92 ), 100000 ) . "\n" . chr( 92 ) . 'u0053ET n.checked = true RETURN n',
			'Should reject SET spelled with a unicode escape after a comment too long to decode in one go'
		];

		yield 'SET after a string ending in an escaped backslash and an escaped surrogate' => [
			"MATCH (n) WITH n, '" . chr( 92 ) . 'u005C' . chr( 92 ) . "uD800' AS x SET n.checked = true RETURN 'done'",
			'Should reject SET after a string whose last escape names a surrogate, which is no character'
		];

		yield 'SET glued to a word by a comment' => [
			'MATCH (n) WITH n/**/SET n.checked = true',
			'Should reject SET that only a comment separates from the word before it'
		];

		yield 'SET after a line comment ended by a carriage return' => [
			"MATCH (n) // comment\rSET n.checked = true",
			'Should reject SET on the line after a comment ended by a carriage return'
		];

		yield 'SET between two block comments' => [
			'MATCH (n) /* a */ SET n.checked = true /* b */',
			'Should reject SET between two block comments'
		];
	}

}
