<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\RequestContext;
use MediaWiki\Content\TextContent;
use Closure;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectInPlaceOfPageTitleLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * Needs the database for the pages whose Subject slots it reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectInPlaceOfPageTitleLookup
 * @group Database
 */
class SubjectInPlaceOfPageTitleLookupTest extends NeoWikiIntegrationTestCase {

	private const string MAIN_ID = 's1zz1111111azz6';
	private const string OTHER_ID = 's1zz1111111azz7';

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testPagesListedTogetherAreEachNamedByTheirMainSubject(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$this->savePage( self::OTHER_ID, 'Grace Hopper', self::OTHER_ID );

		$lookup = $this->lookupListing( self::MAIN_ID, self::OTHER_ID );

		$this->assertSame( self::MAIN_ID, $this->subjectOf( $lookup, self::MAIN_ID )?->getId()->text );
		$this->assertSame( self::OTHER_ID, $this->subjectOf( $lookup, self::OTHER_ID )?->getId()->text );
	}

	public function testAPageTitledByAnyoneIsNotNamed(): void {
		$this->savePage( 'Standardization', 'Ada Lovelace' );

		$this->assertNull( $this->subjectOf( $this->lookupListing( 'Standardization' ), 'Standardization' ) );
	}

	/**
	 * A namespace prefix is part of the page name, so such a page is not titled by the id.
	 */
	public function testAPageInAnotherNamespaceIsNotNamed(): void {
		$this->savePage( 'Help:' . self::MAIN_ID, 'Ada Lovelace' );

		$this->assertNull( $this->subjectOf( $this->lookupListing( 'Help:' . self::MAIN_ID ), 'Help:' . self::MAIN_ID ) );
	}

	public function testAPageIsNamedByItsCurrentLabel(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$this->changeSubjectsOfPage( self::MAIN_ID, TestSubject::build( id: self::MAIN_ID, label: 'Countess' ) );

		$subject = $this->subjectOf( $this->lookupListing( self::MAIN_ID ), self::MAIN_ID );

		$this->assertSame( 'Countess', $subject?->getLabel()?->text );
	}

	/**
	 * On a wiki with an approval extension, readers see the approved revision, not the latest draft.
	 */
	public function testAPageIsNamedAsTheWikiPublishesIt(): void {
		$approved = $this->createPageWithSubjects(
			self::MAIN_ID,
			TestSubject::build( id: self::MAIN_ID, label: 'Ada Lovelace' )
		);
		$this->changeSubjectsOfPage( self::MAIN_ID, TestSubject::build( id: self::MAIN_ID, label: 'Countess' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		$subject = $this->subjectOf( $this->lookupListing( self::MAIN_ID ), self::MAIN_ID );

		$this->assertSame( 'Ada Lovelace', $subject?->getLabel()?->text );
	}

	public function testAPageThatPublishesNothingIsNotNamed(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishingNothing() );

		$this->assertNull( $this->subjectOf( $this->lookupListing( self::MAIN_ID ), self::MAIN_ID ) );
	}

	/**
	 * Subject data that does not deserialize must not take a list down.
	 *
	 * @dataProvider brokenSubjectJsonProvider
	 */
	public function testAPageWhoseSubjectsDoNotDeserializeIsNotNamed( string $subjectJson ): void {
		$this->savePageWithSubjectJson( self::MAIN_ID, $subjectJson );

		$this->assertNull( $this->subjectOf( $this->lookupListing( self::MAIN_ID ), self::MAIN_ID ) );
	}

	public static function brokenSubjectJsonProvider(): iterable {
		yield 'a Subject that fails validation' => [ TestSubject::jsonThatDoesNotDeserialize( self::MAIN_ID ) ];
		yield 'a Subject that is not an object' => [
			'{"mainSubject":"' . self::MAIN_ID . '","subjects":{"' . self::MAIN_ID . '":1}}',
		];
	}

	/**
	 * Anyone can ask for a long list, so what one reads is bounded.
	 */
	public function testAPageWhoseSubjectsAreTooLargeToReadIsNotNamed(): void {
		$this->savePage( self::MAIN_ID, str_repeat( 'Ada Lovelace ', 6000 ) );

		$this->assertNull( $this->subjectOf( $this->lookupListing( self::MAIN_ID ), self::MAIN_ID ) );
	}

	public function testAListReadsNoMorePagesThanItIsAllowed(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$this->savePage( self::OTHER_ID, 'Grace Hopper', self::OTHER_ID );

		$lookup = $this->lookupListingAtMost( 1, self::MAIN_ID, self::OTHER_ID );

		$this->assertNotNull( $this->subjectOf( $lookup, self::MAIN_ID ) );
		$this->assertNull( $this->subjectOf( $lookup, self::OTHER_ID ) );
	}

	/**
	 * A list transcluded into a cached page renders in a later output of the same request.
	 */
	public function testALinkRenderedElsewhereThanTheListIsNotNamed(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );

		$subject = $this->lookupListing( self::MAIN_ID )->forListedPage(
			Title::newFromText( self::MAIN_ID ),
			$this->getTestUser()->getAuthority(),
			new OutputPage( RequestContext::getMain() )
		);

		$this->assertNull( $subject );
	}

	public function testEachReaderIsNamedThePageOnlyIfTheyMayReadIt(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$sysop = $this->getTestSysop()->getAuthority();

		$lookup = $this->lookupListingReadBy(
			static fn ( Authority $reader ): StubPageReadAuthorizer => new StubPageReadAuthorizer(
				$reader->getUser()->getName() === $sysop->getUser()->getName()
			),
			self::MAIN_ID
		);

		$this->assertNotNull( $this->subjectReadBy( $lookup, self::MAIN_ID, $sysop ) );
		$this->assertNull( $this->subjectReadBy( $lookup, self::MAIN_ID, $this->getTestUser()->getAuthority() ) );
	}

	/**
	 * Enhanced Recent changes links a page from every change to it, and from the group heading too.
	 */
	public function testManyLinksToOnePageCheckTheReadersAccessOnce(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$checks = 0;

		$lookup = $this->lookupListingReadBy(
			static function () use ( &$checks ): StubPageReadAuthorizer {
				$checks++;
				return new StubPageReadAuthorizer( true );
			},
			self::MAIN_ID
		);

		$this->subjectOf( $lookup, self::MAIN_ID );
		$this->subjectOf( $lookup, self::MAIN_ID );

		$this->assertSame( 1, $checks );
	}

	private function lookupListing( string ...$pageNames ): SubjectInPlaceOfPageTitleLookup {
		return $this->lookupListingAtMost( 250, ...$pageNames );
	}

	private function lookupListingAtMost( int $maxPages, string ...$pageNames ): SubjectInPlaceOfPageTitleLookup {
		return $this->newLookupListing(
			$maxPages,
			static fn (): StubPageReadAuthorizer => new StubPageReadAuthorizer( true ),
			$pageNames
		);
	}

	private function lookupListingReadBy( Closure $newReadAuthorizer, string ...$pageNames ): SubjectInPlaceOfPageTitleLookup {
		return $this->newLookupListing( 250, $newReadAuthorizer, $pageNames );
	}

	/**
	 * @param string[] $pageNames
	 */
	private function newLookupListing(
		int $maxPages,
		Closure $newReadAuthorizer,
		array $pageNames
	): SubjectInPlaceOfPageTitleLookup {
		$lookup = new SubjectInPlaceOfPageTitleLookup(
			$this->getServiceContainer()->getRevisionStore(),
			$this->getDb(),
			NeoWikiExtension::getInstance()->getRevisionPolicy(),
			$newReadAuthorizer,
			$maxPages
		);

		$lookup->prefetch(
			array_map(
				static fn ( string $pageName ): int => Title::newFromText( $pageName )->getArticleID(),
				$pageNames
			),
			RequestContext::getMain()->getOutput()
		);

		return $lookup;
	}

	private function subjectOf( SubjectInPlaceOfPageTitleLookup $lookup, string $pageName ): ?Subject {
		return $this->subjectReadBy( $lookup, $pageName, $this->getTestUser()->getAuthority() );
	}

	private function subjectReadBy( SubjectInPlaceOfPageTitleLookup $lookup, string $pageName, Authority $reader ): ?Subject {
		return $lookup->forListedPage( Title::newFromText( $pageName ), $reader, RequestContext::getMain()->getOutput() );
	}

	private function savePage( string $pageName, string $label, string $subjectId = self::MAIN_ID ): void {
		$this->createPageWithSubjects( $pageName, TestSubject::build( id: $subjectId, label: $label ) );
	}

	private function savePageWithSubjectJson( string $pageName, string $subjectJson ): void {
		// The slot is written as is: saving through NeoWiki would refuse what does not deserialize.
		$this->clearHook( 'RevisionFromEditComplete' );

		$updater = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageName ) )
			->newPageUpdater( $this->getTestSysop()->getUser() );

		$updater->setContent( 'main', new TextContent( '' ) );
		$updater->setContent( MediaWikiSubjectRepository::SLOT_NAME, new SubjectContent( $subjectJson ) );

		$this->assertNotNull( $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'raw subject slot' ) ) );
	}

}
