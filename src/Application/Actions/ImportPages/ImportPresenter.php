<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

interface ImportPresenter {

	public function presentDone(): void;

	public function presentImportStarted( string $pageTitle ): void;

	public function presentCreatedRevision( string $pageTitle ): void;

	public function presentNoChanges( string $pageTitle ): void;

	public function presentImportFailed( string $pageTitle, string $errorMessage ): void;

}
