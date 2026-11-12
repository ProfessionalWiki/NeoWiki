<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * The Schemas the caller may read, described in RDF Schema, as Turtle or RDF/XML.
 */
class ExportSchemasRdfsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	private const string FORMAT_TURTLE = 'turtle';
	private const string FORMAT_RDF_XML = 'rdfxml';

	public function run(): Response {
		$extension = NeoWikiExtension::getInstance();
		$quads = $extension->newSchemaRdfsDescriber()->describe( ...$this->readableSchemas( $extension ) );

		[ $contentType, $fileExtension, $document ] = match ( $this->requestedFormat() ) {
			self::FORMAT_RDF_XML => [ 'application/rdf+xml', 'rdf', $extension->newRdfXmlSerializer()->serialize( $quads ) ],
			default => [ 'text/turtle', 'ttl', $extension->getRdfSerializer()->serialize( $quads, RdfFormat::Turtle ) ],
		};

		$response = $this->getResponseFactory()->create();
		$response->setHeader( 'Content-Type', $contentType . '; charset=utf-8' );
		$response->setHeader( 'Content-Disposition', 'inline; filename="schemas.' . $fileExtension . '"' );

		if ( ( $this->getValidatedParams()['format'] ?? null ) === null ) {
			$response->addHeader( 'Vary', 'Accept' );
		}

		$response->setBody( new StringStream( $document ) );
		return $response;
	}

	/**
	 * @return Schema[]
	 */
	private function readableSchemas( NeoWikiExtension $extension ): array {
		$schemaLookup = $extension->getSchemaLookup();
		$schemas = [];

		foreach ( $extension->getSchemaNameLookup()->getReadableSchemaNames() as $title ) {
			try {
				$schemaName = new SchemaName( $title->getText() );
			} catch ( InvalidArgumentException ) {
				// A title that names no Schema, like the reserved "Subject" an import can leave behind.
				continue;
			}

			$schema = $schemaLookup->getSchema( $schemaName );

			if ( $schema !== null ) {
				$schemas[] = $schema;
			}
		}

		return $schemas;
	}

	/**
	 * The `format` parameter wins, then the `Accept` header, where Turtle wins when both are named.
	 */
	private function requestedFormat(): string {
		$format = $this->getValidatedParams()['format'] ?? null;

		if ( $format !== null ) {
			return $format;
		}

		$accept = $this->getRequest()->getHeaderLine( 'Accept' );
		$rdfXmlOnly = str_contains( $accept, 'application/rdf+xml' ) && !str_contains( $accept, 'text/turtle' );

		return $rdfXmlOnly ? self::FORMAT_RDF_XML : self::FORMAT_TURTLE;
	}

	public function getParamSettings(): array {
		return [
			'format' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [ self::FORMAT_TURTLE, self::FORMAT_RDF_XML ],
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'RDF serialization to return: "turtle" (default) or "rdfxml". Overrides the Accept header.',
			],
		];
	}

}
