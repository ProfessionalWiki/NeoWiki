<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure\Rdf;

use Closure;
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

	private const string NAME_PATTERN = '[A-Za-z][A-Za-z0-9_-]*';

	/**
	 * hardf matches every IRI against one regex built from all declared prefixes, which PCRE refuses to compile
	 * once their labels and namespaces add up to about 30,000 characters.
	 */
	private const int MAX_PREFIX_TABLE_LENGTH = 16_000;

	private TriGWriter $writer;
	private bool $includeGraph;

	/**
	 * @var array<string, string> Prefix label to namespace IRI, as declared to hardf.
	 */
	private array $prefixes = [];

	private int $prefixTableLength = 0;

	/**
	 * @var string[]
	 */
	private readonly array $schemes;

	/**
	 * @var array<string, bool> Whether each IRI of the current write reads as itself. The answers last one
	 *   write: a later write can declare a prefix that changes how an IRI is written.
	 */
	private array $checkedIris = [];

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI.
	 * @param Closure(QuadList): array<array-key, string> $prefixesForQuads
	 */
	public function __construct(
		array $prefixes,
		private readonly Closure $prefixesForQuads,
		RdfFormat $format,
		private readonly LoggerInterface $logger,
	) {
		$this->schemes = self::schemesOf( $prefixes );
		$this->includeGraph = $format === RdfFormat::TriG;
		$this->writer = new TriGWriter( [
			'prefixes' => $this->declarable( $prefixes ),
			'format' => $this->includeGraph ? 'trig' : 'turtle',
		] );
	}

	/**
	 * The schemes the document's IRIs can have: those of the namespaces the writer starts with, and http and
	 * https, which `url` values have too.
	 *
	 * @param array<string, string> $prefixes
	 * @return string[]
	 */
	private static function schemesOf( array $prefixes ): array {
		return array_merge(
			[ 'http', 'https' ],
			array_map(
				static fn ( string $namespace ): string => strtolower( (string)strstr( $namespace, ':', true ) ),
				array_values( $prefixes )
			)
		);
	}

	public function write( QuadList $quads ): string {
		$this->checkedIris = [];
		$this->writer->addPrefixes( $this->declarable( ( $this->prefixesForQuads )( $quads ), $this->schemesIn( $quads ) ) );

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
	 * @return string[] The schemes of the IRIs the quads hold, lowercased.
	 */
	private function schemesIn( QuadList $quads ): array {
		$schemes = [];

		foreach ( $quads->asArray() as $quad ) {
			foreach ( $this->writtenIris( $quad ) as $iri ) {
				$schemes[strtolower( (string)strstr( $iri->value, ':', true ) )] = true;
			}
		}

		return array_keys( $schemes );
	}

	/**
	 * @param array<array-key, string> $prefixes
	 * @param string[] $schemes Schemes of the IRIs about to be written, besides those the document always has.
	 * @return array<string, string> The prefixes to declare, now recorded as declared.
	 */
	private function declarable( array $prefixes, array $schemes = [] ): array {
		$declarable = [];

		foreach ( $prefixes as $label => $namespace ) {
			if ( $this->canDeclare( (string)$label, $namespace, $schemes ) ) {
				$declarable[(string)$label] = $namespace;
				$this->prefixes[(string)$label] = $namespace;
				$this->prefixTableLength += strlen( (string)$label ) + strlen( $namespace );
			}
		}

		return $declarable;
	}

	/**
	 * A prefix left undeclared has its IRIs written in full. hardf would write every IRI whose scheme is the
	 * label raw.
	 *
	 * @param string[] $schemes
	 */
	private function canDeclare( string $label, string $namespace, array $schemes ): bool {
		return !array_key_exists( $label, $this->prefixes )
			&& preg_match( '/^' . self::NAME_PATTERN . '$/', $label ) === 1
			&& !in_array( strtolower( $label ), [ ...$this->schemes, ...$schemes ], true )
			&& $this->prefixTableLength + strlen( $label ) + strlen( $namespace ) <= self::MAX_PREFIX_TABLE_LENGTH;
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
		if ( preg_match( '/^(' . self::NAME_PATTERN . '):(' . self::NAME_PATTERN . ')$/', $term, $parts ) !== 1 ) {
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
