import { ITEM_TYPES, type ItemType } from '@/application/modelBundle/ModelBundle';
import type { ImportItem, ImportPlan } from '@/application/modelBundle/importPlan';
import type { ImportOutcome, ItemOutcome, ItemResult } from '@/application/modelBundle/importRun';
import { generatedSubjectName } from '@/presentation/subjectDisplayName';

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

export interface PreviewGroup extends ItemGroup<ImportItem> {
	/** What it leaves as it is, unchanged on either side, which needs no decision. */
	readonly quietItems: readonly ImportItem[];
}

/**
 * As itemSections(), with each group's items that need no decision set apart.
 */
export function previewSections( items: readonly ImportItem[] ): ItemSection<PreviewGroup>[] {
	return itemSections( items ).map( ( section ) => ( {
		type: section.type,
		groups: section.groups.map( ( group ) => ( {
			schema: group.schema,
			items: group.items.filter( ( item ) => !isQuiet( item ) ),
			quietItems: group.items.filter( isQuiet ),
		} ) ),
	} ) );
}

/**
 * What this wiki changed or deleted stays in view, as the reader should know the import leaves it so.
 */
function isQuiet( item: ImportItem ): boolean {
	return item.action === null && item.reason === null && item.status === 'unchanged';
}

/**
 * A bundle install says what installing does, a plain import what importing does.
 */
export function importStatusText( item: ImportItem, installs: boolean ): string {
	if ( item.status === 'conflict' ) {
		return mw.msg( installs ? 'neowiki-bundle-status-conflict-install' : 'neowiki-bundle-status-conflict', item.reason ?? '' );
	}

	if ( item.status === 'removed-upstream' ) {
		return item.reason ?? mw.msg( 'neowiki-bundle-status-removed-upstream' );
	}

	if ( item.status === 'deleted-here' && item.action === 'create' ) {
		return mw.msg( 'neowiki-bundle-status-deleted-here-changed-upstream' );
	}

	if ( item.status === 'differs' && installs ) {
		return mw.msg( 'neowiki-bundle-status-differs-install' );
	}

	// Messages used here:
	// * neowiki-bundle-status-new
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
	return [
		...( item.pageTitleTaken ? [ mw.msg( 'neowiki-bundle-page-title-taken', item.pageTitle ?? '' ) ] : [] ),
		...( item.owner !== null && item.status !== 'conflict' ? [ mw.msg( 'neowiki-bundle-belongs-to', item.owner ) ] : [] ),
	];
}

interface ResultGroup {
	readonly result: ItemResult;
	readonly outcomes: readonly ItemOutcome[];
}

/**
 * What an import did, failures first, as they are what the reader has to act on.
 */
export function resultGroups( outcomes: readonly ItemOutcome[] ): ResultGroup[] {
	const results: ItemResult[] = [ 'failed', 'created', 'replaced', 'removed' ];

	return results
		.map( ( result ) => ( { result, outcomes: outcomes.filter( ( outcome ) => outcome.result === result ) } ) )
		.filter( ( resultGroup ) => resultGroup.outcomes.length > 0 );
}

interface ResultHeadline {
	readonly type: 'success' | 'warning';
	readonly text: string;
	/** How many items were not written; null when all were. */
	readonly note: string | null;
}

/**
 * What an install or update came to, once the bundle is recorded as installed. Null for a plain import, which
 * records nothing, and when the record could not be saved, which is told after the items.
 */
export function resultHeadline( plan: ImportPlan, outcome: ImportOutcome ): ResultHeadline | null {
	if ( plan.bundleName === null || outcome.record?.saved !== true ) {
		return null;
	}

	const failed = outcome.items.filter( ( item ) => item.result === 'failed' ).length;

	return {
		type: failed === 0 ? 'success' : 'warning',
		text: doneText( plan, plan.bundleName ),
		note: failed === 0 ? null : mw.msg( 'neowiki-bundle-not-written', failed ),
	};
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
