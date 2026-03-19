<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use DirectoryIterator;
use FileFetcher\FileFetcher;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\ViewContent;

class ViewContentSource {

	public function __construct(
		private readonly string $directoryPath,
		private readonly FileFetcher $fileFetcher,
	) {
	}

	/**
	 * @return array<string, ViewContent>
	 */
	public function getViews(): array {
		if ( !is_dir( $this->directoryPath ) ) {
			return [];
		}

		$viewContents = [];

		$directoryIterator = new DirectoryIterator( $this->directoryPath );

		/**
		 * @var DirectoryIterator $fileInfo
		 */
		foreach ( $directoryIterator as $fileInfo ) {
			if ( !$fileInfo->isFile() ) {
				continue;
			}

			$viewName = $fileInfo->getBasename( '.json' );
			$viewContent = $this->fileFetcher->fetchFile( $fileInfo->getRealPath() );
			$viewContents[$viewName] = new ViewContent( $viewContent );
		}

		return $viewContents;
	}

}
