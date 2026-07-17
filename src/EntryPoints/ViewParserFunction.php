<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\Application\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;

class ViewParserFunction {

	private const string ARG_SUBJECT = 'subject';
	private const string ARG_LAYOUT = 'layout';

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository
	) {
	}

	/**
	 * @return string|array{0: string, noparse: true, isHTML: true}
	 */
	public function handle( Parser $parser, string ...$args ): string|array {
		$parsed = $this->parseArgs( $parser, $args );

		if ( is_string( $parsed ) ) {
			return $this->asHtml( $parsed );
		}

		[ $explicitSubjectId, $layoutName ] = $parsed;

		$resolvedSubjectId = $explicitSubjectId ?? $this->resolveMainSubjectId( $parser );

		if ( $resolvedSubjectId === null ) {
			return '';
		}

		return $this->asHtml( ViewHtmlBuilder::viewPlaceholderHtml( $resolvedSubjectId, $layoutName ) );
	}

	/**
	 * @param string[] $args
	 * @return array{0: ?string, 1: ?string}|string
	 */
	private function parseArgs( Parser $parser, array $args ): array|string {
		$classified = $this->classifyArgs( $parser, $args );

		if ( is_string( $classified ) ) {
			return $classified;
		}

		[ $positional, $named ] = $classified;

		if ( count( $positional ) > 1 ) {
			return $this->renderError( $parser, 'neowiki-view-error-extra-positional', $positional[1] );
		}

		return $this->resolveSubjectAndLayout( $parser, $positional[0] ?? '', $named );
	}

	/**
	 * @param string[] $args
	 * @return array{0: string[], 1: array<string, string>}|string
	 */
	private function classifyArgs( Parser $parser, array $args ): array|string {
		$positional = [];
		$named = [];

		foreach ( $args as $arg ) {
			if ( !str_contains( $arg, '=' ) ) {
				$positional[] = trim( $arg );
				continue;
			}

			[ $key, $value ] = explode( '=', $arg, 2 );
			$key = trim( $key );

			if ( $key !== self::ARG_SUBJECT && $key !== self::ARG_LAYOUT ) {
				return $this->renderError(
					$parser,
					'neowiki-view-error-unknown-arg',
					$key !== '' ? $key : $arg
				);
			}

			$named[$key] = trim( $value );
		}

		return [ $positional, $named ];
	}

	/**
	 * @param array<string, string> $named
	 * @return array{0: ?string, 1: ?string}|string
	 */
	private function resolveSubjectAndLayout( Parser $parser, string $positionalSubject, array $named ): array|string {
		$namedSubject = $named[self::ARG_SUBJECT] ?? '';

		if ( $positionalSubject !== '' && $namedSubject !== '' ) {
			return $this->renderError( $parser, 'neowiki-view-error-conflicting-subject' );
		}

		$subjectId = $this->pickSubjectId( $positionalSubject, $namedSubject );
		$layoutName = ( $named[self::ARG_LAYOUT] ?? '' ) !== '' ? $named[self::ARG_LAYOUT] : null;

		return [ $subjectId, $layoutName ];
	}

	private function pickSubjectId( string $positional, string $named ): ?string {
		if ( $positional !== '' ) {
			return $positional;
		}

		if ( $named !== '' ) {
			return $named;
		}

		return null;
	}

	private function renderError( Parser $parser, string $messageKey, ?string $insertion = null ): string {
		$message = $insertion === null
			? $parser->msg( $messageKey )
			: $parser->msg( $messageKey, $insertion );

		return '<div class="error">' . $message->escaped() . '</div>';
	}

	private function resolveMainSubjectId( Parser $parser ): ?string {
		$title = $parser->getTitle();

		if ( $title === null ) {
			return null;
		}

		$subject = $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->getPageSubjects()
			->getMainSubject();

		return $subject?->getId()->text;
	}

	/**
	 * Hands the HTML to the parser as HTML rather than wikitext. Without this the text is parsed as
	 * wikitext, which autolinks any URL it happens to hold: the URL is swallowed into a link and its
	 * trailing quote percent-encoded, corrupting the error box. The error messages echo the offending
	 * user-supplied argument, which can itself carry a URL, so they need this treatment. The
	 * subject placeholder is armoured the same way.
	 *
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	private function asHtml( string $html ): array {
		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}

}
