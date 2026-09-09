<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

/**
 * Which revision of a page NeoWiki publishes: projects to the graph stores, exports as RDF, and reads
 * Schemas, Layouts, Mappings and the on-wiki configuration from. Registered by an approval extension
 * such as ContentStabilization, which shows readers an approved revision rather than the newest one.
 * With no policy registered every page publishes its latest revision, as NeoWiki has always done.
 *
 * A page save knows its revision and only needs to be told whether to publish it. A reprojection, a
 * configuration read and an RDF export are told nothing beyond the page, so they ask which revision
 * to publish. publishedRevision() therefore runs on every Schema, Layout and Mapping read and every
 * RDF export, not only on a rebuild, and an implementation must be cheap enough for that.
 *
 * The answers must agree: publishesRevision() is true for whatever publishedRevision() names, since a
 * reprojection hands the named revision back to the save path's check.
 *
 * A policy answers for the wiki, not for a viewer: the graph and the RDF export are one state that
 * every reader sees. Only revisionIsReadableBy(), which serves a single request, takes a viewer.
 *
 * The subject-to-page index is deliberately not governed by any of this. It records where a Subject
 * lives, not whether it is published, and every read of it is re-checked against the revision the
 * caller actually reads ([[ADR 32]]).
 */
interface RevisionPolicy {

	/**
	 * Whether this revision may be published, asked as it is written. False leaves the graph holding
	 * whatever it published before, which for an approval extension is the last approved revision.
	 */
	public function publishesRevision( RevisionRecord $revision ): bool;

	/**
	 * The revision to publish for this revision's page, asked when a page is reprojected rather than
	 * written. Null when the page has nothing publishable. Return the argument to leave it alone.
	 */
	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord;

	/**
	 * Whether the viewer may read a revision they asked for by id. Neither publishing method can
	 * answer this: naming a revision bypasses both.
	 */
	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool;

}
