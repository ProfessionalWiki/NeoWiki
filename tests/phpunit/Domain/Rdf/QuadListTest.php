<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\Quad
 */
class QuadListTest extends TestCase {

	private function quad( string $object ): Quad {
		return new Quad(
			new Iri( 'http://example.org/s' ),
			new Iri( 'http://example.org/p' ),
			new Iri( $object ),
			new Iri( 'http://example.org/g' ),
		);
	}

	public function testContainsFindsEqualQuad(): void {
		$list = new QuadList( $this->quad( 'http://example.org/a' ), $this->quad( 'http://example.org/b' ) );

		$this->assertTrue( $list->contains( $this->quad( 'http://example.org/b' ) ) );
	}

	public function testContainsRejectsAbsentQuad(): void {
		$list = new QuadList( $this->quad( 'http://example.org/a' ) );

		$this->assertFalse( $list->contains( $this->quad( 'http://example.org/z' ) ) );
	}

	public function testQuadEqualityComparesTheObjectTerm(): void {
		$literalQuad = new Quad(
			new Iri( 'http://example.org/s' ),
			new Iri( 'http://example.org/p' ),
			new Literal( 'x', new Iri( 'http://www.w3.org/2001/XMLSchema#string' ) ),
			new Iri( 'http://example.org/g' ),
		);

		$this->assertFalse( $literalQuad->equals( $this->quad( 'x' ) ) );
	}

	public function testMergeConcatenatesBothLists(): void {
		$merged = ( new QuadList( $this->quad( 'http://example.org/a' ) ) )
			->merge( new QuadList( $this->quad( 'http://example.org/b' ) ) );

		$this->assertCount( 2, $merged );
		$this->assertTrue( $merged->contains( $this->quad( 'http://example.org/a' ) ) );
		$this->assertTrue( $merged->contains( $this->quad( 'http://example.org/b' ) ) );
	}

	public function testEmptyListReportsEmpty(): void {
		$this->assertTrue( ( new QuadList() )->isEmpty() );
		$this->assertFalse( ( new QuadList( $this->quad( 'http://example.org/a' ) ) )->isEmpty() );
	}

}
