<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Statement
 */
class StatementTest extends TestCase {

	public function testStatementEqualsOneWithTheSamePropertyTypeAndValue(): void {
		$this->assertTrue(
			TestStatement::build( 'Website', 'https://example.com', 'url' )->equals(
				TestStatement::build( 'Website', 'https://example.com', 'url' )
			)
		);
	}

	public function testStatementDiffersFromOneWithAnotherValue(): void {
		$this->assertFalse(
			TestStatement::build( 'Motto', 'Make things' )->equals( TestStatement::build( 'Motto', 'Break things' ) )
		);
	}

	public function testStatementDiffersFromOneWithAnotherPropertyType(): void {
		$this->assertFalse(
			TestStatement::build( 'Website', 'example.com', 'url' )->equals( TestStatement::build( 'Website', 'example.com', 'text' ) )
		);
	}

	public function testValuesThatOnlyReadAlikeAsNumbersDiffer(): void {
		$this->assertFalse(
			TestStatement::build( 'Code', '10' )->equals( TestStatement::build( 'Code', '1e1' ) )
		);
	}

}
