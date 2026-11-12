<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\SchemaRdfsDescriber;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\MonolingualTextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\SchemaRdfsDescriber
 */
class SchemaRdfsDescriberTest extends TestCase {

	private const string PREFIXES = <<<TURTLE
		@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
		@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
		@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
		@prefix neo-schema: <https://wiki.example/schema/> .
		@prefix Person: <https://wiki.example/prop/Person/> .

		TURTLE;

	public function testSchemaBecomesALabelledClassWithItsDescription(): void {
		$this->assertSame(
			ParsedRdf::canonicalQuads( self::PREFIXES . <<<TURTLE
				neo-schema:Person a rdfs:Class ;
					rdfs:label "Person" ;
					rdfs:comment "A human being" .
				TURTLE ),
			$this->triplesOf( $this->personSchema( 'A human being', [] ) )
		);
	}

	public function testClassIsLabelledWithTheSchemaNameAsWritten(): void {
		$this->assertContains(
			"https://wiki.example/schema/Annual_attendance\t" . RdfNamespaces::RDFS . "label\t\"Annual attendance\"\t",
			$this->triplesOf( TestSchema::build( 'Annual attendance', '' ) )
		);
	}

	public function testSchemaAndPropertyWithoutADescriptionHaveNoComment(): void {
		$this->assertSame(
			ParsedRdf::canonicalQuads( self::PREFIXES . <<<TURTLE
				neo-schema:Person a rdfs:Class ; rdfs:label "Person" .
				Person:Name a rdf:Property ; rdfs:label "Name" ; rdfs:domain neo-schema:Person ; rdfs:range xsd:string .
				TURTLE ),
			$this->triplesOf( $this->personSchema( '', [ 'Name' => TestProperty::buildText() ] ) )
		);
	}

	public function testPropertyDefinitionBecomesAPropertyWithItsSchemaAsDomain(): void {
		$this->assertSame(
			ParsedRdf::canonicalQuads( self::PREFIXES . <<<TURTLE
				neo-schema:Person a rdfs:Class ;
					rdfs:label "Person" .

				Person:Birth_date a rdf:Property ;
					rdfs:label "Birth date" ;
					rdfs:comment "Date of birth" ;
					rdfs:domain neo-schema:Person ;
					rdfs:range xsd:date .
				TURTLE ),
			$this->triplesOf( $this->personSchema( '', [ 'Birth date' => TestProperty::buildDate( description: 'Date of birth' ) ] ) )
		);
	}

	public function testEverySchemaGivenIsProjected(): void {
		$this->assertSame(
			ParsedRdf::canonicalQuads( self::PREFIXES . <<<TURTLE
				neo-schema:Person a rdfs:Class ; rdfs:label "Person" .
				neo-schema:City a rdfs:Class ; rdfs:label "City" .
				TURTLE ),
			$this->triplesOf( $this->personSchema( '', [] ), TestSchema::build( 'City', '' ) )
		);
	}

	public function testPropertyLabelsTakeTheirLanguageInPlaceOfTheName(): void {
		$this->assertSame(
			[ '"Date of birth"@en', '"Geburtsdatum"@de' ],
			$this->objectsOf( $this->textPropertyWithLabels( [ 'en' => 'Date of birth', 'de' => 'Geburtsdatum' ] ), 'label' )
		);
	}

	public function testPropertyLabelUnderAnInvalidLanguageTagIsLeftOut(): void {
		$this->assertSame(
			[ '"Geburtsdatum"@de' ],
			$this->objectsOf( $this->textPropertyWithLabels( [ 'not a tag' => 'Date of birth', 'de' => 'Geburtsdatum' ] ), 'label' )
		);
	}

	public function testPropertyWhoseLabelsAllHaveInvalidLanguageTagsIsLabelledWithItsName(): void {
		$this->assertSame(
			[ '"Birth date"' ],
			$this->objectsOf( $this->textPropertyWithLabels( [ 'not a tag' => 'Date of birth' ] ), 'label' )
		);
	}

	/**
	 * @dataProvider literalPropertyProvider
	 */
	public function testPropertyOfALiteralTypeHasTheDatatypeTheProjectionEmitsAsRange(
		PropertyDefinition $property,
		string $range
	): void {
		$this->assertSame( [ $range ], $this->objectsOf( $property, 'range' ) );
	}

	public static function literalPropertyProvider(): iterable {
		yield 'text' => [ TestProperty::buildText(), 'http://www.w3.org/2001/XMLSchema#string' ];
		yield 'select' => [ TestProperty::buildSelect(), 'http://www.w3.org/2001/XMLSchema#string' ];
		yield 'number' => [ TestProperty::buildNumber(), 'http://www.w3.org/2001/XMLSchema#decimal' ];
		yield 'boolean' => [ TestProperty::buildBoolean(), 'http://www.w3.org/2001/XMLSchema#boolean' ];
		yield 'date' => [ TestProperty::buildDate(), 'http://www.w3.org/2001/XMLSchema#date' ];
		yield 'dateTime' => [ TestProperty::buildDateTime(), 'http://www.w3.org/2001/XMLSchema#dateTime' ];
		yield 'monolingualText' => [
			new MonolingualTextProperty( self::core(), multiple: false, uniqueLanguages: false, minLength: null, maxLength: null ),
			'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString',
		];
	}

	public function testUrlPropertyHasNoRange(): void {
		$this->assertSame( [], $this->objectsOf( TestProperty::buildUrl(), 'range' ) );
	}

	public function testPropertyOfAnUnregisteredTypeHasNoRange(): void {
		$this->assertSame( [], $this->objectsOf( new UnregisteredTypeProperty( self::core(), 'colour', [] ), 'range' ) );
	}

	public function testRelationToOneSchemaHasThatSchemasClassAsRange(): void {
		$this->assertSame(
			[ 'https://wiki.example/schema/City' ],
			$this->objectsOf( TestProperty::buildRelation( targetSchemas: [ 'City' ] ), 'range' )
		);
	}

	public function testRelationToSeveralSchemasHasNoRange(): void {
		$this->assertSame( [], $this->objectsOf( TestProperty::buildRelation( targetSchemas: [ 'City', 'Place' ] ), 'range' ) );
	}

	public function testRelationToASchemaOfAnotherSourceHasNoRange(): void {
		$this->assertSame(
			[],
			$this->objectsOf(
				TestProperty::buildRelation( targetSchemas: [ SchemaReference::sourced( 'otherwiki', new SchemaName( 'City' ) ) ] ),
				'range'
			)
		);
	}

	/**
	 * @param array<string, string> $labels
	 */
	private static function core( array $labels = [] ): PropertyCore {
		return new PropertyCore( description: '', required: false, default: null, labels: $labels );
	}

	/**
	 * @param array<string, PropertyDefinition> $properties
	 */
	private function personSchema( string $description, array $properties ): Schema {
		return TestSchema::build( 'Person', $description, new PropertyDefinitions( $properties ) );
	}

	/**
	 * @param array<string, string> $labels
	 */
	private function textPropertyWithLabels( array $labels ): TextProperty {
		return new TextProperty( self::core( $labels ), multiple: false, uniqueItems: false, minLength: null, maxLength: null );
	}

	/**
	 * @return list<string>
	 */
	private function triplesOf( Schema ...$schemas ): array {
		return ParsedRdf::canonicalQuads(
			HardfRdfSerializer::withPrefixes( [], new NullLogger() )->serialize(
				( new SchemaRdfsDescriber( new RdfNamespaces( 'https://wiki.example' ) ) )->describe( ...$schemas ),
				RdfFormat::Turtle
			)
		);
	}

	/**
	 * @return list<string> The objects of the property's triples with the given RDFS predicate, in Turtle notation.
	 */
	private function objectsOf( PropertyDefinition $property, string $rdfsPredicate ): array {
		$subjectAndPredicate = "https://wiki.example/prop/Person/Birth_date\t" . RdfNamespaces::RDFS . $rdfsPredicate . "\t";
		$objects = [];

		foreach ( $this->triplesOf( $this->personSchema( '', [ 'Birth date' => $property ] ) ) as $triple ) {
			if ( str_starts_with( $triple, $subjectAndPredicate ) ) {
				$objects[] = explode( "\t", $triple )[2];
			}
		}

		return $objects;
	}

}
