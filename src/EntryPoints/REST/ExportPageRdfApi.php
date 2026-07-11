<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Exports one page's Subjects and metadata as native RDF (NativeRdfProjection.md). The format is
 * chosen by the `format` query parameter, falling back to the Accept header, then to TriG. TriG keeps
 * the per-page named graph; Turtle emits the same triples without it.
 */
class ExportPageRdfApi extends SimpleHandler {

	private const string FORMAT_TRIG = 'trig';
	private const string FORMAT_TURTLE = 'turtle';

	private const string CONTENT_TYPE_TRIG = 'application/trig; charset=utf-8';
	private const string CONTENT_TYPE_TURTLE = 'text/turtle; charset=utf-8';

	public function run( int $pageId ): Response {
		$format = $this->resolveFormat();

		$document = NeoWikiExtension::getInstance()
			->newRdfPageExporter()
			->exportByPageId( new PageId( $pageId ), $format );

		if ( $document === null ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => 'No NeoWiki data found for page: ' . $pageId,
			] );
		}

		return $this->rdfResponse( $document, $format );
	}

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

	private function rdfResponse( string $document, RdfFormat $format ): Response {
		$response = $this->getResponseFactory()->create();
		$response->setHeader(
			'Content-Type',
			$format === RdfFormat::Turtle ? self::CONTENT_TYPE_TURTLE : self::CONTENT_TYPE_TRIG
		);
		$response->setBody( new StringStream( $document ) );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
			'format' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::FORMAT_TRIG,
					self::FORMAT_TURTLE,
				],
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'RDF serialization to return: "trig" (default, includes the per-page named graph) or "turtle". Overrides the Accept header.',
			],
		];
	}

}
