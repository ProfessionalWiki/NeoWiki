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
use Psr\Log\LoggerInterface;

/**
 * {@see RdfStreamWriter} backed by hardf's TriGWriter. This is the only place that knows hardf's
 * string term representation; the rest of the codebase deals in {@see \ProfessionalWiki\NeoWiki\Domain\Rdf}
 * value objects.
 *
 * A quad is written only when hardf writes each of its IRIs as that IRI: between angle brackets, or as a
 * prefixed name that expands to it. hardf writes an IRI raw when its text up to the first colon is a
 * declared prefix label, and abbreviates through a pattern that also matches IRIs merely resembling a
 * declared namespace, so a quad holding such an IRI is left out and logged instead.
 *
 * For Turtle the graph is dropped, so the same grouped writer emits plain triples.
 */
class HardfRdfStreamWriter implements RdfStreamWriter {

	private TriGWriter $writer;
	private bool $includeGraph;

	/**
	 * @var array<string, string> Prefix label to namespace IRI, as declared to hardf.
	 */
	private readonly array $prefixes;

	/**
	 * @var array<string, bool> Whether each IRI of the current write reads as itself. A page repeats its IRIs
	 *   while a dump keeps meeting new ones, so the answers last one write.
	 */
	private array $checkedIris = [];

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI.
	 */
	public function __construct(
		array $prefixes,
		RdfFormat $format,
		private readonly LoggerInterface $logger,
	) {
		$this->prefixes = self::withoutSchemeLabels( $prefixes );
		$this->includeGraph = $format === RdfFormat::TriG;
		$this->writer = new TriGWriter( [
			'prefixes' => $this->prefixes,
			'format' => $this->includeGraph ? 'trig' : 'turtle',
		] );
	}

	/**
	 * Leaves out a prefix labelled with a scheme the document's IRIs have: that of a declared namespace, or
	 * http and https, which `url` values have too. hardf would write every IRI of that scheme raw, while
	 * undeclared they come out in full.
	 *
	 * @param array<string, string> $prefixes
	 * @return array<string, string>
	 */
	private static function withoutSchemeLabels( array $prefixes ): array {
		$schemes = array_merge(
			[ 'http', 'https' ],
			array_map(
				static fn ( string $namespace ): string => strtolower( (string)strstr( $namespace, ':', true ) ),
				array_values( $prefixes )
			)
		);

		return array_filter(
			$prefixes,
			static fn ( int|string $label ): bool => !in_array( strtolower( (string)$label ), $schemes, true ),
			ARRAY_FILTER_USE_KEY
		);
	}

	public function write( QuadList $quads ): string {
		$this->checkedIris = [];

		foreach ( $this->groupedByResource( $this->writableQuads( $quads ) ) as $quad ) {
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
	 * @return Quad[]
	 */
	private function writableQuads( QuadList $quads ): array {
		$writable = [];

		foreach ( $quads->asArray() as $quad ) {
			$misreadIri = $this->firstMisreadIri( $quad );

			if ( $misreadIri === null ) {
				$writable[] = $quad;
			}
			else {
				$this->logger->warning( 'Not writing a triple to RDF: its IRI "' . $misreadIri->value . '" would read as another term' );
			}
		}

		return $writable;
	}

	private function firstMisreadIri( Quad $quad ): ?Iri {
		foreach ( $this->writtenIris( $quad ) as $iri ) {
			$this->checkedIris[$iri->value] ??= $this->readsAsItself( $iri );

			if ( !$this->checkedIris[$iri->value] ) {
				return $iri;
			}
		}

		return null;
	}

	/**
	 * @return Iri[]
	 */
	private function writtenIris( Quad $quad ): array {
		return array_filter( [
			$quad->subject,
			$quad->predicate,
			$this->includeGraph ? $quad->graph : null,
			$quad->object instanceof Iri ? $quad->object : null,
			$quad->object instanceof Literal ? $this->writtenDatatype( $quad->object ) : null,
		] );
	}

	private function readsAsItself( Iri $iri ): bool {
		if ( !Iri::isSafeAbsolute( $iri->value ) ) {
			return false;
		}

		$written = $this->writtenForm( $iri );

		return $written === '<' . $iri->value . '>' || $this->expandPrefixedName( $written ) === $iri->value;
	}

	/**
	 * hardf's addList() returns how it writes each element, and writes nothing. It raises a warning while
	 * abbreviating an IRI that merely resembles a declared namespace, which the written form shows anyway.
	 */
	private function writtenForm( Iri $iri ): string {
		set_error_handler( static fn (): bool => true, E_WARNING );

		try {
			return substr( $this->writer->addList( [ $iri->value ] ), 1, -1 );
		}
		finally {
			restore_error_handler();
		}
	}

	private function expandPrefixedName( string $term ): ?string {
		if ( preg_match( '/^([A-Za-z][A-Za-z0-9_-]*):([A-Za-z][A-Za-z0-9_-]*)$/', $term, $parts ) !== 1 ) {
			return null;
		}

		return array_key_exists( $parts[1], $this->prefixes ) ? $this->prefixes[$parts[1]] . $parts[2] : null;
	}

	/**
	 * hardf merges only consecutive triples that share a subject into one block.
	 *
	 * @param Quad[] $quads
	 * @return iterable<Quad>
	 */
	private function groupedByResource( array $quads ): iterable {
		$blocks = [];

		foreach ( $quads as $quad ) {
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

		$datatype = $this->writtenDatatype( $literal );

		if ( $datatype === null ) {
			return '"' . $literal->lexicalForm . '"';
		}

		return '"' . $literal->lexicalForm . '"^^' . $datatype->value;
	}

	/**
	 * xsd:string is RDF's default literal type, so its literals take the canonical bare form.
	 */
	private function writtenDatatype( Literal $literal ): ?Iri {
		if ( $literal->isLanguageTagged() || $literal->datatype->value === RdfNamespaces::XSD . 'string' ) {
			return null;
		}

		return $literal->datatype;
	}

}
