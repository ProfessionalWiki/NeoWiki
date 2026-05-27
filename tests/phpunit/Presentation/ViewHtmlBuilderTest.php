<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectContentRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder
 */
class ViewHtmlBuilderTest extends TestCase {

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

}
