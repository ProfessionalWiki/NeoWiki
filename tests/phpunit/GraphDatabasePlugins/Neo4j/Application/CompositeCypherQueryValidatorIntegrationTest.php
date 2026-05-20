<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CompositeCypherQueryValidator;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\ExplainCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::getCypherQueryValidator
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CompositeCypherQueryValidator
 * @group Database
 */
class CompositeCypherQueryValidatorIntegrationTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReadOnlyQueryIsAllowed(): void {
		$validator = NeoWikiExtension::getInstance()->getCypherQueryValidator();

		$this->assertTrue( $validator->queryIsAllowed( 'MATCH (n) RETURN n LIMIT 5' ) );
	}

	public function testWriteQueryIsRejected(): void {
		$validator = NeoWikiExtension::getInstance()->getCypherQueryValidator();

		$this->assertFalse( $validator->queryIsAllowed( 'CREATE (n:Test {name: "test"}) RETURN n' ) );
	}

	public function testAdminQueryIsRejected(): void {
		$validator = NeoWikiExtension::getInstance()->getCypherQueryValidator();

		$this->assertFalse( $validator->queryIsAllowed( 'STOP DATABASE neo4j' ) );
	}

	public function testAdminQueryPassesKeywordValidatorButIsRejectedByComposite(): void {
		$query = 'STOP DATABASE neo4j';

		$keywordValidator = new KeywordCypherQueryValidator();
		$this->assertTrue( $keywordValidator->queryIsAllowed( $query ) );

		$explainValidator = new ExplainCypherQueryValidator(
			NeoWikiExtension::getInstance()->getReadOnlyNeo4jClient()
		);
		$this->assertFalse( $explainValidator->queryIsAllowed( $query ) );

		$compositeValidator = new CompositeCypherQueryValidator( [ $keywordValidator, $explainValidator ] );
		$this->assertFalse( $compositeValidator->queryIsAllowed( $query ) );
	}

}
