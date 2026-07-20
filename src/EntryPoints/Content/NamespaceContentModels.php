<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * The single source of truth binding each of NeoWiki's JSON namespaces to the one content model it is
 * locked to. Both the ContentModelCanBeUsedOn hook and the content handlers derive their lock from this
 * map, so a namespace added here is protected everywhere at once instead of in several hand-kept lists.
 */
class NamespaceContentModels {

	/**
	 * @return array<int, string> Namespace id => content model id.
	 */
	public static function map(): array {
		return [
			NeoWikiExtension::NS_SCHEMA => SchemaContent::CONTENT_MODEL_ID,
			NeoWikiExtension::NS_LAYOUT => LayoutContent::CONTENT_MODEL_ID,
			NeoWikiExtension::NS_MAPPING => MappingContent::CONTENT_MODEL_ID,
		];
	}

	/**
	 * The content model the given namespace is locked to, or null when the namespace is not one of NeoWiki's.
	 */
	public static function forNamespace( int $namespace ): ?string {
		return self::map()[$namespace] ?? null;
	}

}
