<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure\Rdf;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfStreamWriter;
use Psr\Log\LoggerInterface;

readonly class HardfRdfSerializer implements RdfSerializer {

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI, used for abbreviation.
	 * @param Closure(QuadList): array<array-key, string> $prefixesForQuads
	 */
	private function __construct(
		private array $prefixes,
		private Closure $prefixesForQuads,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Abbreviates with the namespaces' prefixes, and with a prefix per Schema whose predicates a document uses.
	 */
	public static function withNamespaces( RdfNamespaces $namespaces, LoggerInterface $logger ): self {
		return new self( $namespaces->prefixMap(), $namespaces->propertyPrefixes( ... ), $logger );
	}

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI, used for abbreviation.
	 */
	public static function withPrefixes( array $prefixes, LoggerInterface $logger ): self {
		return new self( $prefixes, static fn (): array => [], $logger );
	}

	public function serialize( QuadList $quads, RdfFormat $format ): string {
		$writer = $this->newWriter( $format );

		return $writer->write( $quads ) . $writer->finish();
	}

	public function newWriter( RdfFormat $format ): RdfStreamWriter {
		return new HardfRdfStreamWriter( $this->prefixes, $this->prefixesForQuads, $format, $this->logger );
	}

}
