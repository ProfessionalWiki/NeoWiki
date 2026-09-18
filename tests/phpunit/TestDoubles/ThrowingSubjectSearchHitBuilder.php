<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHit;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHitBuilder;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use RuntimeException;

/**
 * A builder that fails, as a Source or Schema lookup below a result row can.
 */
readonly class ThrowingSubjectSearchHitBuilder extends SubjectSearchHitBuilder {

	public const string MESSAGE = 'The Source could not be reached';

	/**
	 * The parent's text builder is left unset: build() throws before anything can read it.
	 */
	public function __construct() {
	}

	public function build(
		PageSubjects $pageSubjects,
		string $pageName,
		bool $pageHasContent,
		?SearchTermMatcher $matcher
	): ?SubjectSearchHit {
		throw new RuntimeException( self::MESSAGE );
	}

}
