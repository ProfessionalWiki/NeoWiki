<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository
 */
class SubjectContentRepositoryTest extends TestCase {

	public function testPageHasSubjectsReturnsTrueWhenSubjectsExist(): void {
		$repository = $this->repositoryWithContent(
			SubjectContent::newFromData( new PageSubjects( TestSubject::build(), new SubjectMap() ) )
		);

		$this->assertTrue( $repository->pageHasSubjects( Title::newFromText( 'HasSubjects' ) ) );
	}

	public function testPageHasSubjectsReturnsFalseWhenNoContent(): void {
		$repository = $this->repositoryWithContent( null );

		$this->assertFalse( $repository->pageHasSubjects( Title::newFromText( 'NoContent' ) ) );
	}

	public function testPageHasSubjectsReturnsFalseWhenContentIsEmpty(): void {
		$repository = $this->repositoryWithContent(
			SubjectContent::newFromData( PageSubjects::newEmpty() )
		);

		$this->assertFalse( $repository->pageHasSubjects( Title::newFromText( 'EmptyContent' ) ) );
	}

	public function testPageHasMainSubjectReturnsTrueWhenMainSubjectExists(): void {
		$repository = $this->repositoryWithContent(
			SubjectContent::newFromData( new PageSubjects( TestSubject::build(), new SubjectMap() ) )
		);

		$this->assertTrue( $repository->pageHasMainSubject( Title::newFromText( 'HasMain' ) ) );
	}

	public function testPageHasMainSubjectReturnsFalseWhenNoContent(): void {
		$repository = $this->repositoryWithContent( null );

		$this->assertFalse( $repository->pageHasMainSubject( Title::newFromText( 'NoContent' ) ) );
	}

	public function testPageHasMainSubjectReturnsFalseWhenOnlyChildrenExist(): void {
		$child = TestSubject::build( id: 's1zz1111111ccc1' );
		$repository = $this->repositoryWithContent(
			SubjectContent::newFromData( new PageSubjects( null, new SubjectMap( $child ) ) )
		);

		$this->assertFalse( $repository->pageHasMainSubject( Title::newFromText( 'OnlyChildren' ) ) );
	}

	private function repositoryWithContent( ?SubjectContent $content ): SubjectContentRepository {
		return new class( $content ) extends SubjectContentRepository {

			public function __construct(
				private readonly ?SubjectContent $content,
			) {
			}

			public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent {
				return $this->content;
			}

		};
	}

}
