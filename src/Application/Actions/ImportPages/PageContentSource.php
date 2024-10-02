<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages;

use DirectoryIterator;
use FileFetcher\FileFetcher;

class PageContentSource {

	/**
	 * @param string[] $directoryPaths
	 */
	public function __construct(
		private readonly array $directoryPaths,
		private readonly FileFetcher $fileFetcher,
	) {
	}

	/**
	 * @return array<string, string>
	 */
	public function getPageContentStrings(): array {
		$pageContent = [];

		foreach ( $this->directoryPaths as $path ) {
			$directoryIterator = new DirectoryIterator( $path );

			/**
			 * @var DirectoryIterator $fileInfo
			 */
			foreach ( $directoryIterator as $fileInfo ) {
				if ( !$fileInfo->isFile() ) {
					continue;
				}

				$pageContent[$fileInfo->getFilename()] = $this->getFileContent( $fileInfo->getRealPath() );
			}
		}

		return $pageContent;
	}

	private function getFileContent( string $fileName ): string {
		return $this->fileFetcher->fetchFile( $fileName );
	}

}
