<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Infrastructure\TitleBasedSchemaReferenceNormalizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\TitleBasedSchemaReferenceNormalizer
 */
class TitleBasedSchemaReferenceNormalizerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider nameProvider
	 */
	public function testLocalNameIsNormalizedToItsPageName( string $written, string $expected ): void {
		$this->assertSame(
			$expected,
			$this->normalizeLocal( $written )
		);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function nameProvider(): iterable {
		yield 'a name already spelled as its page is left alone' => [ 'Person', 'Person' ];
		yield 'the first character is capitalized' => [ 'person', 'Person' ];
		yield 'underscores become spaces' => [ 'Person_name', 'Person name' ];
		yield 'runs of whitespace collapse' => [ 'Person__name', 'Person name' ];
		yield 'surrounding underscores are dropped' => [ '_Person_', 'Person' ];
		yield 'surrounding whitespace is dropped' => [ '  Person  ', 'Person' ];
		yield 'both rules apply at once' => [ '_validation_demo_', 'Validation demo' ];

		// A Schema name may hold a colon of its own, so a prefix that names no namespace of this wiki
		// is part of the name rather than a namespace, and is capitalized like any other first letter.
		yield 'a prefix naming no namespace stays in the name' => [ 'iso:9001', 'Iso:9001' ];

		// The namespace Schemas live in is the one prefix that still names the Schema written down.
		yield 'the Schema namespace spelled out is not part of the name' => [ 'Schema:Person', 'Person' ];

		// Only the first character is touched, so these stay three different Schemas.
		yield 'a capital after the first character survives' => [ 'pErson', 'PErson' ];
		yield 'and is not moved to the front' => [ 'peRson', 'PeRson' ];
	}

	/**
	 * A Source names its own Schemas, so this wiki's page-naming rules say nothing about them.
	 */
	public function testSchemaOfAnotherSourceIsLeftAlone(): void {
		$reference = SchemaReference::sourced( 'othersource', new SchemaName( 'person' ) );

		$this->assertEquals(
			$reference,
			$this->newNormalizer()->normalize( $reference )
		);
	}

	/**
	 * @dataProvider unnormalizableNameProvider
	 */
	public function testNameWithNoNormalFormIsLeftAlone( string $written ): void {
		$this->assertSame(
			$written,
			$this->normalizeLocal( $written )
		);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function unnormalizableNameProvider(): iterable {
		yield 'no title can be made of it' => [ 'Person|Company' ];
		yield 'its normal form is a reserved Schema name' => [ 'page_' ];

		// MediaWiki reads a prefix as a namespace and hands back the bare remainder, which names a
		// different Schema than the one written down. Renaming to it would be a silent substitution.
		yield 'it names another namespace' => [ 'Help:Person' ];
		yield 'it names another namespace that exists' => [ 'Category:Person' ];
		yield 'it names a talk page' => [ 'Talk:Person' ];
		yield 'it carries a fragment' => [ 'Person#Details' ];
		yield 'it is a fragment alone' => [ '#Details' ];
	}

	private function normalizeLocal( string $written ): string {
		return $this->newNormalizer()
			->normalize( SchemaReference::local( new SchemaName( $written ) ) )
			->name
			->getText();
	}

	private function newNormalizer(): TitleBasedSchemaReferenceNormalizer {
		return new TitleBasedSchemaReferenceNormalizer(
			$this->getServiceContainer()->getTitleFactory()
		);
	}

}
