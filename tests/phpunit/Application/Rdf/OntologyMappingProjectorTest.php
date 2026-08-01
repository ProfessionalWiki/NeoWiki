<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeScope;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SubjectMapping;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
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
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer;
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
	private const string GHOST_ID = 's1ghostaaaaaaa9';
	private const string ARTWORK_ID = 's1artworkaaaaa4';
	private const string TWIN_ID = 's1twinaaaaaaaa5';
	private const string BIRTH_ID = 's1birthaaaaaaa6';

	private const string EDM = 'http://www.europeana.eu/schemas/edm/';
	private const string DC = 'http://purl.org/dc/elements/1.1/';
	private const string CRM = 'http://www.cidoc-crm.org/cidoc-crm/';
	private const string RDA_GR2 = 'http://rdvocab.info/ElementsGr2/';

	private RdfNamespaces $ns;
	private LegacyLoggerSpy $logger;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
		$this->logger = new LegacyLoggerSpy();
	}

	/**
	 * Builds a page-level Mapping named "edm" (so its named graph is /graph/edm/page/{id}) from the given
	 * per-Schema entries, keyed by Schema name as in production, plus the page-level prefixes.
	 *
	 * @param array<string, SchemaMapping> $schemas
	 * @param array<string, string> $prefixes
	 */
	private function newProjector( array $schemas, array $prefixes = [ 'edm' => self::EDM, 'dc' => self::DC ] ): OntologyMappingProjector {
		return new OntologyMappingProjector(
			new Mapping( new MappingName( 'edm' ), $prefixes, $schemas ),
			$this->ns,
			RdfValueMapperRegistry::withCoreMappers(),
			$this->logger,
		);
	}

	public function testProjectsMappedVocabularyOnlyWithNativeSubjectIris(): void {
		$quads = $this->newProjector( [ 'Person' => $this->personMapping(), 'City' => $this->cityMapping() ] )
			->projectPage( $this->examplePage() );

		$output = ( new HardfRdfSerializer( $this->serializerPrefixes() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->expectedTriG() ),
			ParsedRdf::canonicalQuads( $output )
		);
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	/**
	 * The example exercises: rdfs:label always emitted; a language tag on a plain string (Name); a url
	 * value projected as an IRI object (Homepage → edm:isShownAt); a datatype override on a number
	 * (BirthYear); an unmapped property present on the Subject but absent in the output (Height); a
	 * relation projected as a direct triple to the target Subject's native IRI (BornIn → City); and
	 * native (neo-subj:) Subject IRIs throughout.
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
					edm:isShownAt <https://jane.example> ;
					dc:date "1990"^^edm:year ;
					dc:spatial neo-subj:s1cityaaaaaaaa3 .

				neo-subj:s1cityaaaaaaaa3 a edm:Place ;
					rdfs:label "Berlin" ;
					dc:title "Berlin" .
			}
			TRIG;
	}

	private function examplePage(): Page {
		return TestPage::build( id: 42, mainSubject: $this->examplePerson(), childSubjects: new SubjectMap( $this->exampleCity() ) );
	}

	private function examplePerson(): Subject {
		return TestSubject::build(
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
	}

	private function exampleCity(): Subject {
		return TestSubject::build(
			id: self::CITY_ID,
			label: 'Berlin',
			schemaName: new SchemaName( 'City' ),
			statements: new StatementList( [
				TestStatement::build( 'Name', new StringValue( 'Berlin' ), 'text' ),
			] )
		);
	}

	/**
	 * The per-Subject export projects exactly the requested Subject's mapped block from a full-page
	 * projection, in the target's named graph, and nothing else. The target Person sits between two
	 * siblings; its BornIn relation points at the City sibling, whose IRI appears as the dc:spatial
	 * object but whose own mapped block (edm:Place, …) must not — so a "project every Subject"
	 * regression fails even though the City has an entry of its own.
	 */
	public function testProjectSubjectEmitsOnlyTheTargetSubjectsMappedBlockInTheTargetGraph(): void {
		$quads = $this->newProjector( [ 'Person' => $this->personMapping(), 'City' => $this->cityMapping() ] )
			->projectSubject( $this->pagePersonBetweenSiblings(), new SubjectId( self::PERSON_ID ) );

		$output = ( new HardfRdfSerializer( $this->serializerPrefixes() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->personOnlyTriG() ),
			ParsedRdf::canonicalQuads( $output )
		);
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testProjectSubjectForASubjectWhoseSchemaHasNoEntryReturnsNothing(): void {
		// Only Person has an entry; the requested Subject uses the unmapped Ghost Schema.
		$quads = $this->newProjector( [ 'Person' => $this->personMapping() ] )
			->projectSubject( $this->pagePersonBetweenSiblings(), new SubjectId( self::GHOST_ID ) );

		$this->assertTrue( $quads->isEmpty() );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testProjectSubjectForASubjectNotOnThePageReturnsNothing(): void {
		$quads = $this->newProjector( [ 'Person' => $this->personMapping() ] )
			->projectSubject( TestPage::build( id: 42, mainSubject: $this->examplePerson() ), new SubjectId( self::CITY_ID ) );

		$this->assertTrue( $quads->isEmpty() );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	private function pagePersonBetweenSiblings(): Page {
		return TestPage::build(
			id: 42,
			mainSubject: $this->exampleCity(),
			childSubjects: new SubjectMap( $this->examplePerson(), $this->ghostSubject() )
		);
	}

	private function ghostSubject(): Subject {
		return TestSubject::build(
			id: self::GHOST_ID,
			label: 'Unmapped',
			schemaName: new SchemaName( 'Ghost' ),
			statements: new StatementList( [ TestStatement::build( 'Name', new StringValue( 'Unmapped' ), 'text' ) ] )
		);
	}

	private function personOnlyTriG(): string {
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
					edm:isShownAt <https://jane.example> ;
					dc:date "1990"^^edm:year ;
					dc:spatial neo-subj:s1cityaaaaaaaa3 .
			}
			TRIG;
	}

	private function personMapping(): SchemaMapping {
		return new SchemaMapping(
			subject: new SubjectMapping( 'edm:ProvidedCHO' ),
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
			subject: new SubjectMapping( 'edm:Place' ),
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

		$quads = $this->newProjector( [ 'Person' => $this->personMapping() ] )->projectPage( $page );

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
		$quads = $this->newProjector( [ 'Person' => $this->personMapping() ] )->projectPage( $page );

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
		$quads = $this->newProjector( [ 'Person' => $this->personMappingWithNameLang( 'en_US' ) ] )
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
		$quads = $this->newProjector( [ 'Person' => $this->personMappingWithNameLang( 'en"^^xsd:evil' ) ] )
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
			subject: new SubjectMapping( 'http://example.org/CHO' ),
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

		$quads = $this->newProjector( [ 'Person' => $this->adversarialMapping() ], $prefixes )
			->projectPage( TestPage::build( id: 42, mainSubject: $this->adversarialSubject() ) );

		$output = ( new HardfRdfSerializer( $this->serializerPrefixes() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertStringNotContainsString( 'evil', $output, 'No injection term survives to the document.' );
		$this->assertStringNotContainsString(
			'/a> <b>',
			$output,
			'A node key that is IRI syntax is percent-encoded into the node IRI, not obeyed as syntax.'
		);
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
			subject: new SubjectMapping(
				// A subject class that would break out of its IRI: it must not produce an rdf:type triple.
				class: 'http://x/> <http://evil.example/s> <http://evil.example/p> <http://evil.example/o',
				// A CURIE against the unsafe prefix: the extra label triple is dropped, rdfs:label stays.
				labelPredicate: 'evil:name'
			),
			properties: new PropertyMappings( [
				'Name' => new PropertyMapping( 'dc:title' ),
				// A safe predicate with an injection datatype override: the value keeps its native datatype.
				'BirthYear' => new PropertyMapping( 'dc:date', null, 'http://x/> <http://evil.example/dt' ),
				// An injection predicate: the whole statement is dropped.
				'Bio' => new PropertyMapping( 'http://x/> <http://evil.example/p2> <http://evil.example/o2' ),
				// A CURIE against the unsafe prefix: dropped.
				'Homepage' => new PropertyMapping( 'evil:foo' ),
				// Attached to a node whose key is IRI syntax: the key is encoded, not obeyed.
				'Alias' => new PropertyMapping( predicate: 'dc:alternative', node: 'a> <b> <c' ),
			] ),
			nodes: [
				'a> <b> <c' => new NodeMapping( class: 'http://example.org/Appellation', linkPredicate: 'dc:relation' ),
			],
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
				TestStatement::build( 'Alias', new StringValue( 'Janey' ), 'text' ),
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
					dc:date "1990"^^xsd:integer ;
					dc:relation <https://wiki.example/node/s1janeaaaaaaaa2/a%3E_%3Cb%3E_%3Cc> .

				<https://wiki.example/node/s1janeaaaaaaaa2/a%3E_%3Cb%3E_%3Cc> a <http://example.org/Appellation> ;
					dc:alternative "Janey" .
			}
			TRIG;
	}

	public function testLanguageTagIsIgnoredForATypedLiteral(): void {
		$page = TestPage::build( id: 42, mainSubject: $this->personWithBirthYear() );

		$quads = $this->newProjector( [ 'Person' => $this->personBirthYearMappingWithLang( 'en' ) ] )->projectPage( $page );

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

	public function testExplicitDatatypeOverrideOnAUrlPropertyEmitsALiteralNotAnIri(): void {
		// A url value projects as an IRI object by default, but an explicit `datatype` on the property
		// mapping is deliberate configuration and wins: the value is emitted as a literal with that datatype.
		$mapping = new SchemaMapping(
			subject: new SubjectMapping( 'edm:ProvidedCHO' ),
			properties: new PropertyMappings( [
				'Homepage' => new PropertyMapping( 'edm:isShownAt', null, 'http://www.w3.org/2001/XMLSchema#anyURI' ),
			] )
		);
		$page = TestPage::build(
			id: 42,
			mainSubject: TestSubject::build(
				id: self::PERSON_ID,
				label: 'Jane',
				schemaName: new SchemaName( 'Person' ),
				statements: new StatementList( [
					TestStatement::build( 'Homepage', new StringValue( 'https://jane.example' ), 'url' ),
				] )
			),
		);

		$quads = $this->newProjector( [ 'Person' => $mapping ] )->projectPage( $page );

		$predicate = new Iri( self::EDM . 'isShownAt' );
		$graph = $this->ns->graph( 'edm', new PageId( 42 ) );
		$subjectIri = $this->ns->subject( new SubjectId( self::PERSON_ID ) );

		$this->assertTrue(
			$quads->contains( new Quad( $subjectIri, $predicate, new Literal( 'https://jane.example', $this->ns->xsd( 'anyURI' ) ), $graph ) ),
			'An explicit datatype override wins over the default IRI projection for a url value.'
		);
		$this->assertFalse(
			$quads->contains( new Quad( $subjectIri, $predicate, new Iri( 'https://jane.example' ), $graph ) ),
			'The url value is not also emitted as an IRI object when a datatype override is set.'
		);
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testDatatypeAndLanguageOnARelationPropertyAreIgnored(): void {
		// Constructed directly, bypassing the save-time lang/datatype mutual-exclusion check, to prove
		// the projector ignores literal overrides on a relation (its object is an IRI, not a literal).
		$mapping = new SchemaMapping(
			subject: new SubjectMapping( 'http://example.org/CHO' ),
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

		$quads = $this->newProjector( [ 'Person' => $mapping ] )->projectPage( $page );

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
			subject: new SubjectMapping( 'http://example.org/CHO' ),
			properties: new PropertyMappings( [
				'BirthYear' => new PropertyMapping( 'dc:date', $lang, null ),
			] )
		);
	}

	public function testProjectsSubjectsOfDifferentSchemasOnTheSamePage(): void {
		// One page, two Subjects of different Schemas, both with an entry on the Mapping page: both project
		// with their own class, so a multi-Schema Mapping page covers every Schema it lists.
		$quads = $this->newProjector( [ 'Person' => $this->personMapping(), 'City' => $this->cityMapping() ] )
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
	 * @param array<string, string> $extra
	 * @return array<string, string>
	 */
	private function serializerPrefixes( array $extra = [] ): array {
		return array_merge( $this->ns->prefixMap(), [ 'edm' => self::EDM, 'dc' => self::DC ], $extra );
	}

	// Structural transformation: synthesized nodes (expansion) and contributions (contraction).

	private function newCrmProjector( string $schemaName, SchemaMapping $mapping ): OntologyMappingProjector {
		return $this->newProjector( [ $schemaName => $mapping ], [ 'crm' => self::CRM, 'rdaGr2' => self::RDA_GR2 ] );
	}

	private function crmTriG( string $body ): string {
		return <<<TRIG
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix neo-graph: <https://wiki.example/graph/edm/page/> .
			@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			@prefix crm: <http://www.cidoc-crm.org/cidoc-crm/> .
			@prefix rdaGr2: <http://rdvocab.info/ElementsGr2/> .

			neo-graph:42 {
			$body
			}
			TRIG;
	}

	private function assertProjectsTo( string $expectedTriG, QuadList $quads ): void {
		$this->assertSame(
			ParsedRdf::canonicalQuads( $expectedTriG ),
			ParsedRdf::canonicalQuads(
				( new HardfRdfSerializer( $this->serializerPrefixes( [ 'crm' => self::CRM, 'rdaGr2' => self::RDA_GR2 ] ) ) )
					->serialize( $quads, RdfFormat::TriG )
			)
		);
	}

	/**
	 * A Person with the flat birth fields the near-1:1 tier consumes, which CIDOC-CRM mediates through an
	 * E67_Birth event node.
	 */
	private function personWithFlatBirthFields(): Page {
		return TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Birth date', new StringValue( '1990-01-31' ), 'date' ),
				TestStatement::buildRelation( 'Birth place', [ TestRelation::build( targetId: self::CITY_ID ) ] ),
			] )
		) );
	}

	public function testEveryPropertyAttachedToASubjectScopedNodeSharesOneInstance(): void {
		$quads = $this->newCrmProjector( 'Person', new SchemaMapping(
			subject: new SubjectMapping( 'crm:E21_Person' ),
			properties: new PropertyMappings( [
				'Birth date' => new PropertyMapping( predicate: 'crm:P4_has_time-span', node: 'birth' ),
				'Birth place' => new PropertyMapping( predicate: 'crm:P7_took_place_at', node: 'birth' ),
			] ),
			nodes: [
				'birth' => new NodeMapping( class: 'crm:E67_Birth', linkPredicate: 'crm:P98i_was_born' ),
			],
		) )->projectPage( $this->personWithFlatBirthFields() );

		// One E67_Birth, reached by one P98i_was_born, carrying both properties: the two flat fields are
		// coordinated onto the same synthesized node.
		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				crm:P98i_was_born <https://wiki.example/node/s1janeaaaaaaaa2/birth> .

			<https://wiki.example/node/s1janeaaaaaaaa2/birth> a crm:E67_Birth ;
				crm:P4_has_time-span "1990-01-31"^^xsd:date ;
				crm:P7_took_place_at neo-subj:s1cityaaaaaaaa3 .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testNestedNodeHangsOffItsParentInstanceAndBringsTheChainAlong(): void {
		$quads = $this->newCrmProjector( 'Person', new SchemaMapping(
			subject: new SubjectMapping( 'crm:E21_Person' ),
			properties: new PropertyMappings( [
				// Only the deepest node carries a value: the E67_Birth between it and the Person is
				// emitted because its descendant needs it.
				'Birth date' => new PropertyMapping( predicate: 'crm:P82_at_some_time_within', node: 'birthTimespan' ),
			] ),
			nodes: [
				'birth' => new NodeMapping( class: 'crm:E67_Birth', linkPredicate: 'crm:P98i_was_born' ),
				'birthTimespan' => new NodeMapping(
					class: 'crm:E52_Time-Span',
					linkPredicate: 'crm:P4_has_time-span',
					parent: 'birth'
				),
			],
		) )->projectPage( $this->personWithFlatBirthFields() );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				crm:P98i_was_born <https://wiki.example/node/s1janeaaaaaaaa2/birth> .

			<https://wiki.example/node/s1janeaaaaaaaa2/birth> a crm:E67_Birth ;
				crm:P4_has_time-span <https://wiki.example/node/s1janeaaaaaaaa2/birth/birthTimespan> .

			<https://wiki.example/node/s1janeaaaaaaaa2/birth/birthTimespan> a crm:E52_Time-Span ;
				crm:P82_at_some_time_within "1990-01-31"^^xsd:date .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testANodeIsNotEmittedForAPropertyTheSubjectDoesNotHave(): void {
		// The mapping declares the birth node, but the Person carries no value for the property that
		// attaches to it, so there must be no empty E67_Birth in the output.
		$quads = $this->newCrmProjector( 'Person', new SchemaMapping(
			subject: new SubjectMapping( 'crm:E21_Person' ),
			properties: new PropertyMappings( [
				'Death date' => new PropertyMapping( predicate: 'crm:P4_has_time-span', node: 'birth' ),
			] ),
			nodes: [
				'birth' => new NodeMapping( class: 'crm:E67_Birth', linkPredicate: 'crm:P98i_was_born' ),
			],
		) )->projectPage( $this->personWithFlatBirthFields() );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testANodeIsNotEmittedWhenItsPropertyProducesNoValueTriple(): void {
		// The Subject does hold the attached property, but its Property Type has no RDF value mapper, so
		// it produces nothing to hang off the node — and an event node with no content is worse than none.
		$page = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Birth date', new StringValue( '1990-01-31' ), 'typewithoutanrdfmapper' ),
			] )
		) );

		$quads = $this->newCrmProjector( 'Person', new SchemaMapping(
			subject: new SubjectMapping( 'crm:E21_Person' ),
			properties: new PropertyMappings( [
				'Birth date' => new PropertyMapping( predicate: 'crm:P4_has_time-span', node: 'birth' ),
			] ),
			nodes: [
				'birth' => new NodeMapping( class: 'crm:E67_Birth', linkPredicate: 'crm:P98i_was_born' ),
			],
		) )->projectPage( $page );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testValueScopedNodeGetsOneRelationIdAnchoredInstancePerRelationValue(): void {
		$artwork = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::ARTWORK_ID,
			label: 'The Milkmaid',
			schemaName: new SchemaName( 'Artwork' ),
			statements: new StatementList( [
				TestStatement::buildRelation( 'Creator', [
					TestRelation::build( id: 'r1firstaaaaaaa2', targetId: self::PERSON_ID ),
					TestRelation::build( id: 'r1secondaaaaaa3', targetId: self::CITY_ID ),
				] ),
			] )
		) );

		$quads = $this->newProjector(
			[ 'Artwork' => new SchemaMapping(
				subject: new SubjectMapping( 'crm:E22_Human-Made_Object' ),
				properties: new PropertyMappings( [
					'Creator' => new PropertyMapping( predicate: 'crm:P14_carried_out_by', node: 'production' ),
				] ),
				nodes: [
					'production' => new NodeMapping(
						class: 'crm:E12_Production',
						linkPredicate: 'crm:P108i_was_produced_by',
						scope: NodeScope::Value
					),
				],
			) ],
			[ 'crm' => self::CRM ]
		)->projectPage( $artwork );

		// Two creators, two production events, each IRI derived from its Relation's persistent ID.
		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1artworkaaaaa4 a crm:E22_Human-Made_Object ;
				rdfs:label "The Milkmaid" ;
				crm:P108i_was_produced_by <https://wiki.example/node/r1firstaaaaaaa2>, <https://wiki.example/node/r1secondaaaaaa3> .

			<https://wiki.example/node/r1firstaaaaaaa2> a crm:E12_Production ;
				crm:P14_carried_out_by neo-subj:s1janeaaaaaaaa2 .

			<https://wiki.example/node/r1secondaaaaaa3> a crm:E12_Production ;
				crm:P14_carried_out_by neo-subj:s1cityaaaaaaaa3 .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testValueScopedNodeGetsOnePositionAnchoredInstancePerLiteralValue(): void {
		$page = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Also known as', new StringValue( 'Janey', 'J. Doe' ), 'text' ),
			] )
		) );

		$quads = $this->newCrmProjector( 'Person', new SchemaMapping(
			subject: new SubjectMapping( 'crm:E21_Person' ),
			properties: new PropertyMappings( [
				'Also known as' => new PropertyMapping( predicate: 'crm:P190_has_symbolic_content', node: 'appellation' ),
			] ),
			nodes: [
				'appellation' => new NodeMapping(
					class: 'crm:E41_Appellation',
					linkPredicate: 'crm:P1_is_identified_by',
					scope: NodeScope::Value
				),
			],
		) )->projectPage( $page );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				crm:P1_is_identified_by <https://wiki.example/node/s1janeaaaaaaaa2/appellation/0>, <https://wiki.example/node/s1janeaaaaaaaa2/appellation/1> .

			<https://wiki.example/node/s1janeaaaaaaaa2/appellation/0> a crm:E41_Appellation ;
				crm:P190_has_symbolic_content "Janey" .

			<https://wiki.example/node/s1janeaaaaaaaa2/appellation/1> a crm:E41_Appellation ;
				crm:P190_has_symbolic_content "J. Doe" .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testAnUnusableNodeDropsItsWholeSubtreeButLeavesTheRestOfTheSubject(): void {
		$quads = $this->newCrmProjector( 'Person', new SchemaMapping(
			subject: new SubjectMapping( 'crm:E21_Person' ),
			properties: new PropertyMappings( [
				'Birth date' => new PropertyMapping( predicate: 'crm:P82_at_some_time_within', node: 'birthTimespan' ),
				'Birth place' => new PropertyMapping( predicate: 'rdaGr2:placeOfBirth' ),
			] ),
			nodes: [
				// An unresolvable class: the node and everything under it must be dropped.
				'birth' => new NodeMapping( class: 'nosuchprefix:E67_Birth', linkPredicate: 'crm:P98i_was_born' ),
				'birthTimespan' => new NodeMapping(
					class: 'crm:E52_Time-Span',
					linkPredicate: 'crm:P4_has_time-span',
					parent: 'birth'
				),
			],
		) )->projectPage( $this->personWithFlatBirthFields() );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				rdaGr2:placeOfBirth neo-subj:s1cityaaaaaaaa3 .
			TRIG ), $quads );
		// One warning for the unusable node, one for the child it orphaned, one for the property that
		// pointed at the child.
		$this->assertCount( 3, $this->logger->getLogCalls()->getMessages() );
	}

	public function testLabelPredicateAddsATargetLabelTermWithoutReplacingRdfsLabel(): void {
		$quads = $this->newCrmProjector( 'Person', new SchemaMapping(
			subject: new SubjectMapping( class: 'crm:E21_Person', labelPredicate: 'rdaGr2:nameOfThePerson' ),
		) )->projectPage( TestPage::build( id: 42, mainSubject: $this->examplePersonWithoutRelations() ) );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				rdaGr2:nameOfThePerson "Jane" .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	/**
	 * Contraction. The Birth Subject holds the structure a flat target does not want, so it emits its own
	 * date and place as flat properties **of the people it brought into life** — twins share one Birth, so
	 * both receive them — and the place, a relation-valued contribution property, arrives as the Place
	 * Subject's IRI. All of it lands in the Birth page's graph, since that is the page being projected.
	 */
	public function testContributionEmitsThisSubjectsValuesAboutEveryTargetOfItsRelation(): void {
		$birth = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::BIRTH_ID,
			label: 'Birth of the twins',
			schemaName: new SchemaName( 'Birth' ),
			statements: new StatementList( [
				TestStatement::buildRelation( 'Brought into life', [
					TestRelation::build( targetId: self::PERSON_ID ),
					TestRelation::build( targetId: self::TWIN_ID ),
				] ),
				TestStatement::buildRelation( 'Took place at', [ TestRelation::build( targetId: self::CITY_ID ) ] ),
				TestStatement::build( 'Date', new StringValue( '1990-01-31' ), 'date' ),
				TestStatement::build( 'Weather', new StringValue( 'Rainy' ), 'text' ),
			] )
		) );

		$quads = $this->newProjector(
			[ 'Birth' => new SchemaMapping(
				subject: null,
				contributions: [ 'Brought into life' => new PropertyMappings( [
					'Date' => new PropertyMapping( 'rdaGr2:dateOfBirth' ),
					'Took place at' => new PropertyMapping( 'rdaGr2:placeOfBirth' ),
				] ) ],
			) ],
			[ 'rdaGr2' => self::RDA_GR2 ]
		)->projectPage( $birth );

		// No type or label for the Birth itself: the entry only contributes. The unmapped Weather is
		// absent, as unmapped properties always are.
		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 rdaGr2:dateOfBirth "1990-01-31"^^xsd:date ;
				rdaGr2:placeOfBirth neo-subj:s1cityaaaaaaaa3 .

			neo-subj:s1twinaaaaaaaa5 rdaGr2:dateOfBirth "1990-01-31"^^xsd:date ;
				rdaGr2:placeOfBirth neo-subj:s1cityaaaaaaaa3 .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testContributionThroughARelationTheSubjectHasNoValueForIsSilentlySkipped(): void {
		// An optional relation left empty produces no Statement at all, which is ordinary sparse data
		// rather than a misconfigured Mapping, so it must not fill the log on every page it touches.
		$page = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::BIRTH_ID,
			label: 'A birth with no child',
			schemaName: new SchemaName( 'Birth' ),
			statements: new StatementList( [ TestStatement::build( 'Date', new StringValue( '1990-01-31' ), 'date' ) ] )
		) );

		$quads = $this->newProjector(
			[ 'Birth' => $this->birthContributingItsDate() ],
			[ 'rdaGr2' => self::RDA_GR2 ]
		)->projectPage( $page );

		$this->assertTrue( $quads->isEmpty() );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testContributionThroughAPropertyThatIsNotARelationIsLogged(): void {
		// The Subject does hold the named property, but as text: the Mapping and the Schema disagree
		// about what it is, which is worth telling the wiki about.
		$page = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::BIRTH_ID,
			label: 'A birth naming its child in prose',
			schemaName: new SchemaName( 'Birth' ),
			statements: new StatementList( [
				TestStatement::build( 'Brought into life', new StringValue( 'Jane' ), 'text' ),
				TestStatement::build( 'Date', new StringValue( '1990-01-31' ), 'date' ),
			] )
		) );

		$quads = $this->newProjector(
			[ 'Birth' => $this->birthContributingItsDate() ],
			[ 'rdaGr2' => self::RDA_GR2 ]
		)->projectPage( $page );

		$this->assertTrue( $quads->isEmpty() );
		$this->assertCount( 1, $this->logger->getLogCalls()->getMessages() );
	}

	private function birthContributingItsDate(): SchemaMapping {
		return new SchemaMapping(
			subject: null,
			contributions: [ 'Brought into life' => new PropertyMappings( [
				'Date' => new PropertyMapping( 'rdaGr2:dateOfBirth' ),
			] ) ],
		);
	}

	/**
	 * An entry may project its own Subject and contribute at the same time, through more than one
	 * relation: the Birth is an event in its own right for a target that has a class for it, and still
	 * flattens its date onto both the child and the place it happened at.
	 */
	public function testAnEntryProjectsItsOwnSubjectAndContributesThroughSeveralRelations(): void {
		$birth = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::BIRTH_ID,
			label: 'Birth of Jane',
			schemaName: new SchemaName( 'Birth' ),
			statements: new StatementList( [
				TestStatement::buildRelation( 'Brought into life', [ TestRelation::build( targetId: self::PERSON_ID ) ] ),
				TestStatement::buildRelation( 'Took place at', [ TestRelation::build( targetId: self::CITY_ID ) ] ),
				TestStatement::build( 'Date', new StringValue( '1990-01-31' ), 'date' ),
			] )
		) );

		$quads = $this->newProjector(
			[ 'Birth' => new SchemaMapping(
				subject: new SubjectMapping( 'crm:E67_Birth' ),
				properties: new PropertyMappings( [
					'Brought into life' => new PropertyMapping( 'crm:P98_brought_into_life' ),
				] ),
				contributions: [
					'Brought into life' => new PropertyMappings( [
						'Date' => new PropertyMapping( 'rdaGr2:dateOfBirth' ),
					] ),
					'Took place at' => new PropertyMappings( [
						'Date' => new PropertyMapping( 'rdaGr2:dateOfEstablishment' ),
					] ),
				],
			) ],
			[ 'crm' => self::CRM, 'rdaGr2' => self::RDA_GR2 ]
		)->projectPage( $birth );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1birthaaaaaaa6 a crm:E67_Birth ;
				rdfs:label "Birth of Jane" ;
				crm:P98_brought_into_life neo-subj:s1janeaaaaaaaa2 .

			neo-subj:s1janeaaaaaaaa2 rdaGr2:dateOfBirth "1990-01-31"^^xsd:date .

			neo-subj:s1cityaaaaaaaa3 rdaGr2:dateOfEstablishment "1990-01-31"^^xsd:date .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	// Shapes save-time validation rejects, which XML import can still put on a wiki. They reach the
	// projector through the persistence deserializer, so these go in the same way rather than being
	// hand-built, and must degrade the projection instead of aborting it.

	private function newImportedProjector( string $json ): OntologyMappingProjector {
		return new OntologyMappingProjector(
			( new MappingPersistenceDeserializer() )->deserialize( new MappingName( 'edm' ), $json ),
			$this->ns,
			RdfValueMapperRegistry::withCoreMappers(),
			$this->logger,
		);
	}

	public function testAnAllDigitNodeKeyProjects(): void {
		// json_decode turns an all-digit object key into an int array key, which every consumer of the
		// key has to survive.
		$quads = $this->newImportedProjector( <<<'JSON'
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": { "2024": { "class": "crm:E67_Birth", "linkPredicate": "crm:P98i_was_born" } },
						"properties": { "Birth date": { "predicate": "crm:P82_at_some_time_within", "node": "2024" } }
					}
				}
			}
			JSON )->projectPage( $this->personWithFlatBirthFields() );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				crm:P98i_was_born <https://wiki.example/node/s1janeaaaaaaaa2/2024> .

			<https://wiki.example/node/s1janeaaaaaaaa2/2024> a crm:E67_Birth ;
				crm:P82_at_some_time_within "1990-01-31"^^xsd:date .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testAnAllDigitPropertyNameProjects(): void {
		// A year is a realistic property name, and is an int array key by the time json_decode is done.
		$page = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [ TestStatement::build( '2020', new StringValue( 'Sabbatical' ), 'text' ) ] )
		) );

		$quads = $this->newImportedProjector( <<<'JSON'
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"properties": { "2020": { "predicate": "crm:P3_has_note" } }
					}
				}
			}
			JSON )->projectPage( $page );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				crm:P3_has_note "Sabbatical" .
			TRIG ), $quads );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testANodeHangingOffAPerValueNodeIsDroppedWithWhatItCarries(): void {
		// A per-value parent has one instance per value, so a child would have no single instance to hang
		// off. The parent itself still projects.
		$artwork = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::ARTWORK_ID,
			label: 'The Milkmaid',
			schemaName: new SchemaName( 'Artwork' ),
			statements: new StatementList( [
				TestStatement::buildRelation( 'Creator', [
					TestRelation::build( id: 'r1firstaaaaaaa2', targetId: self::PERSON_ID ),
				] ),
				TestStatement::build( 'Year', new StringValue( '1658' ), 'text' ),
			] )
		) );

		$quads = $this->newImportedProjector( <<<'JSON'
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Artwork": {
						"subject": { "class": "crm:E22_Human-Made_Object" },
						"nodes": {
							"production": {
								"class": "crm:E12_Production",
								"linkPredicate": "crm:P108i_was_produced_by",
								"per": "value"
							},
							"timespan": {
								"class": "crm:E52_Time-Span",
								"linkPredicate": "crm:P4_has_time-span",
								"parent": "production"
							}
						},
						"properties": {
							"Creator": { "predicate": "crm:P14_carried_out_by", "node": "production" },
							"Year": { "predicate": "crm:P82_at_some_time_within", "node": "timespan" }
						}
					}
				}
			}
			JSON )->projectPage( $artwork );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1artworkaaaaa4 a crm:E22_Human-Made_Object ;
				rdfs:label "The Milkmaid" ;
				crm:P108i_was_produced_by <https://wiki.example/node/r1firstaaaaaaa2> .

			<https://wiki.example/node/r1firstaaaaaaa2> a crm:E12_Production ;
				crm:P14_carried_out_by neo-subj:s1janeaaaaaaaa2 .
			TRIG ), $quads );
		// One for the child of the per-value node, one for the property that pointed at that child.
		$this->assertCount( 2, $this->logger->getLogCalls()->getMessages() );
	}

	public function testAParentCycleDropsOnlyTheNodesInTheCycle(): void {
		$page = TestPage::build( id: 42, mainSubject: TestSubject::build(
			id: self::PERSON_ID,
			label: 'Jane',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Birth date', new StringValue( '1990-01-31' ), 'date' ),
				TestStatement::build( 'Also known as', new StringValue( 'Janey' ), 'text' ),
			] )
		) );

		$quads = $this->newImportedProjector( <<<'JSON'
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": {
							"birth": {
								"class": "crm:E67_Birth",
								"linkPredicate": "crm:P98i_was_born",
								"parent": "birthTimespan"
							},
							"birthTimespan": {
								"class": "crm:E52_Time-Span",
								"linkPredicate": "crm:P4_has_time-span",
								"parent": "birth"
							},
							"naming": { "class": "crm:E41_Appellation", "linkPredicate": "crm:P1_is_identified_by" }
						},
						"properties": {
							"Birth date": { "predicate": "crm:P82_at_some_time_within", "node": "birthTimespan" },
							"Also known as": { "predicate": "crm:P190_has_symbolic_content", "node": "naming" }
						}
					}
				}
			}
			JSON )->projectPage( $page );

		$this->assertProjectsTo( $this->crmTriG( <<<'TRIG'
			neo-subj:s1janeaaaaaaaa2 a crm:E21_Person ;
				rdfs:label "Jane" ;
				crm:P1_is_identified_by <https://wiki.example/node/s1janeaaaaaaaa2/naming> .

			<https://wiki.example/node/s1janeaaaaaaaa2/naming> a crm:E41_Appellation ;
				crm:P190_has_symbolic_content "Janey" .
			TRIG ), $quads );
		// One for each node in the cycle, one for the property that pointed into it.
		$this->assertCount( 3, $this->logger->getLogCalls()->getMessages() );
	}

	public function testSynthesizedNodeIrisAreIdenticalAcrossProjectionsOfTheSamePage(): void {
		// Re-projection must be byte-identical, because the per-page store sync replaces a graph with
		// DROP + INSERT DATA: a node IRI that moved would leave the store inconsistent.
		$mapping = new SchemaMapping(
			subject: new SubjectMapping( 'crm:E21_Person' ),
			properties: new PropertyMappings( [
				'Birth date' => new PropertyMapping( predicate: 'crm:P82_at_some_time_within', node: 'birthTimespan' ),
				'Birth place' => new PropertyMapping( predicate: 'crm:P7_took_place_at', node: 'birth' ),
			] ),
			nodes: [
				'birth' => new NodeMapping( class: 'crm:E67_Birth', linkPredicate: 'crm:P98i_was_born' ),
				'birthTimespan' => new NodeMapping(
					class: 'crm:E52_Time-Span',
					linkPredicate: 'crm:P4_has_time-span',
					parent: 'birth'
				),
			],
		);
		$page = $this->personWithFlatBirthFields();

		$first = $this->newCrmProjector( 'Person', $mapping )->projectPage( $page );
		$second = $this->newCrmProjector( 'Person', $mapping )->projectPage( $page );

		$serializer = new HardfRdfSerializer( $this->serializerPrefixes( [ 'crm' => self::CRM ] ) );
		$this->assertSame(
			$serializer->serialize( $first, RdfFormat::TriG ),
			$serializer->serialize( $second, RdfFormat::TriG )
		);
	}

}
