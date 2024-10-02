<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages;

use DirectoryIterator;
use FileFetcher\FileFetcher;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content\SchemaContent;

class SchemaContentSource {

	public function __construct(
		private readonly string $directoryPath,
		private readonly FileFetcher $fileFetcher,
	) {
	}

	/**
	 * @return array<string, SchemaContent>
	 */
	public function getSchemas(): array {
		$schemaContents = [];

		$directoryIterator = new DirectoryIterator( $this->directoryPath );

		/**
		 * @var DirectoryIterator $fileInfo
		 */
		foreach ( $directoryIterator as $fileInfo ) {
			if ( !$fileInfo->isFile() ) {
				continue;
			}

			$schemaName = $fileInfo->getBasename( '.json' );
			$schemaContent = $this->getFileContent( $fileInfo->getRealPath() );
			$schemaContents[$schemaName] = new SchemaContent( $schemaContent );
		}

		return $schemaContents;
	}

	private function getFileContent( string $fileName ): string {
		return $this->fileFetcher->fetchFile( $fileName );
	}

}
