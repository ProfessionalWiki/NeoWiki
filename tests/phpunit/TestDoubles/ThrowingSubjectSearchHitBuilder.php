<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHit;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHitBuilder;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchTextBuilder;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use RuntimeException;

/**
 * Stands in for whatever below a search result row can fail: a Source that cannot be reached, a
 * Subject slot nothing can make sense of, a Schema lookup that throws.
 */
readonly class ThrowingSubjectSearchHitBuilder extends SubjectSearchHitBuilder {

	public const string MESSAGE = 'The Source could not be reached';

	public function __construct() {
		parent::__construct( new SubjectSearchTextBuilder(
			new PropertyTypeRegistry(),
			TestSources::newSchemaResolver( new InMemorySchemaLookup() )
		) );
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
