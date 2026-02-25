<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder
 */
class ViewHtmlBuilderTest extends TestCase {

	public function testReturnsEmptyStringWhenNoContentExists(): void {
		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byTitle: null, byRevision: null )
		);

		$html = $builder->mainSubjectHtml( Title::newFromText( 'NoContent' ), null );

		$this->assertSame( '', $html );
	}

	public function testReturnsEmptyStringWhenContentHasNoMainSubject(): void {
		$content = SubjectContent::newFromData( PageSubjects::newEmpty() );

		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byTitle: $content )
		);

		$html = $builder->mainSubjectHtml( Title::newFromText( 'Empty' ), null );

		$this->assertSame( '', $html );
	}

	public function testReturnsDivWithSubjectIdAttribute(): void {
		$subject = TestSubject::build( id: 's1zz1111111azz1' );
		$content = SubjectContent::newFromData( new PageSubjects( $subject, new SubjectMap() ) );

		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byTitle: $content )
		);

		$html = $builder->mainSubjectHtml( Title::newFromText( 'HasSubject' ), null );

		$this->assertStringContainsString( 'class="ext-neowiki-view"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s1zz1111111azz1"', $html );
	}

	public function testDoesNotIncludeRevisionIdInHtml(): void {
		$subject = TestSubject::build( id: 's1zz1111111azz2' );
		$content = SubjectContent::newFromData( new PageSubjects( $subject, new SubjectMap() ) );

		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byRevision: $content )
		);

		$html = $builder->mainSubjectHtml( Title::newFromText( 'WithRevision' ), 42 );

		$this->assertStringNotContainsString( 'revision', $html );
	}

	public function testUsesRevisionIdToLookUpContent(): void {
		$subject = TestSubject::build( id: 's1zz1111111azz3' );
		$revisionContent = SubjectContent::newFromData( new PageSubjects( $subject, new SubjectMap() ) );

		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byTitle: null, byRevision: $revisionContent )
		);

		$html = $builder->mainSubjectHtml( Title::newFromText( 'RevisionLookup' ), 42 );

		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s1zz1111111azz3"', $html );
	}

	public function testPageHasSubjectsReturnsTrueWhenSubjectsExist(): void {
		$content = SubjectContent::newFromData(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byTitle: $content )
		);

		$this->assertTrue( $builder->pageHasSubjects( Title::newFromText( 'HasSubjects' ) ) );
	}

	public function testPageHasSubjectsReturnsFalseWhenNoContent(): void {
		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byTitle: null )
		);

		$this->assertFalse( $builder->pageHasSubjects( Title::newFromText( 'NoContent' ) ) );
	}

	public function testPageHasSubjectsReturnsFalseWhenContentIsEmpty(): void {
		$content = SubjectContent::newFromData( PageSubjects::newEmpty() );

		$builder = new ViewHtmlBuilder(
			$this->stubRepository( byTitle: $content )
		);

		$this->assertFalse( $builder->pageHasSubjects( Title::newFromText( 'EmptyContent' ) ) );
	}

	private function stubRepository(
		?SubjectContent $byTitle = null,
		?SubjectContent $byRevision = null,
	): SubjectContentRepository {
		return new class( $byTitle, $byRevision ) extends SubjectContentRepository {

			public function __construct(
				private readonly ?SubjectContent $titleContent,
				private readonly ?SubjectContent $revisionContent,
			) {
			}

			public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent {
				return $this->titleContent;
			}

			public function getSubjectContentByRevisionId( int $revisionId ): ?SubjectContent {
				return $this->revisionContent;
			}

		};
	}

}
