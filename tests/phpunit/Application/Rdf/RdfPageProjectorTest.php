<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
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
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector
 */
class RdfPageProjectorTest extends TestCase {

	private const string ACME_ID = 's1acmeaaaaaaaa1';
	private const string JANE_ID = 's1janeaaaaaaaa2';
	private const string CEO_RELATION_ID = 'r1ceoaaaaaaaaa3';
	private const string AFTER_ID = 's1afteraaaaaaa9';

	private RdfNamespaces $ns;
	private LegacyLoggerSpy $logger;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
		$this->logger = new LegacyLoggerSpy();
	}

	private function newProjector( InMemorySchemaLookup $schemaLookup ): RdfPageProjector {
		return new RdfPageProjector(
			RdfValueMapperRegistry::withCoreMappers(),
			$this->ns,
			$schemaLookup,
			$this->logger,
		);
	}

	public function testProjectsTheCompleteExampleToTheSpecifiedQuadSet(): void {
		$schemaLookup = new InMemorySchemaLookup(
			TestSchema::build(
				name: 'Company',
				properties: new PropertyDefinitions( [
					'CEO' => TestProperty::buildRelation( relationType: 'CEO', targetSchema: 'Person' ),
				] )
			),
			TestSchema::build( name: 'Person', properties: new PropertyDefinitions( [] ) ),
		);

		$quads = $this->newProjector( $schemaLookup )->projectPage( $this->completeExamplePage() );
		$output = ( new HardfRdfSerializer( $this->ns->prefixMap() ) )->serialize( $quads, RdfFormat::TriG );

		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->completeExampleTriG() ),
			ParsedRdf::canonicalQuads( $output )
		);
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	private function completeExamplePage(): Page {
		$acme = TestSubject::build(
			id: self::ACME_ID,
			label: 'ACME Corp',
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				TestStatement::build( 'Website', new StringValue( 'https://acme.example' ), 'url' ),
				TestStatement::build( 'Founded', new NumberValue( 2019 ), 'number' ),
				TestStatement::buildRelation( 'CEO', [
					TestRelation::build(
						id: self::CEO_RELATION_ID,
						targetId: self::JANE_ID,
						properties: [ 'Since' => 2022 ],
					),
				] ),
			] )
		);

		$jane = TestSubject::build(
			id: self::JANE_ID,
			label: 'Jane Smith',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Age', new NumberValue( 45 ), 'number' ),
			] )
		);

		return TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'ACME Corp',
				creationTime: '20240301090000',
				modificationTime: '20251115164500',
				lastEditor: 'Admin',
			),
			mainSubject: $acme,
			childSubjects: new SubjectMap( $jane ),
		);
	}

	private function completeExampleTriG(): string {
		return <<<TRIG
			@prefix neo: <https://wiki.example/ontology/> .
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix neo-prop: <https://wiki.example/prop/> .
			@prefix neo-schema: <https://wiki.example/schema/> .
			@prefix neo-rel: <https://wiki.example/relation/> .
			@prefix neo-page: <https://wiki.example/page/> .
			@prefix neo-graph: <https://wiki.example/graph/native/page/> .
			@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			@prefix dcterms: <http://purl.org/dc/terms/> .

			neo-graph:42 {
				neo-page:42 a neo:Page ;
					neo:pageName "ACME Corp" ;
					dcterms:created "2024-03-01T09:00:00Z"^^xsd:dateTime ;
					dcterms:modified "2025-11-15T16:45:00Z"^^xsd:dateTime ;
					neo:lastEditor "Admin" ;
					neo:mainSubject neo-subj:s1acmeaaaaaaaa1 ;
					neo:hasSubject neo-subj:s1acmeaaaaaaaa1 ;
					neo:hasSubject neo-subj:s1janeaaaaaaaa2 .

				neo-subj:s1acmeaaaaaaaa1 a neo-schema:Company ;
					rdfs:label "ACME Corp" ;
					neo-prop:Website <https://acme.example> ;
					neo-prop:Founded 2019 ;
					neo-prop:CEO neo-subj:s1janeaaaaaaaa2 .

				neo-rel:r1ceoaaaaaaaaa3 a neo:Relation ;
					neo:source neo-subj:s1acmeaaaaaaaa1 ;
					neo:target neo-subj:s1janeaaaaaaaa2 ;
					neo:relationType neo-prop:CEO ;
					neo-prop:Since 2022 .

				neo-subj:s1janeaaaaaaaa2 a neo-schema:Person ;
					rdfs:label "Jane Smith" ;
					neo-prop:Age 45 .
			}
			TRIG;
	}

	public function testMultiValuedUrlProducesRepeatedPredicatesWithIriObjects(): void {
		$subject = TestSubject::build(
			id: self::ACME_ID,
			label: 'ACME Corp',
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				TestStatement::build( 'Website', new StringValue( 'https://a.example', 'https://b.example' ), 'url' ),
			] )
		);
		$page = TestPage::build( id: 42, mainSubject: $subject );

		$quads = $this->newProjector( new InMemorySchemaLookup( $this->companySchema() ) )->projectPage( $page );

		$this->assertTrue( $quads->contains( $this->quad(
			$this->ns->subject( new SubjectId( self::ACME_ID ) ),
			$this->ns->property( 'Website' ),
			new Iri( 'https://a.example' )
		) ) );
		$this->assertTrue( $quads->contains( $this->quad(
			$this->ns->subject( new SubjectId( self::ACME_ID ) ),
			$this->ns->property( 'Website' ),
			new Iri( 'https://b.example' )
		) ) );
	}

	public function testSubjectWithMissingSchemaIsSkippedEntirelyWhileOtherSubjectsProject(): void {
		$acme = TestSubject::build(
			id: self::ACME_ID,
			label: 'ACME Corp',
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				TestStatement::build( 'Founded', new NumberValue( 2019 ), 'number' ),
				TestStatement::buildRelation( 'CEO', [
					TestRelation::build( id: self::CEO_RELATION_ID, targetId: self::JANE_ID ),
				] ),
			] )
		);
		$jane = TestSubject::build(
			id: self::JANE_ID,
			label: 'Jane Smith',
			schemaName: new SchemaName( 'Person' ),
		);
		$page = TestPage::build( id: 42, mainSubject: $acme, childSubjects: new SubjectMap( $jane ) );

		// Only Person is registered. The Company Schema is unavailable, so ACME is skipped entirely,
		// mirroring Neo4jSubjectUpdater — the projection must still hold the same entity set as Neo4j.
		$quads = $this->newProjector( new InMemorySchemaLookup(
			TestSchema::build( name: 'Person', properties: new PropertyDefinitions( [] ) )
		) )->projectPage( $page );

		$acmeIri = $this->ns->subject( new SubjectId( self::ACME_ID ) );
		$janeIri = $this->ns->subject( new SubjectId( self::JANE_ID ) );
		$pageIri = $this->ns->page( new PageId( 42 ) );

		// The schema-less Subject leaves no trace: no type, label, statements, or page reference.
		$this->assertFalse( $quads->contains( $this->quad( $acmeIri, $this->ns->rdfType(), $this->ns->schemaClass( new SchemaName( 'Company' ) ) ) ) );
		$this->assertFalse( $quads->contains( $this->quad( $acmeIri, $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'ACME Corp', 'string' ) ) ) );
		$this->assertFalse( $quads->contains( $this->quad( $acmeIri, $this->ns->property( 'Founded' ), RdfLiteralFactory::typed( '2019', 'integer' ) ) ) );
		$this->assertFalse(
			$quads->contains( $this->quad( $pageIri, $this->ns->term( RdfNamespaces::TERM_MAIN_SUBJECT ), $acmeIri ) ),
			'A skipped Subject must not remain the page main subject.'
		);
		$this->assertFalse(
			$quads->contains( $this->quad( $pageIri, $this->ns->term( RdfNamespaces::TERM_HAS_SUBJECT ), $acmeIri ) ),
			'A skipped Subject must not be referenced by the page.'
		);

		// The Subject whose Schema is available still projects in full.
		$this->assertTrue( $quads->contains( $this->quad( $janeIri, $this->ns->rdfType(), $this->ns->schemaClass( new SchemaName( 'Person' ) ) ) ) );
		$this->assertTrue( $quads->contains( $this->quad( $janeIri, $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'Jane Smith', 'string' ) ) ) );
		$this->assertTrue( $quads->contains( $this->quad( $pageIri, $this->ns->term( RdfNamespaces::TERM_HAS_SUBJECT ), $janeIri ) ) );

		$this->assertSame(
			[ 'Schema not found: Company' ],
			$this->logger->getLogCalls()->getMessages()
		);
	}

	public function testUnregisteredValueTypeStatementIsSkippedButSubjectStillProjects(): void {
		$subject = TestSubject::build(
			id: self::ACME_ID,
			label: 'ACME Corp',
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				TestStatement::build( 'Colour', new StringValue( 'green' ), 'colour-from-disabled-extension' ),
			] )
		);
		$page = TestPage::build( id: 42, mainSubject: $subject );

		$quads = $this->newProjector( new InMemorySchemaLookup( $this->companySchema() ) )->projectPage( $page );

		$acmeIri = $this->ns->subject( new SubjectId( self::ACME_ID ) );
		$this->assertTrue( $quads->contains( $this->quad( $acmeIri, $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'ACME Corp', 'string' ) ) ) );
		$this->assertFalse(
			$quads->contains( $this->quad( $acmeIri, $this->ns->property( 'Colour' ), RdfLiteralFactory::typed( 'green', 'string' ) ) )
		);
	}

	public function testDroppingAnInvalidDatePartLogsAWarning(): void {
		$subject = TestSubject::build(
			id: self::ACME_ID,
			label: 'ACME Corp',
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				TestStatement::build( 'Established', new StringValue( '2019-01-01', 'not-a-date' ), 'date' ),
			] )
		);
		$page = TestPage::build( id: 42, mainSubject: $subject );

		$quads = $this->newProjector( new InMemorySchemaLookup( $this->companySchema() ) )->projectPage( $page );

		$this->assertTrue( $quads->contains( $this->quad(
			$this->ns->subject( new SubjectId( self::ACME_ID ) ),
			$this->ns->property( 'Established' ),
			RdfLiteralFactory::typed( '2019-01-01', 'date' )
		) ) );
		$this->assertSame(
			[ 'Dropped 1 unrepresentable value(s) of property "Established" on page 42 when projecting to RDF' ],
			$this->logger->getLogCalls()->getMessages()
		);
	}

	/**
	 * The per-Subject export projects exactly the requested Subject's block from a full-page projection —
	 * its type, label, Statements and reified Relation — and nothing else: no page-metadata quads and
	 * none of the sibling Subjects' quads, even though a sibling (Jane) is the Relation target whose IRI
	 * does appear. The target sits between two siblings so a "project every Subject" regression fails.
	 */
	public function testProjectSubjectEmitsOnlyTheTargetSubjectsBlockIncludingRelationReification(): void {
		$schemaLookup = new InMemorySchemaLookup(
			$this->companySchema(),
			TestSchema::build( name: 'Person', properties: new PropertyDefinitions( [] ) ),
		);

		$quads = $this->newProjector( $schemaLookup )
			->projectSubject( $this->pageWithAcmeBetweenSiblings(), new SubjectId( self::ACME_ID ) );

		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->acmeSubjectTriG() ),
			ParsedRdf::canonicalQuads( $this->serialize( $quads ) )
		);
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testProjectSubjectDerivesTheNamedGraphFromTheHostingPageId(): void {
		$quads = $this->newProjector( new InMemorySchemaLookup( $this->companySchema() ) )
			->projectSubject( TestPage::build( id: 77, mainSubject: $this->acmeWithCeo() ), new SubjectId( self::ACME_ID ) );

		$this->assertSame(
			[ 'https://wiki.example/graph/native/page/77' ],
			$this->distinctGraphs( $quads ),
			'Every quad belongs to the hosting page\'s native named graph, derived from its id.'
		);
	}

	public function testProjectSubjectForASubjectNotOnThePageReturnsNothing(): void {
		$quads = $this->newProjector( new InMemorySchemaLookup( $this->companySchema() ) )
			->projectSubject( TestPage::build( id: 42, mainSubject: $this->acmeWithCeo() ), new SubjectId( self::AFTER_ID ) );

		$this->assertTrue( $quads->isEmpty() );
		$this->logger->assertNoLoggingCallsWhereMade();
	}

	public function testProjectSubjectForASubjectWithAnUnavailableSchemaReturnsNothingAndLogs(): void {
		$page = TestPage::build( id: 42, mainSubject: $this->acmeWithCeo() );

		// Only Person is registered, so the requested Company Subject's Schema is unavailable and it is
		// skipped — the same graceful degradation as the full-page projection.
		$quads = $this->newProjector( new InMemorySchemaLookup(
			TestSchema::build( name: 'Person', properties: new PropertyDefinitions( [] ) )
		) )->projectSubject( $page, new SubjectId( self::ACME_ID ) );

		$this->assertTrue( $quads->isEmpty() );
		$this->assertSame(
			[ 'Schema not found: Company' ],
			$this->logger->getLogCalls()->getMessages()
		);
	}

	private function acmeWithCeo(): Subject {
		return TestSubject::build(
			id: self::ACME_ID,
			label: 'ACME Corp',
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				TestStatement::build( 'Founded', new NumberValue( 2019 ), 'number' ),
				TestStatement::buildRelation( 'CEO', [
					TestRelation::build(
						id: self::CEO_RELATION_ID,
						targetId: self::JANE_ID,
						properties: [ 'Since' => 2022 ],
					),
				] ),
			] )
		);
	}

	private function pageWithAcmeBetweenSiblings(): Page {
		$jane = TestSubject::build(
			id: self::JANE_ID,
			label: 'Jane Smith',
			schemaName: new SchemaName( 'Person' ),
			statements: new StatementList( [
				TestStatement::build( 'Age', new NumberValue( 45 ), 'number' ),
			] )
		);
		$after = TestSubject::build(
			id: self::AFTER_ID,
			label: 'Later Co',
			schemaName: new SchemaName( 'Company' ),
			statements: new StatementList( [
				TestStatement::build( 'Founded', new NumberValue( 2000 ), 'number' ),
			] )
		);

		return TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'ACME Corp',
				creationTime: '20240301090000',
				modificationTime: '20251115164500',
				lastEditor: 'Admin',
			),
			mainSubject: $jane,
			childSubjects: new SubjectMap( $this->acmeWithCeo(), $after ),
		);
	}

	private function acmeSubjectTriG(): string {
		return <<<TRIG
			@prefix neo: <https://wiki.example/ontology/> .
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix neo-prop: <https://wiki.example/prop/> .
			@prefix neo-schema: <https://wiki.example/schema/> .
			@prefix neo-rel: <https://wiki.example/relation/> .
			@prefix neo-graph: <https://wiki.example/graph/native/page/> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

			neo-graph:42 {
				neo-subj:s1acmeaaaaaaaa1 a neo-schema:Company ;
					rdfs:label "ACME Corp" ;
					neo-prop:Founded 2019 ;
					neo-prop:CEO neo-subj:s1janeaaaaaaaa2 .

				neo-rel:r1ceoaaaaaaaaa3 a neo:Relation ;
					neo:source neo-subj:s1acmeaaaaaaaa1 ;
					neo:target neo-subj:s1janeaaaaaaaa2 ;
					neo:relationType neo-prop:CEO ;
					neo-prop:Since 2022 .
			}
			TRIG;
	}

	/**
	 * @return list<string>
	 */
	private function distinctGraphs( QuadList $quads ): array {
		return array_values( array_unique( array_map(
			static fn ( Quad $quad ): string => $quad->graph->value,
			$quads->asArray()
		) ) );
	}

	/**
	 * A user-authored Property or Schema name must never break out of its IRI: illegal characters
	 * are escaped so the document still parses to exactly the intended quads. The confirmed repro
	 * strings (a name closing the IRIREF with `>`, and one forging extra `<s> <p> <o> .` triples)
	 * must each yield a single statement quad, not injected triples.
	 *
	 * @dataProvider provideNamesThatMustBeEscaped
	 */
	public function testUserAuthoredNamesCannotBreakOutOfTheirIri( string $propertyName, string $expectedLocalName ): void {
		$output = $this->serialize(
			$this->newProjector( new InMemorySchemaLookup( $this->companySchema() ) )
				->projectPage( $this->pageWithTextProperty( $propertyName ) )
		);

		$this->assertSame(
			ParsedRdf::canonicalQuads( $this->expectedTriGForTextProperty( 'https://wiki.example/prop/' . $expectedLocalName ) ),
			ParsedRdf::canonicalQuads( $output )
		);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provideNamesThatMustBeEscaped(): iterable {
		yield 'less-than' => [ 'a<b', 'a%3Cb' ];
		yield 'greater-than closes the IRIREF (confirmed repro)' => [ 'Rev>2020', 'Rev%3E2020' ];
		yield 'double quote' => [ 'a"b', 'a%22b' ];
		yield 'opening brace' => [ 'a{b', 'a%7Bb' ];
		yield 'closing brace' => [ 'a}b', 'a%7Db' ];
		yield 'pipe' => [ 'a|b', 'a%7Cb' ];
		yield 'caret' => [ 'a^b', 'a%5Eb' ];
		yield 'backslash' => [ 'a\\b', 'a%5Cb' ];
		yield 'backtick' => [ 'a`b', 'a%60b' ];
		yield 'percent sign' => [ 'a%b', 'a%25b' ];
		yield 'control character' => [ "a\x01b", 'a%01b' ];
		yield 'unicode passes through raw' => [ 'Naïve 日本語', 'Naïve_日本語' ];
		yield 'triple injection breakout (confirmed repro)' => [
			'p><evil-s>.<evil-s2><evil-p2><evil-o2>.<evil-s3><evil-p3',
			'p%3E%3Cevil-s%3E.%3Cevil-s2%3E%3Cevil-p2%3E%3Cevil-o2%3E.%3Cevil-s3%3E%3Cevil-p3',
		];
	}

	private function pageWithTextProperty( string $propertyName ): Page {
		return TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'ACME Corp',
				creationTime: '20240301090000',
				modificationTime: '20240301090000',
				lastEditor: 'Admin',
			),
			mainSubject: TestSubject::build(
				id: self::ACME_ID,
				label: 'ACME Corp',
				schemaName: new SchemaName( 'Company' ),
				statements: new StatementList( [
					TestStatement::build( $propertyName, new StringValue( 'v' ), 'text' ),
				] )
			),
		);
	}

	private function expectedTriGForTextProperty( string $predicateIri ): string {
		return <<<TRIG
			@prefix neo: <https://wiki.example/ontology/> .
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix neo-schema: <https://wiki.example/schema/> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			@prefix dcterms: <http://purl.org/dc/terms/> .

			<https://wiki.example/graph/native/page/42> {
				<https://wiki.example/page/42> a neo:Page ;
					neo:pageName "ACME Corp" ;
					dcterms:created "2024-03-01T09:00:00Z"^^xsd:dateTime ;
					dcterms:modified "2024-03-01T09:00:00Z"^^xsd:dateTime ;
					neo:lastEditor "Admin" ;
					neo:mainSubject neo-subj:s1acmeaaaaaaaa1 ;
					neo:hasSubject neo-subj:s1acmeaaaaaaaa1 .

				neo-subj:s1acmeaaaaaaaa1 a neo-schema:Company ;
					rdfs:label "ACME Corp" ;
					<$predicateIri> "v" .
			}
			TRIG;
	}

	private function serialize( QuadList $quads ): string {
		return ( new HardfRdfSerializer( $this->ns->prefixMap() ) )->serialize( $quads, RdfFormat::TriG );
	}

	private function companySchema(): Schema {
		return TestSchema::build(
			name: 'Company',
			properties: new PropertyDefinitions( [
				'CEO' => TestProperty::buildRelation( relationType: 'CEO', targetSchema: 'Person' ),
			] )
		);
	}

	private function quad( Iri $subject, Iri $predicate, Literal|Iri $object ): Quad {
		return new Quad( $subject, $predicate, $object, $this->ns->graph( RdfPageProjector::PROJECTION, new PageId( 42 ) ) );
	}

}
