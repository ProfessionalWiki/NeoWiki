<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Source;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Supplies Subjects from one origin: the local revision slot, an on-wiki
 * SMW/Wikibase store, another NeoWiki, or an external system (ADR 23).
 *
 * Sources play no part at query time: a Subject is Cypher-queryable only once
 * materialised in the graph. Resolution through a Source is by-id only.
 */
interface Source {

	/**
	 * The Subject with the given id, or null when this Source has no such
	 * Subject. The registry has already matched the id's source key to this
	 * Source; how the localId is interpreted is this Source's business.
	 */
	public function getSubject( SubjectId $id ): ?Subject;

	/**
	 * The schema with the given name from this Source, or null when it has
	 * none ("Schemas come from Sources", ADR 23). A schema's source is
	 * independent of a subject's source; T3 builds the reference plumbing.
	 */
	public function getSchema( SchemaName $schemaName ): ?Schema;

	/**
	 * Whether Subjects from this Source are editable through NeoWiki.
	 * Editability is the only capability the model varies: local Subjects
	 * are editable and versioned, sourced Subjects are read-only (ADR 23).
	 */
	public function isEditable(): bool;

	/**
	 * Whether the localId is well-formed for this Source. Each Source owns
	 * its localId grammar (ADR 23); the serialized qualified form further
	 * restricts localIds to URL-path-safe characters at the wire level.
	 */
	public function isValidLocalId( string $localId ): bool;

	/**
	 * Base URI for projecting this Source's ids to IRIs, or null when none
	 * is configured. Native RDF projection mints all IRIs from the single
	 * per-wiki base (NeoWikiConfig->rdfBaseUri, consumed by RdfNamespaces);
	 * a per-Source base URI generalizes that for sourced Subjects. Nothing
	 * consumes a per-source value yet; cross-wiki linking stays deferred
	 * (owl:sameAs, NativeRdfProjection Design Principle 5).
	 */
	public function getBaseUri(): ?string;

}
