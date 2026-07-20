<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPresenter;

class ImportPresenterSpy implements ImportPresenter {

	/**
	 * @var string[]
	 */
	public array $created = [];

	/**
	 * @var string[]
	 */
	public array $noChanges = [];

	/**
	 * @var string[]
	 */
	public array $importFailures = [];

	/**
	 * @var string[]
	 */
	public array $deletionsStarted = [];

	/**
	 * @var string[]
	 */
	public array $deleted = [];

	/**
	 * @var string[]
	 */
	public array $deletionFailures = [];

	public bool $done = false;

	public function presentDone(): void {
		$this->done = true;
	}

	public function presentImportStarted( string $pageTitle ): void {
	}

	public function presentCreatedRevision( string $pageTitle ): void {
		$this->created[] = $pageTitle;
	}

	public function presentNoChanges( string $pageTitle ): void {
		$this->noChanges[] = $pageTitle;
	}

	public function presentImportFailed( string $pageTitle, string $errorMessage ): void {
		$this->importFailures[] = $pageTitle;
	}

	public function presentDeletionStarted( string $pageTitle ): void {
		$this->deletionsStarted[] = $pageTitle;
	}

	public function presentDeleted( string $pageTitle ): void {
		$this->deleted[] = $pageTitle;
	}

	public function presentDeletionFailed( string $pageTitle, string $errorMessage ): void {
		$this->deletionFailures[] = $pageTitle;
	}

}
