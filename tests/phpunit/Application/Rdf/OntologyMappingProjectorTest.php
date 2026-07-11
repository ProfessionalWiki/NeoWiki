<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector
 */
class OntologyMappingProjectorTest extends TestCase {

	private const string PERSON_ID = 's1janeaaaaaaaa2';
	private const string CITY_ID = 's1cityaaaaaaaa3';

	private const string EDM = 'http://www.europeana.eu/schemas/edm/';
	private const string DC = 'http://purl.org/dc/elements/1.1/';

	private RdfNamespaces $ns;
	private LegacyLoggerSpy $logger;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
		$this->logger = new LegacyLoggerSpy();
	}

	/**
	 * @param Mapping[] $mappings
	 */
	private function newProjector( array $mappings ): OntologyMappingProjector {
		return new OntologyMappingProjector(
			'edm',
			$mappings,
			$this->ns,
			RdfValueMapperRegistry::withCoreMappers(),
			$this->logger,
		);
	}

	public function testProjectsMappedVocabularyOnlyWithNativeSubjectIris(): void {
		$quads = $this->newProjector( [ $this->personMapping(), $this->cityMapping() ] )
			->projectPage( $this->examplePage() );

		$output = ( new HardfRdfSerializer( $this->serializerPrefixes() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->expectedTriG() ),
			ParsedRdf::canonicalQuads( $output )
		);
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	/**
	 * The example exercises: rdfs:label always emitted; a language tag on a plain string (Name); a
	 * datatype override on a number (BirthYear); an unmapped property present on the Subject but absent
	 * in the output (Height); a relation projected as a direct triple to the target Subject's native
	 * IRI (BornIn → City); and native (neo-subj:) Subject IRIs throughout.
	 */
	private function expectedTriG(): string {
		return <<<TRIG
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix neo-page: <https://wiki.example/page/> .
			@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			@prefix edm: <http://www.europeana.eu/schemas/edm/> .
			@prefix dc: <http://purl.org/dc/elements/1.1/> .

			neo-page:42 {
				neo-subj:s1janeaaaaaaaa2 a edm:ProvidedCHO ;
					rdfs:label "Jane" ;
					dc:title "Jane"@en ;
					edm:isShownAt "https://jane.example"^^xsd:anyURI ;
					dc:date "1990"^^edm:year ;
					dc:spatial neo-subj:s1cityaaaaaaaa3 .

				neo-subj:s1cityaaaaaaaa3 a edm:Place ;
					rdfs:label "Berlin" ;
					dc:title "Berlin" .
			}
			TRIG;
	}

	private function examplePage(): Page {
		$person = TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Name', new StringValue( 'Jane' ), 'text' ),
				TestStatement::build( 'Homepage', new StringValue( 'https://jane.example' ), 'url' ),
				TestStatement::build( 'Height', new NumberValue( 170 ), 'number' ),
				TestStatement::build( 'BirthYear', new NumberValue( 1990 ), 'number' ),
				TestStatement::buildRelation( 'BornIn', [ TestRelation::build( targetId: self::CITY_ID ) ] ),
			] )
		);

		$city = TestSubject::build(
			id: self::CITY_ID,
			label: 'Berlin',
			schemaName: new SchemaName( 'City' ),
			statements: new StatementList( [
				TestStatement::build( 'Name', new StringValue( 'Berlin' ), 'text' ),
			] )
		);

		return TestPage::build( id: 42, mainSubject: $person, childSubjects: new SubjectMap( $city ) );
	}

	private function personMapping(): Mapping {
		return new Mapping(
			name: new MappingName( 'Person to EDM' ),
			schema: new SchemaName( 'Person' ),
			target: 'edm',
			prefixes: [ 'edm' => self::EDM, 'dc' => self::DC ],
			subjectClass: 'edm:ProvidedCHO',
			properties: new PropertyMappings( [
				'Name' => new PropertyMapping( 'dc:title', 'en', null ),
				'Homepage' => new PropertyMapping( 'edm:isShownAt' ),
				'BirthYear' => new PropertyMapping( 'dc:date', null, 'edm:year' ),
				'BornIn' => new PropertyMapping( 'dc:spatial' ),
			] )
		);
	}

	private function cityMapping(): Mapping {
		return new Mapping(
			name: new MappingName( 'City to EDM' ),
			schema: new SchemaName( 'City' ),
			target: 'edm',
			prefixes: [ 'edm' => self::EDM, 'dc' => self::DC ],
			subjectClass: 'edm:Place',
			properties: new PropertyMappings( [
				'Name' => new PropertyMapping( 'dc:title' ),
			] )
		);
	}

	public function testSubjectWhoseSchemaHasNoMappingForTheTargetIsAbsent(): void {
		$ghost = TestSubject::build(
			id: self::CITY_ID,
			label: 'Unmapped',
			schemaName: new SchemaName( 'Ghost' ),
			statements: new StatementList( [ TestStatement::build( 'Name', new StringValue( 'Unmapped' ), 'text' ) ] )
		);
		$page = TestPage::build(
			id: 42,
			mainSubject: $this->examplePersonWithoutRelations(),
			childSubjects: new SubjectMap( $ghost )
		);

		$quads = $this->newProjector( [ $this->personMapping() ] )->projectPage( $page );

		$ghostIri = $this->ns->subject( new SubjectId( self::CITY_ID ) );
		$this->assertFalse(
			$this->containsSubjectWithPredicate( $quads, $ghostIri, $this->ns->rdfType() ),
			'A Subject with no Mapping for the target must not be typed.'
		);
		$this->assertFalse(
			$this->containsSubjectWithPredicate( $quads, $ghostIri, $this->ns->rdfsLabel() ),
			'A Subject with no Mapping for the target must not be labelled.'
		);
		$this->assertTrue(
			$this->containsSubjectWithPredicate( $quads, $this->ns->subject( new SubjectId( self::PERSON_ID ) ), $this->ns->rdfType() ),
			'The mapped Subject still projects.'
		);
	}

	public function testRelationTargetWithoutAMappingIsReferencedButUntyped(): void {
		$page = TestPage::build(
			id: 42,
			mainSubject: TestSubject::build(
				id: self::PERSON_ID,
				label: 'Jane',
				schemaName: new SchemaName( 'Person' ),
				statements: new StatementList( [
					TestStatement::buildRelation( 'BornIn', [ TestRelation::build( targetId: self::CITY_ID ) ] ),
				] )
			),
		);

		// Only Person is mapped; the City target Subject is not even on the page.
		$quads = $this->newProjector( [ $this->personMapping() ] )->projectPage( $page );

		$cityIri = $this->ns->subject( new SubjectId( self::CITY_ID ) );
		$this->assertTrue(
			$quads->contains( new Quad(
				$this->ns->subject( new SubjectId( self::PERSON_ID ) ),
				new Iri( self::DC . 'spatial' ),
				$cityIri,
				$this->ns->page( new PageId( 42 ) )
			) ),
			'The relation is a direct triple to the target Subject native IRI.'
		);
		$this->assertFalse(
			$this->containsSubjectWithPredicate( $quads, $cityIri, $this->ns->rdfType() ),
			'The unmapped relation target stays untyped.'
		);
	}

	public function testDuplicateMappingsForASchemaAreTieBrokenAlphabeticallyAndLogged(): void {
		// Two Mappings claim (Person, edm) with different classes. Passed in reverse alphabetical order,
		// the projector must still pick "A ..." — proving it sorts rather than takes the first given.
		$loser = $this->personMappingNamed( 'B mapping', 'edm:ProvidedCHO' );
		$winner = $this->personMappingNamed( 'A mapping', 'edm:Place' );

		$page = TestPage::build( id: 42, mainSubject: $this->examplePersonWithoutRelations() );

		$quads = $this->newProjector( [ $loser, $winner ] )->projectPage( $page );

		$this->assertTrue(
			$quads->contains( new Quad(
				$this->ns->subject( new SubjectId( self::PERSON_ID ) ),
				$this->ns->rdfType(),
				new Iri( self::EDM . 'Place' ),
				$this->ns->page( new PageId( 42 ) )
			) ),
			'The alphabetically first Mapping wins the tie-break.'
		);
		$this->assertCount( 1, $this->logger->getLogCalls()->getMessages() );
	}

	private function personMappingNamed( string $name, string $subjectClass ): Mapping {
		return new Mapping(
			name: new MappingName( $name ),
			schema: new SchemaName( 'Person' ),
			target: 'edm',
			prefixes: [ 'edm' => self::EDM ],
			subjectClass: $subjectClass,
			properties: new PropertyMappings( [] )
		);
	}

	private function examplePersonWithoutRelations(): \ProfessionalWiki\NeoWiki\Domain\Subject\Subject {
		return TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [ TestStatement::build( 'Name', new StringValue( 'Jane' ), 'text' ) ] )
		);
	}

	private function containsSubjectWithPredicate( QuadList $quads, Iri $subject, Iri $predicate ): bool {
		foreach ( $quads->asArray() as $quad ) {
			if ( $quad->subject->equals( $subject ) && $quad->predicate->equals( $predicate ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, string>
	 */
	private function serializerPrefixes(): array {
		return array_merge( $this->ns->prefixMap(), [ 'edm' => self::EDM, 'dc' => self::DC ] );
	}

}
