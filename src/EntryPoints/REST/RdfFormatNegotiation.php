<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;

/**
 * Shared RDF serialization negotiation for the RDF export handlers ({@see ExportPageRdfApi},
 * {@see ExportSubjectRdfApi}): the `format` query parameter wins, then the `Accept` header, then TriG.
 * TriG keeps the named graph; Turtle emits the same triples without it. Kept in one place so the two
 * endpoints stay byte-for-byte consistent in what they accept and in the Content-Type and download
 * filename they return. The filename stem callers pass (a page ID or validated Subject ID) must be
 * header-safe without escaping. The disposition is `inline` rather than `attachment` so Turtle stays
 * viewable in the browser — the sidebar "View RDF" link depends on that — while TriG, which browsers
 * do not render, downloads under the stem plus the format's own extension.
 *
 * Used only by {@see \MediaWiki\Rest\SimpleHandler} subclasses, whose request/response accessors it
 * calls.
 */
trait RdfFormatNegotiation {

	private const string FORMAT_TRIG = 'trig';
	private const string FORMAT_TURTLE = 'turtle';

	private const string CONTENT_TYPE_TRIG = 'application/trig; charset=utf-8';
	private const string CONTENT_TYPE_TURTLE = 'text/turtle; charset=utf-8';

	private const string FILE_EXTENSION_TRIG = 'trig';
	private const string FILE_EXTENSION_TURTLE = 'ttl';

	private function resolveFormat(): RdfFormat {
		$requested = $this->getValidatedParams()['format'] ?? null;

		if ( $requested === self::FORMAT_TURTLE ) {
			return RdfFormat::Turtle;
		}

		if ( $requested === self::FORMAT_TRIG ) {
			return RdfFormat::TriG;
		}

		return $this->formatFromAcceptHeader();
	}

	private function formatFromAcceptHeader(): RdfFormat {
		$accept = $this->getRequest()->getHeaderLine( 'Accept' );

		// TriG is a superset of Turtle, so only pick Turtle when the client asks for it specifically.
		if ( str_contains( $accept, 'text/turtle' ) && !str_contains( $accept, 'application/trig' ) ) {
			return RdfFormat::Turtle;
		}

		return RdfFormat::TriG;
	}

	private function rdfResponse( string $document, RdfFormat $format, string $filenameStem ): Response {
		$response = $this->getResponseFactory()->create();
		$response->setHeader(
			'Content-Type',
			$format === RdfFormat::Turtle ? self::CONTENT_TYPE_TURTLE : self::CONTENT_TYPE_TRIG
		);
		$response->setHeader(
			'Content-Disposition',
			'inline; filename="' . $filenameStem . '.' . $this->fileExtension( $format ) . '"'
		);
		$response->setBody( new StringStream( $document ) );

		return $response;
	}

	private function fileExtension( RdfFormat $format ): string {
		return $format === RdfFormat::Turtle ? self::FILE_EXTENSION_TURTLE : self::FILE_EXTENSION_TRIG;
	}

}
