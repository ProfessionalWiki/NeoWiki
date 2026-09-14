<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Only the parsed page's own Subjects are resolved at parse time: they live in its revision, so it
 * re-parses when they change. Another page's Subjects change without this page being edited, so the
 * dialog reads those live.
 */
class CreateSubjectParserFunction {

	private const string ARG_SCHEMA = 'schema';
	private const string ARG_PAGE = 'page';
	private const string ARG_TEXT = 'text';

	private const string PAGE_THIS = 'this';
	private const string PAGE_NEW = 'new';

	public function __construct(
		private readonly PageSubjectsLookup $pageSubjectsLookup
	) {
	}

	/**
	 * @return string|array{0: string, noparse: true, isHTML: true}
	 */
	public function handle( Parser $parser, string ...$args ): string|array {
		$named = $this->classifyArgs( $parser, $args );

		if ( is_string( $named ) ) {
			return $named;
		}

		$attributes = $this->buildAttributes( $parser, $named );

		if ( is_string( $attributes ) ) {
			return $attributes;
		}

		$this->loadFrontend( $parser );
		$this->recordPageIdDependency( $parser );

		return [
			Html::element( 'div', $attributes ),
			'noparse' => true,
			'isHTML' => true,
		];
	}

	/**
	 * @param string[] $args
	 * @return array<string, string>|string
	 */
	private function classifyArgs( Parser $parser, array $args ): array|string {
		$named = [];

		foreach ( $args as $arg ) {
			// {{#create_subject:}} arrives as one empty argument, and a stray pipe as another.
			if ( trim( $arg ) === '' ) {
				continue;
			}

			if ( !str_contains( $arg, '=' ) ) {
				return $this->renderError( $parser, 'neowiki-create-subject-error-positional', trim( $arg ) );
			}

			[ $key, $value ] = explode( '=', $arg, 2 );
			$key = trim( $key );

			if ( !in_array( $key, [ self::ARG_SCHEMA, self::ARG_PAGE, self::ARG_TEXT ], true ) ) {
				return $this->renderError(
					$parser,
					'neowiki-create-subject-error-unknown-arg',
					$key !== '' ? $key : $arg
				);
			}

			$value = trim( $value );

			if ( $value !== '' ) {
				$named[$key] = $value;
			}
		}

		return $named;
	}

	/**
	 * @param array<string, string> $named
	 * @return array<string, string>|string
	 */
	private function buildAttributes( Parser $parser, array $named ): array|string {
		$attributes = [ 'class' => 'ext-neowiki-create-subject-button' ];

		$schemaName = $named[self::ARG_SCHEMA] ?? null;

		if ( $schemaName !== null ) {
			$schemaTitle = $this->existingSchemaTitle( $parser, $schemaName );

			if ( $schemaTitle === null ) {
				return $this->renderError( $parser, 'neowiki-create-subject-error-unknown-schema', $schemaName );
			}

			// Subjects store their Schema under the name its page is titled with, not as typed.
			$attributes['data-mw-neowiki-schema'] = $schemaTitle->getText();
		}

		$text = $named[self::ARG_TEXT] ?? null;

		if ( $text !== null ) {
			$attributes['data-mw-neowiki-text'] = $text;
		}

		$page = $this->pageAttributes( $parser, $named[self::ARG_PAGE] ?? null );

		if ( is_string( $page ) ) {
			return $page;
		}

		return $attributes + $page + $this->hostPageAttributes( $parser );
	}

	/**
	 * A page whose Schema does not exist yet renders an error, so the link re-parses it once the Schema is created.
	 */
	private function existingSchemaTitle( Parser $parser, string $schemaName ): ?Title {
		$title = Title::newFromText( $schemaName, NeoWikiExtension::NS_SCHEMA );

		// A prefix naming another namespace names a page that cannot be a Schema.
		if ( $title === null || !$title->inNamespace( NeoWikiExtension::NS_SCHEMA ) ) {
			return null;
		}

		$parser->getOutput()->addLink( $title, $title->getArticleID() );

		return $title->exists() ? $title : null;
	}

	/**
	 * @return array<string, string>|string
	 */
	private function pageAttributes( Parser $parser, ?string $page ): array|string {
		if ( $page === null ) {
			return [];
		}

		if ( $page === self::PAGE_NEW ) {
			return [ 'data-mw-neowiki-page' => self::PAGE_NEW ];
		}

		if ( $page === self::PAGE_THIS ) {
			// A page that cannot hold Subjects falls back to a new page rather than an error.
			$pageCanHoldSubjects = SubjectsAction::isEligibleTitle( $parser->getTitle() );

			return [ 'data-mw-neowiki-page' => $pageCanHoldSubjects ? self::PAGE_THIS : self::PAGE_NEW ];
		}

		return $this->namedPageAttributes( $parser, $page );
	}

	/**
	 * Emitted whichever page the Subject goes on: a host page is what makes saving return to a page.
	 *
	 * @return array<string, string>
	 */
	private function hostPageAttributes( Parser $parser ): array {
		$title = $parser->getTitle();

		if ( !SubjectsAction::isEligibleTitle( $title ) ) {
			return [];
		}

		return [ 'data-mw-neowiki-page-has-main-subject' => $this->hasMainSubject( $title ) ];
	}

	private function hasMainSubject( Title $title ): string {
		return $this->pageSubjectsLookup->pageHasMainSubject( new PageId( $title->getArticleID() ) )
			? 'true'
			: 'false';
	}

	/**
	 * @return array<string, string>|string
	 */
	private function namedPageAttributes( Parser $parser, string $page ): array|string {
		$title = Title::newFromText( $page );

		// canExist() rules out interwiki links and special pages.
		if ( $title === null || !$title->canExist() ) {
			return $this->renderError( $parser, 'neowiki-create-subject-error-invalid-page', $page );
		}

		$pageId = $title->getArticleID();

		// The emitted page id is only right until the page is created or deleted.
		$parser->getOutput()->addLink( $title, $pageId );

		// CreateSubjectPageApi creates main-namespace pages only, and a fixed title leaves the dialog
		// no field to answer a save failure with.
		if ( $pageId === 0 && !$title->inNamespace( NS_MAIN ) ) {
			return $this->renderError(
				$parser,
				'neowiki-create-subject-error-uncreatable-page',
				$title->getPrefixedText()
			);
		}

		return [
			'data-mw-neowiki-page-title' => $title->getPrefixedText(),
			'data-mw-neowiki-page-id' => (string)$pageId,
		];
	}

	/**
	 * Help and Project pages carry no Subjects, so nothing else loads the frontend there.
	 */
	private function loadFrontend( Parser $parser ): void {
		$parser->getOutput()->addModules( [ 'ext.neowiki' ] );
		$parser->getOutput()->addModuleStyles( [ 'ext.neowiki.styles' ] );
	}

	/**
	 * The edit stash parses a new page before it has an id. Recording the id used, as {{PAGEID}} does, makes
	 * saving render the page again once it has one instead of keeping output that treats it as absent.
	 */
	private function recordPageIdDependency( Parser $parser ): void {
		$output = $parser->getOutput();
		$output->setOutputFlag( ParserOutputFlags::VARY_PAGE_ID );

		$pageId = $parser->getTitle()->getArticleID();

		if ( $pageId !== 0 ) {
			$output->setSpeculativePageIdUsed( $pageId );
		}
	}

	private function renderError( Parser $parser, string $messageKey, string $insertion ): string {
		return '<div class="error">' . $parser->msg( $messageKey, $insertion )->escaped() . '</div>';
	}

}
