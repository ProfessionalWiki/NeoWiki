<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfTerm;
use Psr\Log\LoggerInterface;

/**
 * Writes RDF/XML. A resource with a type becomes an element named after that type, such as `rdf:Property`, and
 * any other resource an `rdf:Description`, each holding one property element per further triple. The document
 * declares every namespace of the wiki's vocabulary, the property namespace of each Schema whose properties it
 * holds included, since tools such as Pletka refuse terms from a namespace the document does not declare. RDF/XML
 * has no graphs, so they are dropped.
 *
 * A triple is left out, and logged, when XML cannot hold it: its predicate is not an XML name in a declared
 * namespace, one of its IRIs is not absolute, or one of its terms holds a character XML 1.0 forbids.
 */
class RdfXmlSerializer {

	private const string XML_NAME = '/^[A-Za-z_][A-Za-z0-9._-]*$/D';
	private const string XML_CHARACTERS = '/^[\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*$/Du';

	/**
	 * @var array<string, bool> Whether XML can hold each IRI of the current document. A resource repeats its IRIs
	 *   across its triples, and the vocabulary's IRIs recur throughout.
	 */
	private array $writableIris = [];

	public function __construct(
		private readonly RdfNamespaces $namespaces,
		private readonly LoggerInterface $logger,
	) {
	}

	public function serialize( QuadList $quads ): string {
		$this->writableIris = [];
		$prefixes = $this->prefixesFor( $quads );
		$nodeNames = [];
		$propertyElements = [];

		foreach ( $quads->asArray() as $quad ) {
			$predicateName = $this->qualifiedName( $quad->predicate, $prefixes );

			if ( $predicateName === null || !$this->xmlCanHold( $quad ) ) {
				$this->logger->warning( 'Not writing a triple about "' . $quad->subject->value . '" to RDF/XML: XML cannot hold it' );
				continue;
			}

			$subject = $quad->subject->value;
			$typeName = $this->typeName( $quad, $prefixes );

			if ( $typeName !== null && !array_key_exists( $subject, $nodeNames ) ) {
				$nodeNames[$subject] = $typeName;
				$propertyElements[$subject] ??= [];
				continue;
			}

			$propertyElements[$subject][] = $this->propertyElement( $predicateName, $quad->object );
		}

		$document = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<rdf:RDF" . $this->namespaceDeclarations( $prefixes ) . ">\n";

		// Each subject's elements are released once written, so a large document is not held twice.
		foreach ( array_keys( $propertyElements ) as $subject ) {
			$nodeName = $nodeNames[$subject] ?? 'rdf:Description';
			$document .= "\t<$nodeName rdf:about=\"" . self::escape( (string)$subject ) . "\">\n"
				. implode( '', $propertyElements[$subject] )
				. "\t</$nodeName>\n";
			unset( $propertyElements[$subject] );
		}

		return $document . "</rdf:RDF>\n";
	}

	/**
	 * The wiki's namespaces and one per Schema whose properties the quads hold. A Schema name that is no XML name,
	 * or is taken, gets a generated label.
	 *
	 * @return array<string, string> Prefix label to namespace IRI, each label an XML name.
	 */
	private function prefixesFor( QuadList $quads ): array {
		$prefixes = $this->namespaces->prefixMap();
		$generated = 0;

		foreach ( $this->namespaces->propertyPrefixes( $quads ) as $label => $namespace ) {
			$label = (string)$label;

			while ( preg_match( self::XML_NAME, $label ) !== 1 || array_key_exists( $label, $prefixes ) ) {
				$label = 'ns' . ++$generated;
			}

			$prefixes[$label] = $namespace;
		}

		return $prefixes;
	}

	/**
	 * @param array<string, string> $prefixes
	 */
	private function qualifiedName( Iri $iri, array $prefixes ): ?string {
		foreach ( $prefixes as $label => $namespace ) {
			if ( str_starts_with( $iri->value, $namespace ) ) {
				$localName = substr( $iri->value, strlen( $namespace ) );

				if ( preg_match( self::XML_NAME, $localName ) === 1 ) {
					return $label . ':' . $localName;
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string, string> $prefixes
	 */
	private function typeName( Quad $quad, array $prefixes ): ?string {
		if ( $quad->predicate->value !== RdfNamespaces::RDF . 'type' || !$quad->object instanceof Iri ) {
			return null;
		}

		return $this->qualifiedName( $quad->object, $prefixes );
	}

	private function xmlCanHold( Quad $quad ): bool {
		$object = $quad->object;

		foreach ( [ $quad->subject, $object instanceof Literal ? $object->datatype : $object ] as $iri ) {
			/** @var Iri $iri */
			$this->writableIris[$iri->value] ??= Iri::isSafeAbsolute( $iri->value ) && self::xmlCanHoldText( $iri->value );

			if ( !$this->writableIris[$iri->value] ) {
				return false;
			}
		}

		return !$object instanceof Literal || self::xmlCanHoldText( $object->lexicalForm );
	}

	private static function xmlCanHoldText( string $text ): bool {
		return preg_match( self::XML_CHARACTERS, $text ) === 1;
	}

	private function propertyElement( string $name, RdfTerm $object ): string {
		if ( $object instanceof Iri ) {
			return "\t\t<$name rdf:resource=\"" . self::escape( $object->value ) . "\"/>\n";
		}

		/** @var Literal $object */
		return "\t\t<$name" . $this->literalAttributes( $object ) . '>' . self::escape( $object->lexicalForm ) . "</$name>\n";
	}

	private function literalAttributes( Literal $literal ): string {
		if ( $literal->languageTag !== null ) {
			return ' xml:lang="' . self::escape( $literal->languageTag ) . '"';
		}

		if ( $literal->datatype->value === RdfNamespaces::XSD . 'string' ) {
			return '';
		}

		return ' rdf:datatype="' . self::escape( $literal->datatype->value ) . '"';
	}

	/**
	 * @param array<string, string> $prefixes
	 */
	private function namespaceDeclarations( array $prefixes ): string {
		$declarations = '';

		foreach ( $prefixes as $label => $namespace ) {
			$declarations .= "\n\txmlns:$label=\"" . self::escape( $namespace ) . '"';
		}

		return $declarations;
	}

	/**
	 * An XML parser turns a raw carriage return into a line feed, and raw whitespace in an attribute value into
	 * spaces, so those are written as references.
	 */
	private static function escape( string $text ): string {
		return str_replace(
			[ "\t", "\n", "\r" ],
			[ '&#9;', '&#10;', '&#13;' ],
			htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' )
		);
	}

}
