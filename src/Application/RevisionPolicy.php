<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

/**
 * Which revision of a page NeoWiki publishes: projects to the graph stores, exports as RDF, and reads
 * Schemas, Layouts, Mappings and the on-wiki configuration from. Registered by an approval extension
 * such as ContentStabilization, which shows readers an approved revision rather than the newest one.
 * With no policy registered every page publishes its latest revision.
 *
 * publishedRevision() runs on every Schema, Layout and Mapping read and every RDF export, as well as
 * whenever a page is written or reprojected, and must be cheap.
 *
 * A policy answers for the wiki, not for a viewer: the graph and the RDF export are one state that
 * every reader sees. Only revisionIsReadableBy(), which serves a single request, takes a viewer.
 */
interface RevisionPolicy {

	/**
	 * The revision to publish for this revision's page. Return the argument to publish what was
	 * written, or null when the page has nothing publishable, which withdraws it from the graph
	 * stores and the RDF export.
	 */
	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord;

	/**
	 * Whether the viewer may read a revision they asked for by id. publishedRevision() cannot answer
	 * this: naming a revision bypasses it.
	 */
	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool;

}
