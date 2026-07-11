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
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
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
			@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			@prefix dcterms: <http://purl.org/dc/terms/> .

			neo-page:42 {
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
					neo-prop:Website "https://acme.example"^^xsd:anyURI ;
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

	public function testMultiValuedTextProducesRepeatedPredicates(): void {
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
			new Literal( 'https://a.example', $this->ns->xsd( 'anyURI' ) )
		) ) );
		$this->assertTrue( $quads->contains( $this->quad(
			$this->ns->subject( new SubjectId( self::ACME_ID ) ),
			$this->ns->property( 'Website' ),
			new Literal( 'https://b.example', $this->ns->xsd( 'anyURI' ) )
		) ) );
	}

	public function testMissingSchemaSkipsRelationsButKeepsTypeLabelAndStatements(): void {
		$subject = TestSubject::build(
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
		$page = TestPage::build( id: 42, mainSubject: $subject );

		// No Company schema registered, so relations cannot be typed.
		$quads = $this->newProjector( new InMemorySchemaLookup() )->projectPage( $page );

		$acmeIri = $this->ns->subject( new SubjectId( self::ACME_ID ) );
		$this->assertTrue( $quads->contains( $this->quad( $acmeIri, $this->ns->rdfType(), $this->ns->schemaClass( new SchemaName( 'Company' ) ) ) ) );
		$this->assertTrue( $quads->contains( $this->quad( $acmeIri, $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'ACME Corp', 'string' ) ) ) );
		$this->assertTrue( $quads->contains( $this->quad( $acmeIri, $this->ns->property( 'Founded' ), RdfLiteralFactory::typed( '2019', 'integer' ) ) ) );

		$this->assertFalse(
			$quads->contains( $this->quad( $acmeIri, $this->ns->property( 'CEO' ), $this->ns->subject( new SubjectId( self::JANE_ID ) ) ) ),
			'The direct relation triple must be absent when the Schema is unavailable.'
		);
		$this->assertSame(
			[ 'Schema not found when projecting relations to RDF: Company' ],
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

	private function companySchema(): Schema {
		return TestSchema::build(
			name: 'Company',
			properties: new PropertyDefinitions( [
				'CEO' => TestProperty::buildRelation( relationType: 'CEO', targetSchema: 'Person' ),
			] )
		);
	}

	private function quad( Iri $subject, Iri $predicate, Literal|Iri $object ): Quad {
		return new Quad( $subject, $predicate, $object, $this->ns->page( new PageId( 42 ) ) );
	}

}
