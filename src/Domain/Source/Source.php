<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Source;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * Where a Subject comes from (ADR 23). The local revision slot is one Source; an extension may register
 * others — another wiki of a farm, an on-wiki SMW or Wikibase store, a remote instance.
 *
 * A Source is the authority on its own Subjects: how to fetch one, what its localIds look like, which
 * Schemas it offers, and whether it may be edited. Generic code never reaches past this contract into a
 * Source's storage.
 *
 * **Sources play no part at query time.** A Subject is queryable once materialised in a graph store, and
 * materialisation is the only gate; a query never consults the registry or a Source. This is why the
 * contract has no query method, and why fetch-by-id and query can disagree about a Source whose data is
 * fetchable but not materialised.
 *
 * There is deliberately **no write capability**. Writing back to a Source is end-of-roadmap (ADR 23, Open
 * questions), and a stub for it would be a contract nobody can implement against. Editability is exposed
 * because callers act on it today; write-back adds its own method when it is built.
 *
 * Availability is not part of the contract: a Source that cannot reach its backing store answers as
 * though the Subject or Schema is absent, so a page degrades rather than breaking.
 */
interface Source {

	public function getSubject( SubjectId $id ): ?Subject;

	/**
	 * Ids that resolve to no Subject are absent from the map, so its size is the number found.
	 */
	public function getSubjects( SubjectIdList $ids ): SubjectMap;

	/**
	 * A Schema this Source offers, or null when it has none by that name. A Subject's Schema is resolved
	 * through the Schema's own Source, which need not be the Subject's (ADR 23).
	 */
	public function getSchema( SchemaName $name ): ?Schema;

	/**
	 * Whether this Source's Subjects can be changed through NeoWiki. The only capability the model
	 * varies: local Subjects are editable and versioned, sourced ones are read-only.
	 */
	public function isEditable(): bool;

	/**
	 * Whether $localId is one this Source could have minted. Each Source owns its grammar: the local one
	 * mints nanoids (ADR 14), a Wikibase adapter item ids, a remote instance whatever it uses.
	 */
	public function isValidLocalId( string $localId ): bool;

	/**
	 * The IRI prefix under which this Source's Subjects are named in RDF: a Subject's IRI is this
	 * concatenated with its localId.
	 */
	public function getBaseUri(): string;

}
