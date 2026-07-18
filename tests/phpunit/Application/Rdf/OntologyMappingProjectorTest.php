<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;
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
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
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
	 * Builds a page-level Mapping named "edm" (so its named graph is /graph/edm/page/{id}) from the given
	 * per-Schema entries and page-level prefixes.
	 *
	 * @param SchemaMapping[] $schemaMappings
	 * @param array<string, string> $prefixes
	 */
	private function newProjector( array $schemaMappings, array $prefixes = [ 'edm' => self::EDM, 'dc' => self::DC ] ): OntologyMappingProjector {
		$schemas = [];
		foreach ( $schemaMappings as $schemaMapping ) {
			$schemas[$schemaMapping->schema->getText()] = $schemaMapping;
		}

		return new OntologyMappingProjector(
			new Mapping( new MappingName( 'edm' ), $prefixes, $schemas ),
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
			@prefix neo-graph: <https://wiki.example/graph/edm/page/> .
			@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			@prefix edm: <http://www.europeana.eu/schemas/edm/> .
			@prefix dc: <http://purl.org/dc/elements/1.1/> .

			neo-graph:42 {
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

	private function personMapping(): SchemaMapping {
		return new SchemaMapping(
			schema: new SchemaName( 'Person' ),
			subjectClass: 'edm:ProvidedCHO',
			properties: new PropertyMappings( [
				'Name' => new PropertyMapping( 'dc:title', 'en', null ),
				'Homepage' => new PropertyMapping( 'edm:isShownAt' ),
				'BirthYear' => new PropertyMapping( 'dc:date', null, 'edm:year' ),
				'BornIn' => new PropertyMapping( 'dc:spatial' ),
			] )
		);
	}

	private function cityMapping(): SchemaMapping {
		return new SchemaMapping(
			schema: new SchemaName( 'City' ),
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
				$this->ns->graph( 'edm', new PageId( 42 ) )
			) ),
			'The relation is a direct triple to the target Subject native IRI.'
		);
		$this->assertFalse(
			$this->containsSubjectWithPredicate( $quads, $cityIri, $this->ns->rdfType() ),
			'The unmapped relation target stays untyped.'
		);
	}

	public function testInvalidStoredLanguageTagIsDroppedAndTheLiteralStaysPlain(): void {
		// A Mapping constructed directly, simulating one stored before validation (importDump / a
		// pre-validation page): its "en_US" tag is not BCP-47-shaped.
		$quads = $this->newProjector( [ $this->personMappingWithNameLang( 'en_US' ) ] )
			->projectPage( TestPage::build( id: 42, mainSubject: $this->examplePersonWithoutRelations() ) );

		$output = ( new HardfRdfSerializer( $this->serializerPrefixes() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->expectedPlainNameTriG() ),
			ParsedRdf::canonicalQuads( $output ),
			'The value projects as a plain string literal when the stored language tag is invalid.'
		);
		$this->assertCount( 1, $this->logger->getLogCalls()->getMessages() );
	}

	public function testMaliciousLanguageTagCannotInjectADatatypeIntoTheDocument(): void {
		$quads = $this->newProjector( [ $this->personMappingWithNameLang( 'en"^^xsd:evil' ) ] )
			->projectPage( TestPage::build( id: 42, mainSubject: $this->examplePersonWithoutRelations() ) );

		$output = ( new HardfRdfSerializer( $this->serializerPrefixes() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertStringNotContainsString( 'xsd:evil', $output, 'The attacker-chosen datatype must not reach the document.' );
		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->expectedPlainNameTriG() ),
			ParsedRdf::canonicalQuads( $output ),
			'The document still parses to only the safe, plain-literal triples.'
		);
		$this->assertCount( 1, $this->logger->getLogCalls()->getMessages() );
	}

	private function personMappingWithNameLang( string $lang ): SchemaMapping {
		return new SchemaMapping(
			schema: new SchemaName( 'Person' ),
			subjectClass: 'http://example.org/CHO',
			properties: new PropertyMappings( [
				'Name' => new PropertyMapping( 'dc:title', $lang, null ),
			] )
		);
	}

	private function expectedPlainNameTriG(): string {
		return <<<TRIG
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix dc: <http://purl.org/dc/elements/1.1/> .

			<https://wiki.example/graph/edm/page/42> {
				neo-subj:s1janeaaaaaaaa2 a <http://example.org/CHO> ;
					rdfs:label "Jane" ;
					dc:title "Jane" .
			}
			TRIG;
	}

	/**
	 * The projection-time re-expansion defence: even when save-time validation was bypassed, a class,
	 * predicate, datatype, or prefix that does not re-expand safely is dropped, so no injection term
	 * reaches the serialized document and it still parses.
	 */
	public function testUnsafeTermsAreDroppedAtProjectionTimeWhenSaveValidationWasBypassed(): void {
		$prefixes = [
			'dc' => self::DC,
			// A prefix whose namespace breaks out of the prefix table; a CURIE using it must be dropped.
			'evil' => 'http://evil.example/"> .# ',
		];

		$quads = $this->newProjector( [ $this->adversarialMapping() ], $prefixes )
			->projectPage( TestPage::build( id: 42, mainSubject: $this->adversarialSubject() ) );

		$output = ( new HardfRdfSerializer( $this->serializerPrefixes() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertStringNotContainsString( 'evil', $output, 'No injection term survives to the document.' );
		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->expectedSafeAdversarialTriG() ),
			ParsedRdf::canonicalQuads( $output ),
			'Only the safe triples remain: the always-emitted label, the safely mapped property, and the '
				. 'value whose unsafe datatype override was dropped (kept with its native datatype). The '
				. 'injection class, predicate, and unsafe-prefix CURIE are gone, and there is no rdf:type.'
		);
	}

	private function adversarialMapping(): SchemaMapping {
		return new SchemaMapping(
			schema: new SchemaName( 'Person' ),
			// A subject class that would break out of its IRI: it must not produce an rdf:type triple.
			subjectClass: 'http://x/> <http://evil.example/s> <http://evil.example/p> <http://evil.example/o',
			properties: new PropertyMappings( [
				'Name' => new PropertyMapping( 'dc:title' ),
				// A safe predicate with an injection datatype override: the value keeps its native datatype.
				'BirthYear' => new PropertyMapping( 'dc:date', null, 'http://x/> <http://evil.example/dt' ),
				// An injection predicate: the whole statement is dropped.
				'Bio' => new PropertyMapping( 'http://x/> <http://evil.example/p2> <http://evil.example/o2' ),
				// A CURIE against the unsafe prefix: dropped.
				'Homepage' => new PropertyMapping( 'evil:foo' ),
			] )
		);
	}

	private function adversarialSubject(): Subject {
		return TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Name', new StringValue( 'Jane' ), 'text' ),
				TestStatement::build( 'BirthYear', new NumberValue( 1990 ), 'number' ),
				TestStatement::build( 'Bio', new StringValue( 'Hi' ), 'text' ),
				TestStatement::build( 'Homepage', new StringValue( 'http://jane.example' ), 'text' ),
			] )
		);
	}

	private function expectedSafeAdversarialTriG(): string {
		return <<<TRIG
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			@prefix dc: <http://purl.org/dc/elements/1.1/> .

			<https://wiki.example/graph/edm/page/42> {
				neo-subj:s1janeaaaaaaaa2 rdfs:label "Jane" ;
					dc:title "Jane" ;
					dc:date "1990"^^xsd:integer .
			}
			TRIG;
	}

	public function testLanguageTagIsIgnoredForATypedLiteral(): void {
		$page = TestPage::build( id: 42, mainSubject: $this->personWithBirthYear() );

		$quads = $this->newProjector( [ $this->personBirthYearMappingWithLang( 'en' ) ] )->projectPage( $page );

		$this->assertTrue(
			$quads->contains( new Quad(
				$this->ns->subject( new SubjectId( self::PERSON_ID ) ),
				new Iri( self::DC . 'date' ),
				RdfLiteralFactory::typed( '1990', 'integer' ),
				$this->ns->graph( 'edm', new PageId( 42 ) )
			) ),
			'A typed literal keeps its datatype; a language tag does not apply to it.'
		);
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testDatatypeAndLanguageOnARelationPropertyAreIgnored(): void {
		// Constructed directly, bypassing the save-time lang/datatype mutual-exclusion check, to prove
		// the projector ignores literal overrides on a relation (its object is an IRI, not a literal).
		$mapping = new SchemaMapping(
			schema: new SchemaName( 'Person' ),
			subjectClass: 'http://example.org/CHO',
			properties: new PropertyMappings( [
				'BornIn' => new PropertyMapping( 'dc:spatial', 'en', 'edm:year' ),
			] )
		);
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

		$quads = $this->newProjector( [ $mapping ] )->projectPage( $page );

		$this->assertTrue(
			$quads->contains( new Quad(
				$this->ns->subject( new SubjectId( self::PERSON_ID ) ),
				new Iri( self::DC . 'spatial' ),
				$this->ns->subject( new SubjectId( self::CITY_ID ) ),
				$this->ns->graph( 'edm', new PageId( 42 ) )
			) ),
			'The relation is a plain IRI-to-IRI triple; datatype and language overrides do not apply.'
		);
	}

	private function personWithBirthYear(): Subject {
		return TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [ TestStatement::build( 'BirthYear', new NumberValue( 1990 ), 'number' ) ] )
		);
	}

	private function personBirthYearMappingWithLang( string $lang ): SchemaMapping {
		return new SchemaMapping(
			schema: new SchemaName( 'Person' ),
			subjectClass: 'http://example.org/CHO',
			properties: new PropertyMappings( [
				'BirthYear' => new PropertyMapping( 'dc:date', $lang, null ),
			] )
		);
	}

	public function testProjectsSubjectsOfDifferentSchemasOnTheSamePage(): void {
		// One page, two Subjects of different Schemas, both with an entry on the Mapping page: both project
		// with their own class, so a multi-Schema Mapping page covers every Schema it lists.
		$quads = $this->newProjector( [ $this->personMapping(), $this->cityMapping() ] )
			->projectPage( $this->examplePage() );

		$graph = $this->ns->graph( 'edm', new PageId( 42 ) );
		$this->assertTrue(
			$quads->contains( new Quad(
				$this->ns->subject( new SubjectId( self::PERSON_ID ) ),
				$this->ns->rdfType(),
				new Iri( self::EDM . 'ProvidedCHO' ),
				$graph
			) ),
			'The Person Subject projects with the Person entry class.'
		);
		$this->assertTrue(
			$quads->contains( new Quad(
				$this->ns->subject( new SubjectId( self::CITY_ID ) ),
				$this->ns->rdfType(),
				new Iri( self::EDM . 'Place' ),
				$graph
			) ),
			'The City Subject projects with the City entry class.'
		);
	}

	private function examplePersonWithoutRelations(): Subject {
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
