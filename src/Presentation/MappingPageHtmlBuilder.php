<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use Closure;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use stdClass;

/**
 * Builds the server-rendered read view of a Mapping page: a header with a mapped-schemas overview and a
 * prefixes table, followed by one section per schema whose body stays the core mw-json table. The schema
 * links and prefix IRIs are collected so the handler can register them in the ParserOutput link tables.
 */
class MappingPageHtmlBuilder {

	private const string SECTION_ID_PREFIX = 'ext-neowiki-mapping-schema-';

	/** @var list<LinkTarget> */
	private array $schemaLinks = [];

	/** @var list<string> */
	private array $externalLinks = [];

	/** @var array<string, Title> Schema name => its Schema page, for names that form a valid title. */
	private array $schemaTitles = [];

	/**
	 * @param LinkTarget $pageTitle The Mapping page being rendered, for title-specific external link attributes.
	 * @param Closure(mixed): string $renderJsonTable Renders a JSON subtree as the core mw-json table.
	 */
	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly TitleFactory $titleFactory,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly LinkTarget $pageTitle,
		private readonly Closure $renderJsonTable
	) {
	}

	public function build( stdClass $mapping ): MappingPageRendering {
		$this->schemaLinks = [];
		$this->externalLinks = [];

		$schemas = $mapping->schemas;

		$this->schemaTitles = $this->resolveSchemaTitles( $schemas );

		$html = Html::rawElement(
			'div',
			[ 'class' => 'ext-neowiki-mapping-page' ],
			$this->versionHtml( $mapping )
				. $this->schemasOverviewHtml( $schemas )
				. $this->prefixesHtml( $mapping->prefixes ?? null )
				. $this->schemaSectionsHtml( $schemas )
		);

		return new MappingPageRendering( $html, $this->schemaLinks, $this->externalLinks );
	}

	/**
	 * Resolves every schema name to its Schema page up front and primes the LinkCache with a single
	 * existence query. Without this, both LinkRenderer::makeLink (red or blue?) and ParserOutput::addLink
	 * (which page id?) do their own lookup per schema, and the schemas list is unbounded user input.
	 *
	 * @return array<string, Title>
	 */
	private function resolveSchemaTitles( stdClass $schemas ): array {
		$titles = [];

		foreach ( get_object_vars( $schemas ) as $name => $schema ) {
			$title = $this->titleFactory->makeTitleSafe( NeoWikiExtension::NS_SCHEMA, (string)$name );

			if ( $title !== null ) {
				$titles[(string)$name] = $title;
			}
		}

		$this->linkBatchFactory->newLinkBatch( $titles )->setCaller( __METHOD__ )->execute();

		return $titles;
	}

	private function versionHtml( stdClass $mapping ): string {
		return Html::element(
			'div',
			[ 'class' => 'ext-neowiki-mapping-page__version' ],
			wfMessage( 'neowiki-mapping-page-version' )->numParams( $mapping->version )->inContentLanguage()->text()
		);
	}

	private function schemasOverviewHtml( stdClass $schemas ): string {
		if ( get_object_vars( $schemas ) === [] ) {
			return $this->emptyStateHtml();
		}

		return $this->headingHtml( 'neowiki-mapping-page-schemas-heading' )
			. Html::rawElement(
				'table',
				[ 'class' => 'wikitable ext-neowiki-mapping-page__schemas' ],
				$this->overviewHeadHtml() . Html::rawElement( 'tbody', [], $this->overviewRowsHtml( $schemas ) )
			);
	}

	private function overviewHeadHtml(): string {
		return Html::rawElement( 'thead', [], Html::rawElement(
			'tr',
			[],
			$this->headerCellHtml( 'neowiki-mapping-page-schema-column' )
				. $this->headerCellHtml( 'neowiki-mapping-page-class-column' )
				. $this->headerCellHtml( 'neowiki-mapping-page-properties-column' )
		) );
	}

	private function overviewRowsHtml( stdClass $schemas ): string {
		$rows = '';

		foreach ( get_object_vars( $schemas ) as $name => $schema ) {
			$rows .= $this->overviewRowHtml( (string)$name, $schema );
		}

		return $rows;
	}

	private function overviewRowHtml( string $name, mixed $schema ): string {
		return Html::rawElement(
			'tr',
			[],
			Html::rawElement( 'td', [], $this->schemaLinkHtml( $name ) )
				. Html::element( 'td', [], $this->targetClass( $schema ) )
				. Html::element( 'td', [], (string)$this->propertyCount( $schema ) )
		);
	}

	private function schemaLinkHtml( string $name ): string {
		$title = $this->schemaTitles[$name] ?? null;

		if ( $title === null ) {
			return Html::element( 'span', [], $name );
		}

		$this->schemaLinks[] = $title;

		return $this->linkRenderer->makeLink( $title, $name );
	}

	private function targetClass( mixed $schema ): string {
		$subject = $schema instanceof stdClass ? ( $schema->subject ?? null ) : null;

		if ( $subject instanceof stdClass && isset( $subject->class ) && is_string( $subject->class ) ) {
			return $subject->class;
		}

		return '';
	}

	private function propertyCount( mixed $schema ): int {
		$properties = $schema instanceof stdClass ? ( $schema->properties ?? null ) : null;

		return $properties instanceof stdClass ? count( get_object_vars( $properties ) ) : 0;
	}

	private function prefixesHtml( mixed $prefixes ): string {
		if ( !$prefixes instanceof stdClass ) {
			return '';
		}

		$rows = $this->prefixRowsHtml( $prefixes );

		if ( $rows === '' ) {
			return '';
		}

		return $this->headingHtml( 'neowiki-mapping-page-prefixes-heading' )
			. Html::rawElement(
				'table',
				[ 'class' => 'wikitable ext-neowiki-mapping-page__prefixes' ],
				$this->prefixHeadHtml() . Html::rawElement( 'tbody', [], $rows )
			);
	}

	private function prefixHeadHtml(): string {
		return Html::rawElement( 'thead', [], Html::rawElement(
			'tr',
			[],
			$this->headerCellHtml( 'neowiki-mapping-page-prefix-column' )
				. $this->headerCellHtml( 'neowiki-mapping-page-namespace-column' )
		) );
	}

	private function prefixRowsHtml( stdClass $prefixes ): string {
		$rows = '';

		foreach ( get_object_vars( $prefixes ) as $prefix => $iri ) {
			if ( is_string( $iri ) ) {
				$rows .= $this->prefixRowHtml( (string)$prefix, $iri );
			}
		}

		return $rows;
	}

	private function prefixRowHtml( string $prefix, string $iri ): string {
		return Html::rawElement(
			'tr',
			[],
			Html::element( 'td', [], $prefix )
				. Html::rawElement( 'td', [], $this->iriHtml( $iri ) )
		);
	}

	private function iriHtml( string $iri ): string {
		if ( !$this->isLinkableUrl( $iri ) ) {
			return Html::element( 'span', [], $iri );
		}

		$this->externalLinks[] = $iri;

		return $this->linkRenderer->makeExternalLink( $iri, $iri, $this->pageTitle );
	}

	/**
	 * Namespace IRIs are arbitrary strings as far as the mapping format is concerned, and
	 * LinkRenderer::makeExternalLink does no scheme validation, so anything that is not plainly a web
	 * address stays unlinked rather than becoming an href.
	 */
	private function isLinkableUrl( string $url ): bool {
		return preg_match( '#^(?:https?|ftps?)://#i', $url ) === 1;
	}

	private function schemaSectionsHtml( stdClass $schemas ): string {
		$sections = '';

		foreach ( get_object_vars( $schemas ) as $name => $schema ) {
			$sections .= $this->schemaSectionHtml( (string)$name, $schema );
		}

		return $sections;
	}

	private function schemaSectionHtml( string $name, mixed $schema ): string {
		$heading = Html::element(
			'h2',
			[ 'id' => Sanitizer::escapeIdForAttribute( self::SECTION_ID_PREFIX . $name ) ],
			$name
		);

		return $heading . ( $this->renderJsonTable )( $schema );
	}

	private function emptyStateHtml(): string {
		return Html::element(
			'p',
			[ 'class' => 'ext-neowiki-mapping-page__empty' ],
			wfMessage( 'neowiki-mapping-page-no-schemas' )->inContentLanguage()->text()
		);
	}

	private function headingHtml( string $messageKey ): string {
		return Html::element( 'h2', [], wfMessage( $messageKey )->inContentLanguage()->text() );
	}

	private function headerCellHtml( string $messageKey ): string {
		return Html::element( 'th', [], wfMessage( $messageKey )->inContentLanguage()->text() );
	}

}
