<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use DateTimeImmutable;
use DateTimeZone;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageValue;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelation;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use Psr\Log\LoggerInterface;

/**
 * Projects a {@see Page} to the native RDF quads specified in NativeRdfProjection.md: page metadata,
 * one resource per Subject (rdf:type from the Schema, rdfs:label, one predicate per Statement value),
 * and the two-layer relation reification. Every quad is placed in the page's named graph.
 *
 * Schema resolution mirrors Neo4jSubjectUpdater: relations need the Subject's Schema to determine the
 * Relation Type (the predicate). When the Schema is missing, only relations are skipped; the Subject's
 * type, label and non-relation Statements still project, since those do not depend on the Schema.
 */
class RdfPageProjector {

	private const string PROPERTY_NAME = 'name';
	private const string PROPERTY_CREATION_TIME = 'creationTime';
	private const string PROPERTY_LAST_UPDATED = 'lastUpdated';
	private const string PROPERTY_LAST_EDITOR = 'lastEditor';
	private const string PROPERTY_CATEGORIES = 'categories';

	public function __construct(
		private readonly RdfValueMapperRegistry $valueMappers,
		private readonly RdfNamespaces $namespaces,
		private readonly SchemaLookup $schemaLookup,
		private readonly LoggerInterface $logger,
	) {
	}

	public function projectPage( Page $page ): QuadList {
		$graph = $this->namespaces->page( $page->getId() );

		$quads = $this->projectPageMetadata( $page, $graph );

		foreach ( $page->getSubjects()->getAllSubjects()->asArray() as $subject ) {
			$quads = array_merge( $quads, $this->projectSubject( $subject, $graph, $page->getId() ) );
		}

		return QuadList::fromArray( $quads );
	}

	/**
	 * @return Quad[]
	 */
	private function projectPageMetadata( Page $page, Iri $graph ): array {
		$pageIri = $this->namespaces->page( $page->getId() );
		$properties = $page->getProperties();

		$quads = [ new Quad( $pageIri, $this->namespaces->rdfType(), $this->namespaces->term( RdfNamespaces::CLASS_PAGE ), $graph ) ];

		$name = $properties->get( self::PROPERTY_NAME );
		if ( is_string( $name ) && $name !== '' ) {
			$quads[] = new Quad( $pageIri, $this->namespaces->term( RdfNamespaces::TERM_PAGE_NAME ), RdfLiteralFactory::typed( $name, 'string' ), $graph );
		}

		$created = $this->timestampLiteral( $properties->get( self::PROPERTY_CREATION_TIME ) );
		if ( $created !== null ) {
			$quads[] = new Quad( $pageIri, $this->namespaces->dctermsCreated(), $created, $graph );
		}

		$modified = $this->timestampLiteral( $properties->get( self::PROPERTY_LAST_UPDATED ) );
		if ( $modified !== null ) {
			$quads[] = new Quad( $pageIri, $this->namespaces->dctermsModified(), $modified, $graph );
		}

		$lastEditor = $properties->get( self::PROPERTY_LAST_EDITOR );
		if ( is_string( $lastEditor ) && $lastEditor !== '' ) {
			$quads[] = new Quad( $pageIri, $this->namespaces->term( RdfNamespaces::TERM_LAST_EDITOR ), RdfLiteralFactory::typed( $lastEditor, 'string' ), $graph );
		}

		$categories = $properties->get( self::PROPERTY_CATEGORIES );
		if ( is_array( $categories ) ) {
			foreach ( $categories as $category ) {
				if ( is_string( $category ) ) {
					$quads[] = new Quad( $pageIri, $this->namespaces->term( RdfNamespaces::TERM_CATEGORY ), RdfLiteralFactory::typed( $category, 'string' ), $graph );
				}
			}
		}

		$mainSubject = $page->getSubjects()->getMainSubject();
		if ( $mainSubject !== null ) {
			$quads[] = new Quad( $pageIri, $this->namespaces->term( RdfNamespaces::TERM_MAIN_SUBJECT ), $this->namespaces->subject( $mainSubject->id ), $graph );
		}

		foreach ( $page->getSubjects()->getAllSubjects()->asArray() as $subject ) {
			$quads[] = new Quad( $pageIri, $this->namespaces->term( RdfNamespaces::TERM_HAS_SUBJECT ), $this->namespaces->subject( $subject->id ), $graph );
		}

		return $quads;
	}

	/**
	 * @return Quad[]
	 */
	private function projectSubject( Subject $subject, Iri $graph, PageId $pageId ): array {
		$subjectIri = $this->namespaces->subject( $subject->id );

		$quads = [
			new Quad( $subjectIri, $this->namespaces->rdfType(), $this->namespaces->schemaClass( $subject->getSchemaName() ), $graph ),
			new Quad( $subjectIri, $this->namespaces->rdfsLabel(), RdfLiteralFactory::typed( $subject->label->text, 'string' ), $graph ),
		];

		$quads = array_merge( $quads, $this->projectStatements( $subject, $subjectIri, $graph, $pageId ) );

		return array_merge( $quads, $this->projectRelations( $subject, $subjectIri, $graph ) );
	}

	/**
	 * @return Quad[]
	 */
	private function projectStatements( Subject $subject, Iri $subjectIri, Iri $graph, PageId $pageId ): array {
		$quads = [];

		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$literals = $this->valueMappers->mapValue( $statement->getPropertyType(), $statement->getValue() );

			// null means the Property Type has no RDF mapper (a relation, handled separately, or an
			// unregistered type). Skip it, matching the Neo4j projection's graceful degradation.
			if ( $literals === null ) {
				continue;
			}

			$this->warnOnDroppedValues( $statement, count( $literals ), $pageId );

			$predicate = $this->namespaces->property( $statement->getPropertyName()->text );

			foreach ( $literals as $literal ) {
				$quads[] = new Quad( $subjectIri, $predicate, $literal, $graph );
			}
		}

		return $quads;
	}

	/**
	 * @return Quad[]
	 */
	private function projectRelations( Subject $subject, Iri $subjectIri, Iri $graph ): array {
		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		if ( $schema === null ) {
			$this->logger->warning(
				'Schema not found when projecting relations to RDF: ' . $subject->getSchemaName()->getText()
			);
			return [];
		}

		$quads = [];

		foreach ( $subject->getTypedRelations( $schema )->relations as $relation ) {
			$quads = array_merge( $quads, $this->projectRelation( $relation, $subjectIri, $graph ) );
		}

		return $quads;
	}

	/**
	 * Emits the two layers for one relation: the direct triple for simple queries, and the Relation
	 * node preserving the Relation ID, endpoints, type, and any Relation properties.
	 *
	 * @return Quad[]
	 */
	private function projectRelation( TypedRelation $relation, Iri $subjectIri, Iri $graph ): array {
		$predicate = $this->namespaces->property( $relation->type->text );
		$targetIri = $this->namespaces->subject( $relation->targetId );
		$relationIri = $this->namespaces->relationNode( $relation->id );

		$quads = [
			new Quad( $subjectIri, $predicate, $targetIri, $graph ),
			new Quad( $relationIri, $this->namespaces->rdfType(), $this->namespaces->term( RdfNamespaces::CLASS_RELATION ), $graph ),
			new Quad( $relationIri, $this->namespaces->term( RdfNamespaces::TERM_SOURCE ), $subjectIri, $graph ),
			new Quad( $relationIri, $this->namespaces->term( RdfNamespaces::TERM_TARGET ), $targetIri, $graph ),
			new Quad( $relationIri, $this->namespaces->term( RdfNamespaces::TERM_RELATION_TYPE ), $predicate, $graph ),
		];

		foreach ( $relation->properties->map as $name => $value ) {
			$literal = RdfLiteralFactory::forScalar( $value );

			if ( $literal !== null ) {
				$quads[] = new Quad( $relationIri, $this->namespaces->property( (string)$name ), $literal, $graph );
			}
		}

		return $quads;
	}

	private function warnOnDroppedValues( Statement $statement, int $producedCount, PageId $pageId ): void {
		$scalars = $statement->getValue()->toScalars();
		$expectedCount = is_array( $scalars ) ? count( $scalars ) : 1;

		if ( $producedCount >= $expectedCount ) {
			return;
		}

		$this->logger->warning(
			'Dropped ' . ( $expectedCount - $producedCount ) . ' unrepresentable value(s) of property "'
			. $statement->getPropertyName()->text . '" on page ' . $pageId->id . ' when projecting to RDF'
		);
	}

	private function timestampLiteral( mixed $value ): ?Literal {
		$timestamp = $value instanceof PageValue ? $value->getValue() : $value;

		if ( !is_string( $timestamp ) ) {
			return null;
		}

		$dateTime = DateTimeImmutable::createFromFormat( 'YmdHis', $timestamp, new DateTimeZone( 'UTC' ) );

		if ( $dateTime === false ) {
			return null;
		}

		return RdfLiteralFactory::typed( $dateTime->format( 'Y-m-d\TH:i:s\Z' ), 'dateTime' );
	}

}
