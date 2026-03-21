<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use DirectoryIterator;
use FileFetcher\FileFetcher;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;

class LayoutContentSource {

	public function __construct(
		private readonly string $directoryPath,
		private readonly FileFetcher $fileFetcher,
	) {
	}

	/**
	 * @return array<string, LayoutContent>
	 */
	public function getLayouts(): array {
		if ( !is_dir( $this->directoryPath ) ) {
			return [];
		}

		$layoutContents = [];

		$directoryIterator = new DirectoryIterator( $this->directoryPath );

		/**
		 * @var DirectoryIterator $fileInfo
		 */
		foreach ( $directoryIterator as $fileInfo ) {
			if ( !$fileInfo->isFile() ) {
				continue;
			}

			$layoutName = $fileInfo->getBasename( '.json' );
			$layoutContent = $this->fileFetcher->fetchFile( $fileInfo->getRealPath() );
			$layoutContents[$layoutName] = new LayoutContent( $layoutContent );
		}

		return $layoutContents;
	}

}
