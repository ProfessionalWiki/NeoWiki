<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\ValueFormat\Formats;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\RelationFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\RelationFormat
 */
class RelationFormatTest extends TestCase {

	public function testBuildNeo4jValueReturnsSkipIndicator(): void {
		$this->assertSame(
			ValueFormat::NO_NEO4J_VALUE,
			$this->newFormat()->buildNeo4jValue( new RelationValue( TestRelation::build() ) )
		);
	}

	private function newFormat(): RelationFormat {
		return new RelationFormat();
	}

}
