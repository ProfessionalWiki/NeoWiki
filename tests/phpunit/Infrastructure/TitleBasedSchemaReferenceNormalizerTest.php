<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
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

		// A name is never split into a prefix and a remainder, so a prefix of any kind is part of the
		// name and its page is the one titled that way: Schema:Schema:Person, Schema:Help:Person.
		yield 'the Schema namespace spelled out stays in the name' => [ 'Schema:Person', 'Schema:Person' ];
		yield 'another namespace stays in the name' => [ 'Help:Person', 'Help:Person' ];
		yield 'so normalizing again renames nothing' => [ 'Schema:Schema:Person', 'Schema:Schema:Person' ];

		// No page title can hold a "#", so the name is the page the fragment points into, which is
		// what MediaWiki makes of such a link.
		yield 'a fragment names the page before it' => [ 'Person#Details', 'Person' ];

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
		yield 'a leading colon makes no page' => [ ':Person' ];
		yield 'it is a fragment alone' => [ '#Details' ];
	}

	/**
	 * Every Subject read asks after a Schema, a wiki names few of them, and parsing a title is neither
	 * free nor cached by MediaWiki outside NS_MAIN. A name already seen is not parsed again.
	 */
	public function testParsesANameOnlyOnce(): void {
		$titleFactory = $this->newCountingTitleFactory();
		$normalizer = new TitleBasedSchemaReferenceNormalizer( $titleFactory );

		$normalizer->normalize( SchemaReference::local( new SchemaName( 'Person' ) ) );
		$normalizer->normalize( SchemaReference::local( new SchemaName( 'Person' ) ) );
		$normalizer->normalize( SchemaReference::local( new SchemaName( 'Person' ) ) );

		$this->assertSame( 1, $titleFactory->calls );
	}

	/**
	 * What it remembers is per name, so a page of Subjects following different Schemas gets each of
	 * their names, not whichever was asked for first.
	 */
	public function testRemembersEachNameOnItsOwn(): void {
		$normalizer = new TitleBasedSchemaReferenceNormalizer(
			$this->getServiceContainer()->getTitleFactory()
		);

		$names = array_map(
			static fn ( string $written ): string => $normalizer
				->normalize( SchemaReference::local( new SchemaName( $written ) ) )
				->name
				->getText(),
			[ 'person', 'company', 'person' ]
		);

		$this->assertSame( [ 'Person', 'Company', 'Person' ], $names );
	}

	/**
	 * @return TitleFactory&object{calls: int}
	 */
	private function newCountingTitleFactory(): TitleFactory {
		return new class() extends TitleFactory {
			public int $calls = 0;

			public function makeTitleSafe( $ns, $title, $fragment = '', $interwiki = '' ): ?Title {
				$this->calls++;
				return parent::makeTitleSafe( $ns, $title, $fragment, $interwiki );
			}
		};
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
