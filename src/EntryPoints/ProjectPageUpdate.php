<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Exception;
use MediaWiki\Deferred\DataUpdate;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Deferred\EnqueueableDataUpdate;
use MediaWiki\Deferred\LinksUpdate\LinksTable;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Revision\RenderedRevision;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\ProjectPageJob;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Projects a page once the operation that changed it has committed: the operation holds the page row until
 * then, and a projection made before it commits can land after a later change of the page, such as its
 * deletion. Should the projection fail, {@see ProjectPageJob} runs it again.
 */
class ProjectPageUpdate extends DataUpdate implements EnqueueableDataUpdate {

	/**
	 * The causes MediaWiki gives the secondary data updates of an import, an undeletion and a move up to
	 * MediaWiki 1.43, and of an import and an undeletion from 1.44, which gives a move's the cause of an edit.
	 */
	private const array CAUSES_PROJECTED_FROM_THEIR_OWN_HOOK = [
		'import-page',
		'undelete-page',
		'MovePage',
		'page-import',
		'page-undelete',
	];

	private function __construct(
		private readonly PageId $pageId,
		private readonly ?RenderedRevision $rendering,
		private readonly ?LinksUpdate $linksUpdate,
	) {
		parent::__construct();
	}

	public static function schedule( PageId $pageId ): void {
		DeferredUpdates::addUpdate( new self( $pageId, null, null ) );
	}

	/**
	 * For the secondary data updates MediaWiki runs for a revision, with the rendering MediaWiki made of it and
	 * the links update it runs before this one.
	 */
	public static function amongSecondaryDataUpdates( RenderedRevision $rendering, ?LinksUpdate $linksUpdate ): self {
		return new self( new PageId( $rendering->getRevision()->getPageId() ), $rendering, $linksUpdate );
	}

	public function doUpdate(): void {
		if ( !$this->shouldProject() ) {
			return;
		}

		try {
			NeoWikiExtension::getInstance()->newCurrentPageProjector( isolateFailures: true )
				->project( $this->pageId, $this->rendering );
		} catch ( Exception $e ) {
			if ( $this->isAmongSecondaryDataUpdates() ) {
				// MediaWiki runs the secondary data updates again from a refreshLinks job, which skips a page
				// whose links the failed run already refreshed, and whose links update finds nothing left to
				// change.
				MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush( $this->getAsJobSpecification()['job'] );
			}

			throw $e;
		}
	}

	private function shouldProject(): bool {
		if ( $this->linksUpdate !== null && $this->isLinksRefresh( $this->linksUpdate ) ) {
			return $this->changedCategoriesOrPageProperties( $this->linksUpdate );
		}

		return !$this->isForAnOperationProjectedFromItsOwnHook();
	}

	/**
	 * A links update that does not recurse to the pages using this one is for a revision whose content did not
	 * change, which from MediaWiki 1.44 includes a move's.
	 */
	private function isLinksRefresh( LinksUpdate $linksUpdate ): bool {
		return !$linksUpdate->isRecursive();
	}

	/**
	 * Known only once MediaWiki has run the links update, which it does before this update.
	 */
	private function changedCategoriesOrPageProperties( LinksUpdate $linksUpdate ): bool {
		return $this->changedCategories( $linksUpdate )
			|| $linksUpdate->getAddedProperties() !== []
			|| $linksUpdate->getRemovedProperties() !== [];
	}

	/**
	 * MediaWiki removes and adds again a category whose sort key changed.
	 */
	private function changedCategories( LinksUpdate $linksUpdate ): bool {
		$added = $this->categoryNames( $linksUpdate, LinksTable::INSERTED );
		$removed = $this->categoryNames( $linksUpdate, LinksTable::DELETED );

		return array_diff( $added, $removed ) !== [] || array_diff( $removed, $added ) !== [];
	}

	/**
	 * @return string[]
	 */
	private function categoryNames( LinksUpdate $linksUpdate, int $setType ): array {
		return array_map(
			static fn ( PageReference $category ): string => $category->getDBkey(),
			$linksUpdate->getPageReferenceArray( 'categorylinks', $setType )
		);
	}

	/**
	 * An import, an undeletion or a move projects the page from its own hook, which also covers an import made
	 * without updates and an undeletion that leaves the current revision in place. The links refreshes it has MediaWiki
	 * run for the pages using the page carry its cause too, so this is asked only of an update that is not a
	 * refresh.
	 */
	private function isForAnOperationProjectedFromItsOwnHook(): bool {
		return $this->isAmongSecondaryDataUpdates()
			&& in_array( $this->causeAction, self::CAUSES_PROJECTED_FROM_THEIR_OWN_HOOK, true );
	}

	private function isAmongSecondaryDataUpdates(): bool {
		return $this->rendering !== null;
	}

	public function getAsJobSpecification(): array {
		return [
			'domain' => MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getLocalDomainID(),
			'job' => ProjectPageJob::newSpecification( $this->pageId, isolateFailures: true ),
		];
	}

}
