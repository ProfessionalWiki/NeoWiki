<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure\Rdf;

use pietercolpaert\hardf\TriGWriter;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfStreamWriter;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfTerm;

/**
 * {@see RdfStreamWriter} backed by hardf's TriGWriter. This is the only place that knows hardf's
 * string term representation; the rest of the codebase deals in {@see \ProfessionalWiki\NeoWiki\Domain\Rdf}
 * value objects.
 *
 * For Turtle the graph is dropped, so the same grouped writer emits plain triples.
 */
class HardfRdfStreamWriter implements RdfStreamWriter {

	private TriGWriter $writer;
	private bool $includeGraph;

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI.
	 */
	public function __construct( array $prefixes, RdfFormat $format ) {
		$this->includeGraph = $format === RdfFormat::TriG;
		$this->writer = new TriGWriter( [
			'prefixes' => $prefixes,
			'format' => $this->includeGraph ? 'trig' : 'turtle',
		] );
	}

	public function write( QuadList $quads ): string {
		foreach ( $this->groupedByResource( $quads ) as $quad ) {
			$this->writer->addTriple(
				$quad->subject->value,
				$quad->predicate->value,
				$this->encodeObject( $quad->object ),
				$this->includeGraph ? $quad->graph->value : null,
			);
		}

		return $this->writer->read();
	}

	/**
	 * hardf merges only consecutive triples that share a subject into one block.
	 *
	 * @return iterable<Quad>
	 */
	private function groupedByResource( QuadList $quads ): iterable {
		$blocks = [];

		foreach ( $quads->asArray() as $quad ) {
			$blocks[$quad->subject->value][] = $quad;
		}

		foreach ( $blocks as $block ) {
			yield from $this->typesFirst( $block );
		}
	}

	/**
	 * @param Quad[] $block
	 * @return Quad[]
	 */
	private function typesFirst( array $block ): array {
		$types = array_filter( $block, $this->isTypeTriple( ... ) );

		return array_merge( $types, array_diff_key( $block, $types ) );
	}

	private function isTypeTriple( Quad $quad ): bool {
		return $quad->predicate->value === RdfNamespaces::RDF . 'type';
	}

	public function finish(): string {
		// end() returns null only when a read callback is set, which this adapter never does.
		return $this->writer->end() ?? '';
	}

	private function encodeObject( RdfTerm $term ): string {
		if ( $term instanceof Iri ) {
			return $term->value;
		}

		/** @var Literal $term */
		return $this->encodeLiteral( $term );
	}

	private function encodeLiteral( Literal $literal ): string {
		if ( $literal->languageTag !== null ) {
			return '"' . $literal->lexicalForm . '"@' . $literal->languageTag;
		}

		// xsd:string is RDF's default literal type, so emit the canonical bare form.
		if ( $literal->datatype->value === RdfNamespaces::XSD . 'string' ) {
			return '"' . $literal->lexicalForm . '"';
		}

		return '"' . $literal->lexicalForm . '"^^' . $literal->datatype->value;
	}

}
