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
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\SubjectsPageData;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Infrastructure\SubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\RevisionUpdater;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FailingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SucceedingSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\TestGuidGenerator;
use ParserOutput;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectsAction
 */
class CreateSubjectsActionTest extends \MediaWikiIntegrationTestCase {

	private const GUID_ONE = '00000000-8888-0000-0000-000000000002';
	private const GUID_TWO = '00000000-8888-0000-0000-000000000003';
	private const FAKE_GUID_ONE = '77b57a00-4403-438e-85cb-fee6d54cd70b';
	private const FAKE_GUID_TWO = '77b57a00-4403-438e-85cb-fee6d54cd71b';
	private const PAGE_ID = 7;

	private InMemorySubjectRepository $subjectRepository;
	private SubjectActionAuthorizer $authorizer;
	private RevisionRecord $revision;
	private RenderedRevision $renderedRevision;

	public function setUp(): void {
		$this->subjectRepository = new InMemorySubjectRepository();
		$this->guidGenerator = new TestGuidGenerator( [ self::GUID_ONE, self::GUID_TWO ] );
		$this->authorizer = new SucceedingSubjectActionAuthorizer();

		$this->revision = new MutableRevisionRecord(
			PageIdentityValue::localIdentity( self::PAGE_ID, NS_MAIN, 'RenderTestPage' )
		);
		$this->revision->setContent( SlotRecord::MAIN, new \WikitextContent( $this->getTestText( [ self::FAKE_GUID_ONE, self::FAKE_GUID_TWO ] ) ) );

		$this->renderedRevision = new RenderedRevision(
			$this->revision,
			\ParserOptions::newFromAnon(),
			$this->getServiceContainer()->getContentRenderer(),
			function ( RenderedRevision $rr, array $hints = [] ) {
				return $this->combineOutput( $rr, $hints );
			}
		);
	}

	private function getTestText( array $guids ): string {
		$text = 'Test Text ';
		foreach ( $guids as $guid ) {
			$text .= '{{#infobox:' . $guid . '}}';
		}
		return $text;
	}

	private function getTestData(): array {
		return [
			'mainSubject' => null,
			'subjects' => [
				self::FAKE_GUID_ONE => [
					'label' => 'aaa',
					'schema' => 'City',
					'statements' => [
						'Country' => [
							'format' => 'text',
							'value' => [ 'aaa' ]
						]
					],
				],
				self::FAKE_GUID_TWO => [
					'label' => 'bbb',
					'schema' => 'City',
					'statements' => [
						'Country' => [
							'format' => 'text',
							'value' => [ 'bbb' ]
						]
					],
				],
			]
		];
	}

	private function combineOutput( RenderedRevision $rrev, array $hints = [] ): ParserOutput {
		$withHtml = $hints['generate-html'] ?? true;

		$revision = $rrev->getRevision();
		$slots = $revision->getSlots()->getSlots();

		$combinedOutput = new ParserOutput( null );
		$slotOutput = [];
		foreach ( $slots as $role => $slot ) {
			$out = $rrev->getSlotParserOutput( $role, $hints );
			$slotOutput[$role] = $out;

			$combinedOutput->mergeInternalMetaDataFrom( $out );
			$combinedOutput->mergeTrackingMetaDataFrom( $out );
		}

		if ( $withHtml ) {
			$html = '';
			/** @var ParserOutput $out */
			foreach ( $slotOutput as $role => $out ) {

				if ( $html !== '' ) {
					$html .= "(($role))";
				}

				$html .= $out->getRawText();
				$combinedOutput->mergeHtmlMetaDataFrom( $out );
			}

			$combinedOutput->setText( $html );
		}

		return $combinedOutput;
	}

	private function newCreateSubjectsAction(): CreateSubjectsAction {
		return new CreateSubjectsAction(
			$this->subjectRepository,
			$this->guidGenerator,
			$this->authorizer,
			new StatementListPatcher(
				NeoWikiExtension::getInstance()->getFormatTypeLookup(),
				$this->guidGenerator
			),
			new RevisionUpdater(
				new \User(),
				$this->renderedRevision
			)
		);
	}

	public function testCreateSubjects(): void {

		$testSubjects = $this->getTestData();

		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( self::PAGE_ID ) );

		$this->newCreateSubjectsAction()->createSubjects(
			new CreateSubjectsRequest(
				new PageId( $this->renderedRevision->getRevision()->getPage()->getId() ),
				new SubjectsPageData(
					$this->renderedRevision->getRevision()->getContent( SlotRecord::MAIN )->getWikitextForTransclusion(),
					json_encode( array_map( function ( $item, $key ) {
						$item[ 'id' ] = $key;
						return $item;
					}, $testSubjects['subjects'], array_keys( $testSubjects['subjects'] ) ) )
				)
			)
		);

		$rev = $this->renderedRevision->getRevision();
		$mainWikiContent = $rev->getContent( SlotRecord::MAIN )->serialize();
		$neoWikiContent = $rev->getContent( MediaWikiSubjectRepository::SLOT_NAME )->serialize();

		$this->assertEquals(
			$this->getTestText( [ self::GUID_ONE, self::GUID_TWO ] ),
			$mainWikiContent
		);

		$this->assertJsonStringEqualsJsonString(
			str_replace(
				[ self::FAKE_GUID_ONE, self::FAKE_GUID_TWO ],
				[ self::GUID_ONE, self::GUID_TWO ],
				json_encode( $testSubjects )
			),
			$neoWikiContent
		);
	}

	public function testUserIsNotAllowedToCreateSubject(): void {
		$this->authorizer = new FailingSubjectActionAuthorizer();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to create this subject' );

		$testSubjects = $this->getTestData();

		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( self::PAGE_ID ) );

		$this->newCreateSubjectsAction()->createSubjects(
			new CreateSubjectsRequest(
				new PageId( $this->renderedRevision->getRevision()->getPage()->getId() ),
				new SubjectsPageData(
					$this->renderedRevision->getRevision()->getContent( SlotRecord::MAIN )->getWikitextForTransclusion(),
					json_encode( array_map( function ( $item, $key ) {
						$item[ 'id' ] = $key;
						return $item;
					}, $testSubjects['subjects'], array_keys( $testSubjects['subjects'] ) ) )
				)
			)
		);
	}

	public function testNotCreateSubjects(): void {

		$this->expectException( RevisionAccessException::class );
		$this->expectExceptionMessage( 'No such slot: ' . MediaWikiSubjectRepository::SLOT_NAME );

		$testSubjects = $this->getTestData();

		$this->subjectRepository->savePageSubjects( PageSubjects::newEmpty(), new PageId( self::PAGE_ID ) );

		$this->newCreateSubjectsAction()->createSubjects(
			new CreateSubjectsRequest(
				new PageId( $this->renderedRevision->getRevision()->getPage()->getId() ),
				new SubjectsPageData(
					$this->renderedRevision->getRevision()->getContent( SlotRecord::MAIN )->getWikitextForTransclusion(),
					json_encode( array_map( function ( $item, $key ) {
						$item[ 'id' ] = $key;
						unset( $item[ 'schema' ] );
						return $item;
					}, $testSubjects['subjects'], array_keys( $testSubjects['subjects'] ) ) )
				)
			)
		);

		$this->renderedRevision->getRevision()->getContent( MediaWikiSubjectRepository::SLOT_NAME )->serialize();
	}

}
