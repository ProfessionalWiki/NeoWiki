<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference
 */
class SchemaReferenceTest extends TestCase {

	public function testALocalReferenceSerializesToItsBareName(): void {
		$reference = SchemaReference::local( new SchemaName( 'Company' ) );

		$this->assertSame( 'Company', $reference->toJson() );
		$this->assertTrue( $reference->isLocal() );
	}

	public function testASourcedReferenceSerializesToAnObject(): void {
		$reference = SchemaReference::sourced( 'otherwiki', new SchemaName( 'Company' ) );

		$this->assertSame( [ 'source' => 'otherwiki', 'name' => 'Company' ], $reference->toJson() );
		$this->assertFalse( $reference->isLocal() );
	}

	public function testReadsAStringAsALocalName(): void {
		$reference = $this->read( 'Company' );

		$this->assertNull( $reference->source );
		$this->assertSame( 'Company', $reference->name->getText() );
	}

	public function testReadsAnObjectAsSourced(): void {
		$reference = $this->read( [ 'source' => 'otherwiki', 'name' => 'Company' ] );

		$this->assertSame( 'otherwiki', $reference->source );
		$this->assertSame( 'Company', $reference->name->getText() );
	}

	/**
	 * A local Schema name is a page title, which may hold a colon. Nothing splits a string on it, so
	 * such a name means exactly what it did before Sources existed.
	 *
	 * @dataProvider colonBearingLocalNameProvider
	 */
	public function testAColonBearingStringIsOneLocalName( string $text ): void {
		$reference = $this->read( $text );

		$this->assertNull( $reference->source );
		$this->assertSame( $text, $reference->name->getText() );
		$this->assertSame( $text, $reference->toJson() );
	}

	public static function colonBearingLocalNameProvider(): iterable {
		yield 'a space after the colon reads as a subtitle' => [ 'Report: Annual' ];
		yield 'a prefix that is not a Source key' => [ 'Report 2020:Annual' ];
		yield 'a trailing colon' => [ 'Company:' ];
		yield 'a prefix that is a well-formed Source key' => [ 'ISO:9001' ];
		yield 'a name that is a registrable Source key followed by a name' => [ 'otherwiki:Company' ];
	}

	public function testAnObjectNamingThisWikiCanonicalizesToLocal(): void {
		$reference = $this->read( [ 'source' => TestSubjectIds::LOCAL_SOURCE_KEY, 'name' => 'Company' ] );

		$this->assertTrue( $reference->isLocal() );
		$this->assertSame( 'Company', $reference->toJson() );
		$this->assertTrue( $reference->equals( SchemaReference::local( new SchemaName( 'Company' ) ) ) );
	}

	public function testASourcedReferenceRoundTrips(): void {
		$reference = SchemaReference::sourced( 'otherwiki', new SchemaName( 'Company' ) );

		$this->assertTrue( $reference->equals( $this->read( $reference->toJson() ) ) );
	}

	public function testReferencesToDifferentSourcesAreNotEqual(): void {
		$this->assertFalse(
			SchemaReference::sourced( 'otherwiki', new SchemaName( 'Company' ) )
				->equals( SchemaReference::sourced( 'thirdwiki', new SchemaName( 'Company' ) ) )
		);
	}

	/**
	 * The one-way display form, for messages naming a Schema that is not this wiki's.
	 */
	public function testDisplaysASourcedReferenceQualified(): void {
		$this->assertSame(
			'otherwiki:Company',
			SchemaReference::sourced( 'otherwiki', new SchemaName( 'Company' ) )->getText()
		);
	}

	public function testRejectsAMalformedSourceKey(): void {
		$this->expectException( InvalidArgumentException::class );

		SchemaReference::sourced( 'not a key', new SchemaName( 'Company' ) );
	}

	public function testRejectsAnEmptyName(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->read( '' );
	}

	/**
	 * @dataProvider malformedJsonProvider
	 */
	public function testRejectsMalformedJson( mixed $value ): void {
		$this->expectException( InvalidArgumentException::class );

		$this->read( $value );
	}

	public static function malformedJsonProvider(): iterable {
		yield 'an object without a source' => [ [ 'name' => 'Company' ] ];
		yield 'an object without a name' => [ [ 'source' => 'otherwiki' ] ];
		yield 'an object with a non-string source' => [ [ 'source' => 42, 'name' => 'Company' ] ];
		yield 'an object with a non-string name' => [ [ 'source' => 'otherwiki', 'name' => 42 ] ];
		yield 'a number' => [ 42 ];
		yield 'null' => [ null ];
	}

	private function read( mixed $value ): SchemaReference {
		return SchemaReference::fromJson( $value, TestSubjectIds::LOCAL_SOURCE_KEY );
	}

}
