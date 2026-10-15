<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectContentRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder
 */
class ViewHtmlBuilderTest extends TestCase {

	private const string TITLING_SUBJECT_ID = 's1zz1111111azz4';

	public function testReturnsEmptyStringWhenNoContentExists(): void {
		$builder = new ViewHtmlBuilder( new InMemorySubjectContentRepository() );

		$html = $builder->mainSubjectHtml( Title::newFromText( 'NoContent' ), null );

		$this->assertSame( '', $html );
	}

	public function testReturnsEmptyStringWhenContentHasNoMainSubject(): void {
		$builder = new ViewHtmlBuilder(
			new InMemorySubjectContentRepository( PageSubjects::newEmpty() )
		);

		$html = $builder->mainSubjectHtml( Title::newFromText( 'Empty' ), null );

		$this->assertSame( '', $html );
	}

	public function testReturnsDivWithSubjectIdAttribute(): void {
		$subject = TestSubject::build( id: 's1zz1111111azz1' );

		$builder = new ViewHtmlBuilder(
			new InMemorySubjectContentRepository( new PageSubjects( $subject, new SubjectMap() ) )
		);

		$html = $builder->mainSubjectHtml( Title::newFromText( 'HasSubject' ), null );

		$this->assertStringContainsString( 'class="ext-neowiki-view"', $html );
		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s1zz1111111azz1"', $html );
	}

	public function testDoesNotIncludeRevisionIdInHtml(): void {
		$subject = TestSubject::build( id: 's1zz1111111azz2' );

		$repo = new InMemorySubjectContentRepository();
		$repo->setContentForRevision( 42, new PageSubjects( $subject, new SubjectMap() ) );

		$builder = new ViewHtmlBuilder( $repo );

		$html = $builder->mainSubjectHtml( Title::newFromText( 'WithRevision' ), 42 );

		$this->assertStringNotContainsString( 'revision', $html );
	}

	public function testUsesRevisionIdToLookUpContent(): void {
		$subject = TestSubject::build( id: 's1zz1111111azz3' );

		$repo = new InMemorySubjectContentRepository();
		$repo->setContentForRevision( 42, new PageSubjects( $subject, new SubjectMap() ) );

		$builder = new ViewHtmlBuilder( $repo );

		$html = $builder->mainSubjectHtml( Title::newFromText( 'RevisionLookup' ), 42 );

		$this->assertStringContainsString( 'data-mw-neowiki-subject-id="s1zz1111111azz3"', $html );
	}

	public function testShowsNoMainSubjectInFullForAPageWithoutOne(): void {
		$builder = new ViewHtmlBuilder(
			new InMemorySubjectContentRepository( new PageSubjects( null, new SubjectMap( TestSubject::build() ) ) )
		);

		$this->assertSame( '', $builder->mainSubjectInFullHtml( Title::newFromText( 'OnlyOtherSubjects' ) ) );
	}

	private function pageHoldingSubjectLabelled( string $label ): PageSubjects {
		return new PageSubjects(
			TestSubject::build( id: self::TITLING_SUBJECT_ID, label: $label ),
			new SubjectMap()
		);
	}

	public function testAPageTitledByItsMainSubjectsIdIsHeadedByThatSubject(): void {
		$builder = new ViewHtmlBuilder(
			new InMemorySubjectContentRepository( $this->pageHoldingSubjectLabelled( 'Ada' ) )
		);

		$subject = $builder->subjectInPlaceOfPageTitle( Title::newFromText( self::TITLING_SUBJECT_ID ), null );

		$this->assertSame( self::TITLING_SUBJECT_ID, $subject?->getId()->text );
	}

	public function testAPageTitledByAnyoneKeepsItsTitle(): void {
		$builder = new ViewHtmlBuilder(
			new InMemorySubjectContentRepository( $this->pageHoldingSubjectLabelled( 'Ada' ) )
		);

		$this->assertNull( $builder->subjectInPlaceOfPageTitle( Title::newFromText( 'Standardization' ), null ) );
	}

	public function testAPageInTheSubjectNamespaceTitledByItsMainSubjectsIdIsHeadedByThatSubject(): void {
		$builder = new ViewHtmlBuilder(
			new InMemorySubjectContentRepository( $this->pageHoldingSubjectLabelled( 'Ada' ) )
		);

		$subject = $builder->subjectInPlaceOfPageTitle( Title::newFromText( 'Subject:' . self::TITLING_SUBJECT_ID ), null );

		$this->assertSame( self::TITLING_SUBJECT_ID, $subject?->getId()->text );
	}

	/**
	 * Subject data that does not deserialize must not take the page view down.
	 */
	public function testAPageWhoseSubjectsDoNotDeserializeKeepsItsTitle(): void {
		$subject = ( new ViewHtmlBuilder( $this->repositoryHoldingSubjectsThatDoNotDeserialize() ) )
			->subjectInPlaceOfPageTitle( Title::newFromText( self::TITLING_SUBJECT_ID ), null );

		$this->assertNull( $subject );
	}

	public function testAPageWhoseSubjectsDoNotDeserializeShowsNoMainSubject(): void {
		$html = ( new ViewHtmlBuilder( $this->repositoryHoldingSubjectsThatDoNotDeserialize() ) )
			->mainSubjectHtml( Title::newFromText( self::TITLING_SUBJECT_ID ), null );

		$this->assertSame( '', $html );
	}

	public function testSubjectsThatDoNotDeserializeAreReadOncePerPageView(): void {
		$repository = $this->repositoryHoldingSubjectsThatDoNotDeserialize();
		$builder = new ViewHtmlBuilder( $repository );

		$builder->subjectInPlaceOfPageTitle( Title::newFromText( self::TITLING_SUBJECT_ID ), null );
		$builder->mainSubjectHtml( Title::newFromText( self::TITLING_SUBJECT_ID ), null );

		$this->assertSame( 1, $repository->readCount );
	}

	private function repositoryHoldingSubjectsThatDoNotDeserialize(): InMemorySubjectContentRepository {
		return new class() extends InMemorySubjectContentRepository {
			public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent {
				return $this->brokenContent();
			}

			public function getSubjectContentByRevisionId( int $revisionId ): ?SubjectContent {
				return $this->brokenContent();
			}

			public function getSubjectContentByPageId( PageId $pageId ): ?SubjectContent {
				return $this->brokenContent();
			}

			private function brokenContent(): SubjectContent {
				$this->readCount++;
				return new SubjectContent( TestSubject::jsonThatDoesNotDeserialize( 's1zz1111111azz4' ) );
			}
		};
	}

	/**
	 * Each read queries the database, and a page view asks for both.
	 */
	public function testThePageHeadingAndTheMainSubjectOfOnePageViewShareOneRead(): void {
		$repository = new InMemorySubjectContentRepository( $this->pageHoldingSubjectLabelled( 'Ada' ) );
		$builder = new ViewHtmlBuilder( $repository );

		$builder->subjectInPlaceOfPageTitle( Title::newFromText( self::TITLING_SUBJECT_ID ), null );
		$builder->mainSubjectHtml( Title::newFromText( self::TITLING_SUBJECT_ID ), null );

		$this->assertSame( 1, $repository->readCount );
	}

	public function testATitleThatCannotBeASubjectIdIsHeadedWithoutAnyRead(): void {
		$repository = new InMemorySubjectContentRepository( $this->pageHoldingSubjectLabelled( 'Ada' ) );

		( new ViewHtmlBuilder( $repository ) )->subjectInPlaceOfPageTitle( Title::newFromText( 'Ada Lovelace' ), null );

		$this->assertSame( 0, $repository->readCount );
	}

	public function testRecognizesTheMountForTheMainSubjectInFull(): void {
		$this->assertTrue( ViewHtmlBuilder::containsMainSubjectInFull(
			'<p>Text</p>' . ViewHtmlBuilder::mainSubjectInFullPlaceholderHtml( self::TITLING_SUBJECT_ID )
		) );
	}

	public function testAViewIsNoMountForTheMainSubjectInFull(): void {
		$this->assertFalse( ViewHtmlBuilder::containsMainSubjectInFull(
			ViewHtmlBuilder::viewPlaceholderHtml( self::TITLING_SUBJECT_ID )
		) );
	}

	/**
	 * @dataProvider wikitextMarkupProvider
	 */
	public function testWikitextCannotFakeAMainSubjectMount( string $sanitizedMarkup ): void {
		$this->assertFalse( ViewHtmlBuilder::containsMainSubjectInFull( $sanitizedMarkup ) );
	}

	public static function wikitextMarkupProvider(): iterable {
		yield 'the id alone' => [ '<div id="ext-neowiki-main-subject"></div>' ];
		yield 'an attribute ending in the data attribute' => [
			'<div id="ext-neowiki-main-subject" data-x-data-mw-neowiki-subject-id="1"></div>'
		];
		yield 'the data attribute inside a value' => [
			'<div id="ext-neowiki-main-subject" title="a data-mw-neowiki-subject-id="></div>'
		];
	}

}
