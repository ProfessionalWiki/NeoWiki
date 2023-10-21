<?php

declare( strict_types = 1 );

namespace Application\Actions;

use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectsAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectsRequest;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\RevisionUpdater;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\IncrementalGuidGenerator;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectsAction
 */
class CreateSubjectsActionTest extends \MediaWikiIntegrationTestCase {

	private const PAGE_ID = 7;
	private const WIKITEXT = 'Test Text {{#infobox:00000000-0000-0000-0000-000000000aaa}}{{#infobox:00000000-0000-0000-0000-000000000bbb}}';

	private InMemorySubjectRepository $subjectRepository;
	private SubjectActionAuthorizer $authorizer;
	private RenderedRevision $renderedRevision;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->authorizer = new SucceedingSubjectActionAuthorizer();
		$this->renderedRevision = $this->buildRenderedRevision();
	}

	private function buildRenderedRevision(): RenderedRevision {
		$revision = new MutableRevisionRecord(
			PageIdentityValue::localIdentity( self::PAGE_ID, NS_MAIN, 'RenderTestPage' )
		);

		$revision->setContent( SlotRecord::MAIN, new \WikitextContent( self::WIKITEXT ) );

		return new RenderedRevision(
			$revision,
			\ParserOptions::newFromAnon(),
			$this->getServiceContainer()->getContentRenderer(),
			function (): mixed {
			}
		);
	}

	private function newCreateSubjectsAction(): CreateSubjectsAction {
		return new CreateSubjectsAction(
			$this->subjectRepository,
			$this->authorizer,
			new StatementListPatcher(
				NeoWikiExtension::getInstance()->getFormatTypeLookup(),
				new IncrementalGuidGenerator()
			),
			new RevisionUpdater(
				new \User(),
				$this->renderedRevision
			)
		);
	}

	public function testCreateSubjects(): void {
		$this->newCreateSubjectsAction()->createSubjects(
			new CreateSubjectsRequest(
				new PageId( $this->renderedRevision->getRevision()->getPage()->getId() ),
				$this->newValidSubjectsJson()
			)
		);

		$revision = $this->renderedRevision->getRevision();

		$this->assertMainSlotIsIntact( $revision );
		$this->assertSubjectsHaveBeenAdded( $revision );
	}

	private function newValidSubjectsJson(): string {
		return <<<JSON
[
    {
        "id":"00000000-0000-0000-0000-000000000aaa",
        "label":"aaa",
        "schema":"City",
        "statements":{
            "Country":{
                "format":"text",
                "value":[
                    "aaa"
                ]
            }
        }
    },
    {
    	"id":"00000000-0000-0000-0000-000000000bbb",
        "label":"bbb",
        "schema":"City",
        "statements":{
            "Country":{
                "format":"text",
                "value":[
                    "bbb"
                ]
            }
        }
    }
]
JSON;
	}

	private function assertMainSlotIsIntact( RevisionRecord $revision ): void {
		$this->assertSame( // Ensure the main slot is still there
			self::WIKITEXT,
			$revision->getContent( SlotRecord::MAIN )->serialize()
		);
	}

	private function assertSubjectsHaveBeenAdded( RevisionRecord $revision ): void {
		/**
		 * @var SubjectContent $subjectContent
		 */
		$subjectContent = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME );

		$this->assertSame(
			[ '00000000-0000-0000-0000-000000000aaa', '00000000-0000-0000-0000-000000000bbb' ], // TODO: re-extract GUIDs as constants
			$subjectContent->getPageSubjects()->getAllSubjects()->getIdsAsTextArray()
		);
	}

	public function testUserIsNotAllowedToCreateSubject(): void {
		$this->authorizer = new FailingSubjectActionAuthorizer();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to create this subject' );

		$this->newCreateSubjectsAction()->createSubjects(
			new CreateSubjectsRequest(
				new PageId( $this->renderedRevision->getRevision()->getPage()->getId() ),
				$this->newValidSubjectsJson()
			)
		);
	}

	public function testNotCreateSubjects(): void {
		$this->newCreateSubjectsAction()->createSubjects(
			new CreateSubjectsRequest(
				new PageId( $this->renderedRevision->getRevision()->getPage()->getId() ),
				$this->newInvalidSubjectsJson()
			)
		);

		$this->expectException( RevisionAccessException::class );
		$this->expectExceptionMessage( 'No such slot: ' . MediaWikiSubjectRepository::SLOT_NAME );

		$this->renderedRevision->getRevision()->getContent( MediaWikiSubjectRepository::SLOT_NAME );
	}

	private function newInvalidSubjectsJson(): string {
		// This JSON is invalid because the required key "schema" is missing.
		return <<<JSON
[
    {
        "id":"00000000-0000-0000-0000-000000000aaa",
        "label":"aaa",
        "statements":{
            "Country":{
                "format":"text",
                "value":[
                    "aaa"
                ]
            }
        }
    }
]
JSON;
	}

}
