<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use DOMDocument;
use DOMElement;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use RuntimeException;

/**
 * Test helper: reads RDF/XML whose node elements, typed or `rdf:Description`, sit directly under `rdf:RDF`
 * and hold property elements, into the canonical lines of {@see ParsedRdf}, so a document can be compared with
 * one written in Turtle.
 */
final class ParsedRdfXml {

	private const string RDF = RdfNamespaces::RDF;
	private const string XML = 'http://www.w3.org/XML/1998/namespace';
	private const string XSD_STRING = RdfNamespaces::XSD . 'string';

	/**
	 * @return list<string> Sorted "subject \t predicate \t object \t graph" lines, one per triple.
	 */
	public static function canonicalTriples( string $rdfXml ): array {
		$document = new DOMDocument();

		if ( !$document->loadXML( $rdfXml ) ) {
			throw new RuntimeException( 'Not well-formed XML' );
		}

		$lines = [];

		foreach ( $document->documentElement->childNodes as $node ) {
			if ( !$node instanceof DOMElement ) {
				continue;
			}

			$subject = $node->getAttributeNS( self::RDF, 'about' );

			if ( $node->namespaceURI . $node->localName !== self::RDF . 'Description' ) {
				$lines[] = implode( "\t", [ $subject, self::RDF . 'type', $node->namespaceURI . $node->localName, '' ] );
			}

			foreach ( $node->childNodes as $property ) {
				if ( $property instanceof DOMElement ) {
					$lines[] = implode( "\t", [ $subject, $property->namespaceURI . $property->localName, self::object( $property ), '' ] );
				}
			}
		}

		sort( $lines );
		return $lines;
	}

	private static function object( DOMElement $property ): string {
		if ( $property->hasAttributeNS( self::RDF, 'resource' ) ) {
			return $property->getAttributeNS( self::RDF, 'resource' );
		}

		if ( $property->hasAttributeNS( self::XML, 'lang' ) ) {
			return '"' . $property->textContent . '"@' . strtolower( $property->getAttributeNS( self::XML, 'lang' ) );
		}

		if ( $property->hasAttributeNS( self::RDF, 'datatype' ) && $property->getAttributeNS( self::RDF, 'datatype' ) !== self::XSD_STRING ) {
			return '"' . $property->textContent . '"^^' . $property->getAttributeNS( self::RDF, 'datatype' );
		}

		return '"' . $property->textContent . '"';
	}

}
