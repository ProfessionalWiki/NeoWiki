<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use DirectoryIterator;
use FileFetcher\FileFetcher;

class SubjectPageSource {

	public function __construct(
		private readonly string $directoryPath,
		private readonly FileFetcher $fileFetcher,
	) {
	}

	/**
	 * @return SubjectPageData[]
	 */
	public function getSubjectPages(): array {
		$subjectPages = [];

		$directoryIterator = new DirectoryIterator( $this->directoryPath );

		/**
		 * @var DirectoryIterator $fileInfo
		 */
		foreach ( $directoryIterator as $fileInfo ) {
			if ( $fileInfo->isFile() && $fileInfo->getExtension() === 'json' ) {
				$subjectPages[] = $this->newSubjectPageData( $fileInfo );
			}
		}

		return $subjectPages;
	}

	private function newSubjectPageData( DirectoryIterator $fileInfo ): SubjectPageData {
		$pageName = $fileInfo->getBasename( '.json' );

		return new SubjectPageData(
			$pageName,
			$this->getWikitext( $pageName ),
			$this->fileFetcher->fetchFile( $fileInfo->getRealPath() )
		);
	}

	private function getWikitext( string $pageName ): string {
		$wikitextFilePath = $this->directoryPath . '/' . $pageName . '.wikitext';
		return is_file( $wikitextFilePath ) ? $this->fileFetcher->fetchFile( $wikitextFilePath ) : '';
	}

}
