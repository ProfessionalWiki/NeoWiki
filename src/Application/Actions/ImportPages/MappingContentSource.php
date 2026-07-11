<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\ImportPages;

use DirectoryIterator;
use FileFetcher\FileFetcher;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;

class MappingContentSource {

	public function __construct(
		private readonly string $directoryPath,
		private readonly FileFetcher $fileFetcher,
	) {
	}

	/**
	 * @return array<string, MappingContent>
	 */
	public function getMappings(): array {
		if ( !is_dir( $this->directoryPath ) ) {
			return [];
		}

		$mappingContents = [];

		$directoryIterator = new DirectoryIterator( $this->directoryPath );

		/**
		 * @var DirectoryIterator $fileInfo
		 */
		foreach ( $directoryIterator as $fileInfo ) {
			if ( !$fileInfo->isFile() ) {
				continue;
			}

			$mappingName = $fileInfo->getBasename( '.json' );
			$mappingContents[$mappingName] = new MappingContent( $this->fileFetcher->fetchFile( $fileInfo->getRealPath() ) );
		}

		return $mappingContents;
	}

}
