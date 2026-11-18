import { ITEM_TYPES, type ItemType } from '../application/ModelBundle';
import type { ImportItem, ImportPlan } from '../application/importPlan';
import type { ImportOutcome, ItemOutcome } from '../application/importRun';
import { generatedSubjectName } from 'ext.neowiki';

export const TYPE_HEADINGS = {
	schema: 'neowiki-bundle-type-schema',
	layout: 'neowiki-bundle-type-layout',
	mapping: 'neowiki-bundle-type-mapping',
	subject: 'neowiki-bundle-type-subject',
} as const;

interface GroupedItem {
	readonly type: ItemType;
	readonly key: string;
	/** Null for a Subject with neither a label nor a page title. */
	readonly name: string | null;
	readonly schema: string | null;
}

export interface ItemGroup<T> {
	/** Null for the group of Schemas, Layouts or Mappings. */
	readonly schema: string | null;
	readonly items: readonly T[];
}

interface ItemSection<G> {
	readonly type: ItemType;
	readonly groups: readonly G[];
}

export function itemName( item: GroupedItem ): string {
	return item.name ?? generatedSubjectName( item.key );
}

/**
 * The items by type, in the order they are written, and Subjects by Schema, each group by name.
 */
export function itemSections<T extends GroupedItem>( items: readonly T[] ): ItemSection<ItemGroup<T>>[] {
	return ITEM_TYPES
		.map( ( type ) => ( { type, groups: groupsOf( type, items.filter( ( item ) => item.type === type ) ) } ) )
		.filter( ( section ) => section.groups.length > 0 );
}

function groupsOf<T extends GroupedItem>( type: ItemType, items: readonly T[] ): ItemGroup<T>[] {
	const sorted = [ ...items ].sort( ( a, b ) => byText( itemName( a ), itemName( b ) ) );

	if ( sorted.length === 0 ) {
		return [];
	}

	if ( type !== 'subject' ) {
		return [ { schema: null, items: sorted } ];
	}

	return [ ...new Set( sorted.map( ( item ) => item.schema ?? '' ) ) ]
		.sort( byText )
		.map( ( schema ) => ( { schema, items: sorted.filter( ( item ) => ( item.schema ?? '' ) === schema ) } ) );
}

function byText( a: string, b: string ): number {
	return a.localeCompare( b );
}

/**
 * What installing does not settle, which a preview shows without being expanded: what an item would change
 * left unticked, what cannot be installed, and what the bundle no longer has.
 */
export function needsDecision( item: ImportItem ): boolean {
	return item.status === 'conflict' || item.status === 'removed-upstream' || ( item.action !== null && !item.selectedByDefault );
}

const SUMMARY_TYPES = {
	schema: 'neowiki-bundle-summary-schemas',
	layout: 'neowiki-bundle-summary-layouts',
	mapping: 'neowiki-bundle-summary-mappings',
	subject: 'neowiki-bundle-summary-subjects',
} as const;

/**
 * The file's items by type, then what installing it does, as "8 schemas, 30 subjects, all new".
 */
export function planSummary( plan: ImportPlan ): string {
	const fileItems = plan.items.filter( ( item ) => item.upstreamHash !== null );
	const counts = ITEM_TYPES
		.map( ( type ) => [ type, fileItems.filter( ( item ) => item.type === type ).length ] as const )
		.filter( ( [ , count ] ) => count > 0 )
		.map( ( [ type, count ] ) => mw.msg( SUMMARY_TYPES[ type ], count ) );

	return [ ...counts, ...actionSummary( plan.items ) ].join( mw.msg( 'comma-separator' ) );
}

function actionSummary( items: readonly ImportItem[] ): string[] {
	const created = items.filter( ( item ) => item.status === 'new' ).length;
	const updated = items.filter( ( item ) => item.status === 'changed-upstream' ).length;
	const decided = items.filter( needsDecision ).length;
	const left = items.length - created - updated - decided;

	if ( created === items.length ) {
		return [ mw.msg( 'neowiki-bundle-summary-all-new' ) ];
	}

	if ( left === items.length ) {
		return [ mw.msg( 'neowiki-bundle-summary-all-unchanged' ) ];
	}

	const parts: [ string, number ][] = [
		[ 'neowiki-bundle-summary-new', created ],
		[ 'neowiki-bundle-summary-update', updated ],
		[ 'neowiki-bundle-summary-decide', decided ],
		[ 'neowiki-bundle-summary-unchanged', left ],
	];

	// Messages used here:
	// * neowiki-bundle-summary-new
	// * neowiki-bundle-summary-update
	// * neowiki-bundle-summary-decide
	// * neowiki-bundle-summary-unchanged
	return parts.filter( ( [ , count ] ) => count > 0 ).map( ( [ key, count ] ) => mw.msg( key, count ) );
}

export function importStatusText( item: ImportItem ): string {
	if ( item.status === 'conflict' ) {
		return mw.msg( 'neowiki-bundle-status-conflict', item.reason ?? '' );
	}

	if ( item.status === 'removed-upstream' ) {
		return item.reason ?? mw.msg( 'neowiki-bundle-status-removed-upstream' );
	}

	if ( item.status === 'deleted-here' && item.action === 'create' ) {
		return mw.msg( 'neowiki-bundle-status-deleted-here-changed-upstream' );
	}

	// Messages used here:
	// * neowiki-bundle-status-new
	// * neowiki-bundle-status-declined
	// * neowiki-bundle-status-unchanged
	// * neowiki-bundle-status-differs
	// * neowiki-bundle-status-changed-upstream
	// * neowiki-bundle-status-changed-both
	// * neowiki-bundle-status-changed-here
	// * neowiki-bundle-status-deleted-here
	return mw.msg( `neowiki-bundle-status-${ item.status }` );
}

/**
 * What a row of the preview says besides its status, each on a line of its own.
 */
export function importNotes( item: ImportItem ): string[] {
	return item.pageTitleTaken ? [ mw.msg( 'neowiki-bundle-page-title-taken', item.pageTitle ?? '' ) ] : [];
}

interface ResultLine {
	readonly type: 'success' | 'warning' | 'error';
	readonly text: string;
	/** How many items were not written; null when all were. */
	readonly note: string | null;
}

/**
 * What an install or update came to, on one line, as "Installed model bundle Vocabularies: 2 items created". When
 * the bundle could not be recorded as installed, the line says that instead.
 */
export function resultLine( plan: ImportPlan, outcome: ImportOutcome ): ResultLine {
	const failed = outcome.items.filter( ( item ) => item.result === 'failed' ).length;
	const note = failed === 0 ? null : mw.msg( 'neowiki-bundle-not-written', failed );

	if ( !outcome.record.saved ) {
		return { type: 'error', text: mw.msg( 'neowiki-bundle-record-failed', plan.bundleName, outcome.record.reason ), note };
	}

	return {
		type: failed === 0 ? 'success' : 'warning',
		text: doneText( plan, plan.bundleName ) + mw.msg( 'colon-separator' ) + writtenCounts( outcome.items ),
		note,
	};
}

const WRITTEN_COUNTS = {
	created: 'neowiki-bundle-count-created',
	replaced: 'neowiki-bundle-count-replaced',
	removed: 'neowiki-bundle-count-removed',
} as const;

function writtenCounts( outcomes: readonly ItemOutcome[] ): string {
	const counts = ( [ 'created', 'replaced', 'removed' ] as const )
		.map( ( result ) => [ result, outcomes.filter( ( outcome ) => outcome.result === result ).length ] as const )
		.filter( ( [ , count ] ) => count > 0 )
		// Messages used here:
		// * neowiki-bundle-count-created
		// * neowiki-bundle-count-replaced
		// * neowiki-bundle-count-removed
		.map( ( [ result, count ] ) => mw.msg( WRITTEN_COUNTS[ result ], count ) );

	return counts.length === 0 ? mw.msg( 'neowiki-bundle-count-nothing' ) : counts.join( mw.msg( 'comma-separator' ) );
}

function doneText( plan: ImportPlan, bundleName: string ): string {
	if ( plan.record === null ) {
		return mw.msg( 'neowiki-bundle-installed', bundleName );
	}

	const version = plan.bundle.metadata?.version ?? '';

	return version === '' ?
		mw.msg( 'neowiki-bundle-updated-unversioned', bundleName ) :
		mw.msg( 'neowiki-bundle-updated', bundleName, version );
}
