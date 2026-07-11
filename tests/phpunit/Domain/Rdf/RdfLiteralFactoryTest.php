<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory
 */
class RdfLiteralFactoryTest extends TestCase {

	private function xsd( string $local ): Iri {
		return new Iri( 'http://www.w3.org/2001/XMLSchema#' . $local );
	}

	public function testIntegerNumberIsXsdInteger(): void {
		$this->assertEquals( new Literal( '2019', $this->xsd( 'integer' ) ), RdfLiteralFactory::number( 2019 ) );
	}

	public function testFractionlessFloatIsXsdInteger(): void {
		$this->assertEquals( new Literal( '2019', $this->xsd( 'integer' ) ), RdfLiteralFactory::number( 2019.0 ) );
	}

	public function testDecimalFloatIsXsdDecimalWithoutScientificNotation(): void {
		$this->assertEquals( new Literal( '0.0001', $this->xsd( 'decimal' ) ), RdfLiteralFactory::number( 0.0001 ) );
	}

	public function testDecimalKeepsTrailingSignificantDigits(): void {
		$this->assertEquals( new Literal( '3.14159', $this->xsd( 'decimal' ) ), RdfLiteralFactory::number( 3.14159 ) );
	}

	public function testNonFiniteNumberYieldsNull(): void {
		$this->assertNull( RdfLiteralFactory::number( INF ) );
		$this->assertNull( RdfLiteralFactory::number( NAN ) );
	}

	public function testForScalarStringIsXsdString(): void {
		$this->assertEquals( new Literal( 'Editor', $this->xsd( 'string' ) ), RdfLiteralFactory::forScalar( 'Editor' ) );
	}

	public function testForScalarBooleanIsXsdBoolean(): void {
		$this->assertEquals( new Literal( 'false', $this->xsd( 'boolean' ) ), RdfLiteralFactory::forScalar( false ) );
	}

	public function testForScalarIntegerIsXsdInteger(): void {
		$this->assertEquals( new Literal( '2019', $this->xsd( 'integer' ) ), RdfLiteralFactory::forScalar( 2019 ) );
	}

	public function testForScalarRejectsNullAndArrays(): void {
		$this->assertNull( RdfLiteralFactory::forScalar( null ) );
		$this->assertNull( RdfLiteralFactory::forScalar( [ 'x' ] ) );
	}

}
