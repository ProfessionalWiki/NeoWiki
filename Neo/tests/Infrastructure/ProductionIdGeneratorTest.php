<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionIdGenerator;
use Random\Engine;
use Random\Randomizer;
use WMDE\Clock\StubClock;

#[CoversClass( ProductionIdGenerator::class )]
class ProductionIdGeneratorTest extends TestCase {

	public function testGeneratesFixedLengthIds(): void {
		$this->assertSame( 14, strlen( $this->newGenerator()->generate() ) );
		$this->assertSame( 14, strlen( $this->newGenerator()->generate() ) );
		$this->assertSame( 14, strlen( $this->newGenerator()->generate() ) );
	}

	private function newGenerator(): ProductionIdGenerator {
		return new ProductionIdGenerator();
	}

	public function testGeneratesUniqueIds(): void {
		$generator = $this->newGenerator();

		$this->assertCount(
			1000,
			array_unique(
				array_map(
					fn() => $generator->generate(),
					range( 1, 1000 )
				)
			)
		);
	}

	public function testGenerateUsesOnlyValidCharacters(): void {
		$this->assertMatchesRegularExpression( '/^[0-9A-Za-z]+$/', $this->newGenerator()->generate() );
	}

	public function testTimestampEncodingIsConsistent(): void {
		$generator = new ProductionIdGenerator( $this->newStubRandomizer() );

		$id1 = $generator->generate();
		usleep( 1000 ); // Wait 1ms
		$id2 = $generator->generate();

		$this->assertNotSame( substr( $id1, 0, 12 ), substr( $id2, 0, 12 ) );
	}

	public function testRandomPartIsConsistent(): void {
		$generator = new ProductionIdGenerator( $this->newStubRandomizer() );

		$id1 = $generator->generate();
		$id2 = $generator->generate();

		$this->assertSame( substr( $id1, -5 ), substr( $id2, -5 ) );
	}

	private function newStubRandomizer(): Randomizer {
		return new Randomizer(
			new class implements Engine {
				public function generate(): string {
					return "\0\0\0\0";
				}
			}
		);
	}

	public function testTimestampsAreStable(): void {
		$generator = new ProductionIdGenerator( clock: new StubClock( new DateTimeImmutable( '1970-01-01' ) ) );

		$this->assertSame(
			'111111111',
			substr( $generator->generate(), 0, 9 ),
		);

		$generator = new ProductionIdGenerator( clock: new StubClock( new DateTimeImmutable( '2200-01-01' ) ) );

		$this->assertSame(
			'ygDTqkTJ7',
			substr( $generator->generate(), 0, 9 ),
		);
	}

}
