<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference
 */
class SchemaReferenceTest extends TestCase {

	public function testLocalReferenceHasNoSource(): void {
		$reference = SchemaReference::local( new SchemaName( 'Person' ) );

		$this->assertNull( $reference->getSource() );
		$this->assertSame( 'Person', $reference->getName()->getText() );
	}

	public function testForeignReferenceKeepsItsSource(): void {
		$reference = new SchemaReference( 'otherwiki', new SchemaName( 'Person' ) );

		$this->assertSame( 'otherwiki', $reference->getSource() );
		$this->assertSame( 'Person', $reference->getName()->getText() );
	}

	public function testLocalReferenceSerializesToBareName(): void {
		$this->assertSame(
			'Person',
			SchemaReference::local( new SchemaName( 'Person' ) )->toSerializedValue()
		);
	}

	public function testForeignReferenceSerializesToObject(): void {
		$this->assertSame(
			[ 'source' => 'otherwiki', 'name' => 'Person' ],
			( new SchemaReference( 'otherwiki', new SchemaName( 'Person' ) ) )->toSerializedValue()
		);
	}

	public function testDeserializesBareNameAsLocal(): void {
		$reference = SchemaReference::fromSerializedValue( 'Person' );

		$this->assertNull( $reference->getSource() );
		$this->assertSame( 'Person', $reference->getName()->getText() );
	}

	public function testDeserializesObjectAsForeign(): void {
		$reference = SchemaReference::fromSerializedValue( [ 'source' => 'otherwiki', 'name' => 'Person' ] );

		$this->assertSame( 'otherwiki', $reference->getSource() );
		$this->assertSame( 'Person', $reference->getName()->getText() );
	}

	public function testDeserializingObjectWithoutSourceKeyIsRejected(): void {
		$this->expectException( InvalidArgumentException::class );
		SchemaReference::fromSerializedValue( [ 'name' => 'Person' ] );
	}

	public function testDeserializingObjectWithNonStringNameIsRejected(): void {
		$this->expectException( InvalidArgumentException::class );
		SchemaReference::fromSerializedValue( [ 'source' => 'otherwiki', 'name' => 42 ] );
	}

	public function testLocalReferencesWithTheSameNameAreEqual(): void {
		$this->assertTrue(
			SchemaReference::local( new SchemaName( 'Person' ) )
				->equals( SchemaReference::local( new SchemaName( 'Person' ) ) )
		);
	}

	public function testReferencesWithDifferentSourcesAreNotEqual(): void {
		$this->assertFalse(
			SchemaReference::local( new SchemaName( 'Person' ) )
				->equals( new SchemaReference( 'otherwiki', new SchemaName( 'Person' ) ) )
		);
	}

	public function testForeignReferencesDifferingByNameAreNotEqual(): void {
		$this->assertFalse(
			( new SchemaReference( 'otherwiki', new SchemaName( 'Person' ) ) )
				->equals( new SchemaReference( 'otherwiki', new SchemaName( 'Company' ) ) )
		);
	}

}
