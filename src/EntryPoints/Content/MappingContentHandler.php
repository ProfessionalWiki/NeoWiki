<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingContentValidator;
use ProfessionalWiki\NeoWiki\Presentation\MappingPageHtmlBuilder;
use ProfessionalWiki\NeoWiki\Presentation\MappingPageRendering;
use StatusValue;
use stdClass;

class MappingContentHandler extends JsonContentHandler {

	protected function getContentClass(): string {
		return MappingContent::class;
	}

	public function validateSave( Content $content, ValidationParams $validationParams ): StatusValue {
		$status = parent::validateSave( $content, $validationParams );

		if ( !$status->isOK() ) {
			return $status;
		}

		$title = Title::newFromPageIdentity( $validationParams->getPageIdentity() );

		// The page title is the target/projection name, so a reserved name (native) is rejected here.
		try {
			new MappingName( $title->getText() );
		} catch ( InvalidArgumentException $exception ) {
			$status->fatal( 'neowiki-mapping-name-invalid', $exception->getMessage() );
		}

		$validator = MappingContentValidator::newInstance( MediaWikiServices::getInstance()->getTitleParser() );

		if ( !$validator->validate( $content->getText() ) ) {
			$status->fatal( 'neowiki-mapping-invalid', count( $validator->getErrors() ) );

			foreach ( $validator->getErrors() as $pointer => $message ) {
				$status->fatal( 'neowiki-mapping-invalid-detail', $pointer, $message );
			}
		}

		return $status;
	}

	/**
	 * Renders the Mapping as a header (mapped-schemas overview, prefixes, format version) plus one section
	 * per schema whose body stays the default mw-json table. Falls back to the inherited JSON rendering when
	 * the content does not parse or is not the expected shape (XML import and other format versions bypass
	 * save validation), so display never fatals.
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		if ( $cpoParams->getGenerateHtml() && $content instanceof MappingContent ) {
			$mapping = $content->getData()->getValue();

			if ( $this->isRenderableMapping( $mapping ) ) {
				$this->renderMapping( $content, $mapping, $cpoParams->getPage(), $parserOutput );
				return;
			}

			parent::fillParserOutput( $content, $cpoParams, $parserOutput );
			$this->addUnsupportedVersionNotice( $mapping, $parserOutput );
			return;
		}

		parent::fillParserOutput( $content, $cpoParams, $parserOutput );
	}

	/**
	 * A page written in another format version renders as plain JSON, which on its own looks like an
	 * ordinary Mapping page even though it defines no projection and every export of it fails. Saying so
	 * is the only signal the reader gets.
	 */
	private function addUnsupportedVersionNotice( mixed $mapping, ParserOutput $parserOutput ): void {
		$version = $mapping instanceof stdClass ? ( $mapping->version ?? null ) : null;

		if ( !is_scalar( $version ) || $version === Mapping::FORMAT_VERSION ) {
			return;
		}

		$parserOutput->setContentHolderText(
			Html::warningBox(
				wfMessage( 'neowiki-mapping-unsupported-version' )
					->params( (string)$version )
					->inContentLanguage()
					->escaped()
			) . $parserOutput->getContentHolderText()
		);
		$parserOutput->addModuleStyles( [ 'mediawiki.codex.messagebox.styles' ] );
	}

	private function renderMapping(
		MappingContent $content,
		stdClass $mapping,
		PageReference $page,
		ParserOutput $parserOutput
	): void {
		$services = MediaWikiServices::getInstance();

		$builder = new MappingPageHtmlBuilder(
			$services->getLinkRenderer(),
			$services->getTitleFactory(),
			$services->getLinkBatchFactory(),
			TitleValue::newFromPage( $page ),
			static fn ( mixed $subtree ): string => $content->rootValueTable( $subtree )
		);

		$rendering = $builder->build( $mapping );

		$parserOutput->setContentHolderText( $rendering->html );
		$this->registerLinks( $parserOutput, $rendering );
		$parserOutput->addModuleStyles( [ 'mediawiki.content.json', 'ext.neowiki.styles' ] );
	}

	private function registerLinks( ParserOutput $parserOutput, MappingPageRendering $rendering ): void {
		foreach ( $rendering->schemaLinks as $schemaLink ) {
			$parserOutput->addLink( $schemaLink );
		}

		foreach ( $rendering->externalLinks as $externalLink ) {
			$parserOutput->addExternalLink( $externalLink );
		}
	}

	/**
	 * @phpstan-assert-if-true stdClass $mapping
	 */
	private function isRenderableMapping( mixed $mapping ): bool {
		return $mapping instanceof stdClass
			&& ( $mapping->version ?? null ) === Mapping::FORMAT_VERSION
			&& isset( $mapping->schemas )
			&& $mapping->schemas instanceof stdClass;
	}

	public function makeEmptyContent(): MappingContent {
		$version = Mapping::FORMAT_VERSION;

		return new MappingContent( <<<JSON
{
	"version": {$version},
	"prefixes": {},
	"schemas": {}
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->getNamespace() === NeoWikiExtension::NS_MAPPING;
	}

}
