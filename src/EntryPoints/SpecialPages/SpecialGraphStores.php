<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Message\Message;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphStoreStatus;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NothingToCancelException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildAlreadyRunningException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\StoreSyncState;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\UnknownGraphStoreException;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Throwable;

/**
 * Shows how far each graph store is from the wiki, and rebuilds one on request.
 *
 * Rendered on the server and read from the run records, so what it shows is what a rebuild has actually
 * recorded rather than a copy of it kept in the browser. A rebuild started here runs on the job queue,
 * so the page comes back at once and progress appears on reload.
 */
class SpecialGraphStores extends SpecialPage {

	private const ACTION_FIELD = 'nwAction';
	private const STORE_FIELD = 'nwStore';
	private const REBUILD_ACTION = 'rebuild';
	private const CANCEL_ACTION = 'cancel';

	public function __construct() {
		parent::__construct( 'GraphStores', NeoWikiExtension::ADMIN_RIGHT );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		if ( $this->getRequest()->wasPosted() ) {
			$this->checkReadOnly();
			$this->act();
			return;
		}

		$this->showReportedOutcome();
		$this->getOutput()->addHTML( $this->buildStoreTable() );
	}

	/**
	 * Carries out a rebuild or a cancellation and sends the browser back to this page to read the result,
	 * so that reloading to watch a rebuild advance never repeats the action that started it.
	 */
	private function act(): void {
		$request = $this->getRequest();
		$storeName = $request->getText( self::STORE_FIELD );

		if ( !$this->newCsrfTokenSet()->matchTokenField() ) {
			$this->redirectWithOutcome( 'sessionfailure', $storeName );
			return;
		}

		$this->redirectWithOutcome( $this->runRequestedAction( $storeName ), $storeName );
	}

	/**
	 * @return string The message key suffix naming what happened, for the box the redirect lands on.
	 */
	private function runRequestedAction( string $storeName ): string {
		$coordinator = NeoWikiExtension::getInstance()->newGraphRebuildCoordinator();
		$cancelling = $this->getRequest()->getText( self::ACTION_FIELD ) === self::CANCEL_ACTION;

		try {
			if ( $cancelling ) {
				$coordinator->cancel( $storeName );
				return 'cancelled';
			}

			$coordinator->startBackground( $storeName, RebuildTrigger::Ui );

			return 'queued';
		} catch ( UnknownGraphStoreException ) {
			return 'unknownstore';
		} catch ( RebuildAlreadyRunningException ) {
			return 'alreadyrunning';
		} catch ( NothingToCancelException ) {
			return 'nothingtocancel';
		} catch ( Throwable $e ) {
			// The queue would not take the batch, or the store's start lock was held, or the queue backend
			// itself is unreachable. All leave the store exactly as it was, and all are worth saying rather
			// than answering with a table that looks unchanged for no stated reason.
			$this->logFailedAction( $storeName, $e );
			return 'notqueued';
		}
	}

	private function logFailedAction( string $storeName, Throwable $e ): void {
		LoggerFactory::getInstance( 'NeoWiki' )->error(
			'NeoWiki could not act on graph store "' . $storeName . '" from Special:GraphStores. The store is '
			. 'unchanged. Underlying error: ' . BackendFailureMessage::withoutCredentials( $e->getMessage() ),
			[ 'exception' => $e, 'store' => $storeName ]
		);
	}

	private function redirectWithOutcome( string $outcome, string $storeName ): void {
		$this->getOutput()->redirect(
			$this->getPageTitle()->getLocalURL( [ 'outcome' => $outcome, 'store' => $storeName ] )
		);
	}

	private function showReportedOutcome(): void {
		$outcome = $this->getRequest()->getText( 'outcome' );

		if ( $outcome === '' ) {
			return;
		}

		$message = $this->msg( 'neowiki-graphstores-outcome-' . $outcome, $this->getRequest()->getText( 'store' ) );

		if ( $message->isDisabled() ) {
			return;
		}

		$this->getOutput()->addHTML(
			in_array( $outcome, [ 'queued', 'cancelled' ], true )
				? Html::successBox( $message->escaped() )
				: Html::errorBox( $message->escaped() )
		);
	}

	private function buildStoreTable(): string {
		$statuses = NeoWikiExtension::getInstance()->newGraphStoreStatusLookup()->getStatuses();

		if ( $statuses === [] ) {
			return Html::rawElement( 'p', [], $this->msg( 'neowiki-graphstores-nostores' )->escaped() );
		}

		$rows = '';

		foreach ( $statuses as $status ) {
			$rows .= $this->buildStoreRow( $status );
		}

		return Html::rawElement( 'p', [], $this->msg( 'neowiki-graphstores-intro' )->escaped() )
			. Html::rawElement( 'table', [ 'class' => 'wikitable' ], $this->buildTableHead() . $rows );
	}

	private function buildTableHead(): string {
		$headings = '';

		foreach ( [ 'store', 'projection', 'state', 'lastrebuild', 'actions' ] as $column ) {
			$headings .= Html::element( 'th', [], $this->msg( 'neowiki-graphstores-column-' . $column )->text() );
		}

		return Html::rawElement( 'tr', [], $headings );
	}

	private function buildStoreRow( GraphStoreStatus $status ): string {
		return Html::rawElement(
			'tr',
			[ 'data-mw-neowiki-graph-store' => $status->name ],
			Html::element( 'td', [], $status->name )
				. Html::element( 'td', [], $status->projection ?? $this->msg( 'neowiki-graphstores-projection-none' )->text() )
				. Html::element( 'td', [], $this->describeState( $status ) )
				. Html::element( 'td', [], $this->describeLastRebuild( $status->lastSuccessfulRun ) )
				. Html::rawElement( 'td', [], $this->buildActions( $status ) )
		);
	}

	/**
	 * What a rebuild is doing right now takes precedence over how out of date the store was before it
	 * started, because that is what an operator reloading the page came to see.
	 */
	private function describeState( GraphStoreStatus $status ): string {
		if ( $status->activeRun !== null ) {
			return $status->activeRun->status === RebuildStatus::Queued
				? $this->msg( 'neowiki-graphstores-state-queued' )->text()
				: $this->msg(
					'neowiki-graphstores-state-running',
					$this->getLanguage()->formatNum( $status->activeRun->processed ),
					$this->getLanguage()->formatNum( $status->activeRun->failed )
				)->text();
		}

		return match ( $status->state ) {
			StoreSyncState::NeverBuilt => $this->msg( 'neowiki-graphstores-state-neverbuilt' )->text(),
			StoreSyncState::Stale => $this->msg(
				'neowiki-graphstores-state-stale',
				$this->formatTime( $status->projectionChanged )
			)->text(),
			StoreSyncState::InSync => $this->msg( 'neowiki-graphstores-state-insync' )->text(),
		};
	}

	private function describeLastRebuild( ?RebuildRun $lastSuccessfulRun ): string {
		if ( $lastSuccessfulRun === null ) {
			return $this->msg( 'neowiki-graphstores-lastrebuild-never' )->text();
		}

		return $this->msg(
			'neowiki-graphstores-lastrebuild',
			$this->formatTime( $lastSuccessfulRun->finished ),
			$this->getLanguage()->formatNum( $lastSuccessfulRun->processed ),
			$this->getLanguage()->formatNum( $lastSuccessfulRun->failed )
		)->text();
	}

	private function formatTime( ?string $timestamp ): string {
		return $timestamp === null
			? ''
			: $this->getLanguage()->userTimeAndDate( $timestamp, $this->getUser() );
	}

	/**
	 * A store with a rebuild queued or going is offered only the way to stop it: starting a second one is
	 * refused anyway, and a button whose only outcome is an error is not worth offering.
	 */
	private function buildActions( GraphStoreStatus $status ): string {
		return $status->activeRun === null
			? $this->buildActionForm( $status->name, self::REBUILD_ACTION, 'neowiki-graphstores-rebuild' )
			: $this->buildActionForm( $status->name, self::CANCEL_ACTION, 'neowiki-graphstores-cancel' );
	}

	private function buildActionForm( string $storeName, string $action, string $labelMessage ): string {
		return Html::rawElement(
			'form',
			[ 'method' => 'post', 'action' => $this->getPageTitle()->getLocalURL() ],
			Html::hidden( 'wpEditToken', $this->newCsrfTokenSet()->getToken()->toString() )
				. Html::hidden( self::STORE_FIELD, $storeName )
				. Html::hidden( self::ACTION_FIELD, $action )
				. Html::submitButton( $this->msg( $labelMessage )->text(), [ 'class' => 'cdx-button' ] )
		);
	}

	/**
	 * Built from the request being handled rather than taken off the context, which resolves it through
	 * whatever context it derives from — the request that made it, not necessarily this one.
	 */
	private function newCsrfTokenSet(): CsrfTokenSet {
		return new CsrfTokenSet( $this->getRequest() );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-graphstores' );
	}

}
