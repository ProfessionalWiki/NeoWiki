import { normalizeSchemaName } from 'ext.neowiki';
import {
	canonicalJson,
	itemHash,
	itemText,
	schemaItem,
	subjectItem,
	subjectsAreDependent,
} from './canonical';
import {
	type DependentSubjectEntry,
	itemId,
	type ItemType,
	type JsonObject,
	type ModelBundle,
	type SubjectEntry,
} from './ModelBundle';
import { type InstallRecord, type RecordedItem, recordedHash, recordedItems } from './InstallRecord';
import type { CurrentState, CurrentStateReader } from './CurrentStateReader';
import { mapConcurrently } from './mapConcurrently';
import type { DataModelRepository } from './DataModelRepository';
import { sameBundleName } from './bundleName';

/**
 * - new: the wiki does not have it
 * - declined: the wiki does not have it, and an earlier install or update left it unticked
 * - unchanged: the wiki has it as the file does
 * - differs: the wiki has it otherwise, and no record says where that version came from
 * - changed-upstream: the bundle changed it since the installed version, and this wiki did not
 * - changed-both: the bundle and this wiki both changed it
 * - changed-here: this wiki changed it, and the bundle did not
 * - deleted-here: the installed version had it, and this wiki deleted it
 * - removed-upstream: the installed version had it, and the file does not
 * - conflict: it cannot be written; the reason says why
 */
export type ImportStatus = 'new' | 'declined' | 'unchanged' | 'differs' | 'changed-upstream' | 'changed-both' | 'changed-here' |
	'deleted-here' | 'removed-upstream' | 'conflict';

type ImportAction = 'create' | 'replace' | 'remove';

export type ItemContent =
	| { readonly type: 'schema' | 'layout' | 'mapping'; readonly json: JsonObject }
	| { readonly type: 'subject'; readonly entry: SubjectEntry };

export interface ImportItem {
	readonly id: string;
	readonly type: ItemType;
	readonly key: string;
	/** Its name, or a Subject's label, else its page's title; null for a Subject with neither. */
	readonly name: string | null;
	readonly schema: string | null;
	readonly status: ImportStatus;
	/** What importing the item does; null when there is nothing it may do. */
	readonly action: ImportAction | null;
	readonly selectedByDefault: boolean;
	readonly reason: string | null;
	/** For a Subject to create: the title to give its page, or null to leave it to the wiki. */
	readonly pageTitle: string | null;
	/** Whether the title the Subject's page would take is taken, so that the page takes its ID instead. */
	readonly pageTitleTaken: boolean;
	/** The wiki's version and the file's, where both exist and differ. */
	readonly versionHere: string | null;
	readonly versionUpstream: string | null;
	readonly content: ItemContent | null;
	readonly upstreamHash: string | null;
	/** The bundle's version of the item when it was last installed or updated. */
	readonly recorded: RecordedItem | null;
	/** Of the page of a Schema, Layout or Mapping the wiki has, as the plan read it, for the write to save over. */
	readonly revisionId: number | null;
}

export interface ImportPlan {
	readonly bundle: ModelBundle;
	readonly bundleName: string;
	/** Null for a first install. */
	readonly record: InstallRecord | null;
	readonly items: readonly ImportItem[];
}

interface PlanContext {
	readonly reader: CurrentStateReader;
	readonly repository: DataModelRepository;
	readonly records: readonly InstallRecord[];
	readonly subjectFirst: boolean;
}

interface Upstream {
	readonly type: ItemType;
	readonly key: string;
	readonly content: ItemContent;
	readonly item: JsonObject;
	readonly hash: string;
}

/**
 * The wiki is read through the context's reader, which keeps what it read.
 */
export function planImport( bundle: ModelBundle, bundleName: string, context: PlanContext ): Promise<ImportPlan> {
	return planBundle( bundle, bundleName, context, [] );
}

/**
 * Plans bundles installed together, in the order given, which puts a bundle after those it requires: what an
 * earlier bundle provides counts as present for a later one, and belongs to the earlier one.
 */
export async function planImports(
	bundles: readonly { readonly bundle: ModelBundle; readonly name: string }[],
	context: PlanContext,
): Promise<ImportPlan[]> {
	const plans: ImportPlan[] = [];

	for ( const { bundle, name } of bundles ) {
		plans.push( await planBundle( bundle, name, context, [ ...plans ] ) );
	}

	return plans;
}

async function planBundle(
	bundle: ModelBundle,
	bundleName: string,
	context: PlanContext,
	earlier: readonly ImportPlan[],
): Promise<ImportPlan> {
	const record = context.records.find( ( installed ) => sameBundleName( installed.name, bundleName ) ) ?? null;
	const recorded = recordedItems( record );
	const declined = new Set( ( record?.declined ?? [] ).map( ( item ) => itemId( item.type, item.key ) ) );
	const owners = new Map( [ ...ownersOtherThan( context.records, record ), ...ownersIn( earlier ) ] );
	const upstream = upstreamItems( bundle );
	const removed = [ ...recorded.values() ].filter( ( item ) => !upstream.has( itemId( item.type, item.key ) ) );
	const dependents = dependentSubjects( bundle );

	const states = await context.reader.readAll( [
		...upstream.values(),
		...removed,
		...dependents.map( ( dependent ) => ( { type: 'subject' as const, key: dependent.id } ) ),
		...dependents.map( ( dependent ) => ( { type: 'schema' as const, key: dependent.schema } ) ),
	] );

	const fileItems = withDependentSchemasChecked( [ ...upstream.values() ].map( ( item ) => classifyUpstream(
		item,
		states,
		recorded.get( itemId( item.type, item.key ) ) ?? null,
		declined.has( itemId( item.type, item.key ) ),
		owners.get( itemId( item.type, item.key ) ) ?? null,
	) ), states, earlier );

	const removedItems = await classifyRemoved( removed, states, bundle, recorded, context.repository );

	return {
		bundle,
		bundleName,
		record,
		items: await withPageTitles( [ ...fileItems, ...removedItems ].sort( byKey ), context ),
	};
}

/**
 * Within each type, items are written in the order of their names, or of their IDs.
 */
function byKey( a: ImportItem, b: ImportItem ): number {
	return a.key.localeCompare( b.key );
}

function upstreamItems( bundle: ModelBundle ): Map<string, Upstream> {
	const items = [
		...[ ...bundle.schemas ].map( ( [ key, json ] ) => upstreamItem( key, { type: 'schema', json }, schemaItem( json ) ) ),
		...[ ...bundle.layouts ].map( ( [ key, json ] ) => upstreamItem( key, { type: 'layout', json }, canonicalJson( json ) ) ),
		...[ ...bundle.mappings ].map( ( [ key, json ] ) => upstreamItem( key, { type: 'mapping', json }, canonicalJson( json ) ) ),
		...[ ...bundle.subjects ].map( ( [ key, entry ] ) => upstreamItem( key, { type: 'subject', entry }, subjectItem( entry ) ) ),
	];

	return new Map( items.map( ( item ) => [ itemId( item.type, item.key ), item ] ) );
}

function upstreamItem( key: string, content: ItemContent, item: JsonObject ): Upstream {
	return { type: content.type, key, content, item, hash: itemHash( item ) };
}

function ownersOtherThan( records: readonly InstallRecord[], own: InstallRecord | null ): Map<string, string> {
	return new Map( records
		.filter( ( record ) => record !== own )
		.flatMap( ( record ) => record.items.map( ( item ) => [ itemId( item.type, item.key ), record.name ] as const ) ) );
}

function ownersIn( plans: readonly ImportPlan[] ): Map<string, string> {
	return new Map( plans.flatMap( ( plan ) => plan.items
		.filter( ( item ) => item.content !== null )
		.map( ( item ) => [ item.id, plan.bundleName ] as const ) ) );
}

function dependentSubjects( bundle: ModelBundle ): DependentSubjectEntry[] {
	return [ ...bundle.subjects.values() ].flatMap( ( entry ) => entry.dependentSubjects );
}

function classifyUpstream(
	upstream: Upstream,
	states: Map<string, CurrentState>,
	recorded: RecordedItem | null,
	declined: boolean,
	owner: string | null,
): ImportItem {
	const state = states.get( itemId( upstream.type, upstream.key ) ) as CurrentState;
	const item = {
		...baseItem( upstream.type, upstream.key, upstreamName( upstream ), upstreamSchema( upstream ) ),
		content: upstream.content,
		upstreamHash: upstream.hash,
		recorded,
		revisionId: state.revisionId,
		...versions( state, upstream ),
	};
	const reason = conflictOf( upstream, state, states, owner );

	if ( reason !== null ) {
		return { ...item, status: 'conflict', reason };
	}

	return { ...item, ...comparison( state.hash, upstream.hash, recorded === null ? null : recordedHash( recorded ), declined ) };
}

type Comparison = Pick<ImportItem, 'status' | 'action' | 'selectedByDefault'>;

function comparison( here: string | null, upstream: string, recorded: string | null, declined: boolean ): Comparison {
	if ( recorded === null ) {
		if ( here === null ) {
			return declined ?
				{ status: 'declined', action: 'create', selectedByDefault: false } :
				{ status: 'new', action: 'create', selectedByDefault: true };
		}

		return here === upstream ?
			{ status: 'unchanged', action: null, selectedByDefault: false } :
			{ status: 'differs', action: 'replace', selectedByDefault: false };
	}

	if ( upstream === recorded ) {
		if ( here === null ) {
			return { status: 'deleted-here', action: null, selectedByDefault: false };
		}

		return here === recorded ?
			{ status: 'unchanged', action: null, selectedByDefault: false } :
			{ status: 'changed-here', action: null, selectedByDefault: false };
	}

	if ( here === null ) {
		return { status: 'deleted-here', action: 'create', selectedByDefault: false };
	}

	if ( here === upstream ) {
		return { status: 'unchanged', action: null, selectedByDefault: false };
	}

	return here === recorded ?
		{ status: 'changed-upstream', action: 'replace', selectedByDefault: true } :
		{ status: 'changed-both', action: 'replace', selectedByDefault: false };
}

function conflictOf(
	upstream: Upstream,
	state: CurrentState,
	states: Map<string, CurrentState>,
	owner: string | null,
): string | null {
	if ( state.error !== null ) {
		return mw.msg( 'neowiki-bundle-conflict-unreadable', state.error );
	}

	if ( owner !== null ) {
		return mw.msg( 'neowiki-bundle-conflict-owned', owner );
	}

	if ( upstream.type === 'schema' && state.item !== null &&
		subjectsAreDependent( state.item ) !== subjectsAreDependent( upstream.item ) ) {
		return mw.msg( 'neowiki-bundle-conflict-dependence' );
	}

	if ( upstream.content.type === 'subject' ) {
		return subjectConflictOf( upstream.key, upstream.content.entry, state, states );
	}

	return null;
}

/**
 * A replace keeps a Subject's Schema whatever it is sent, and a write refuses a Dependent Subject ID that
 * another Subject holds.
 */
function subjectConflictOf(
	id: string,
	entry: SubjectEntry,
	state: CurrentState,
	states: Map<string, CurrentState>,
): string | null {
	if ( state.item !== null && !sameSchema( state.schema, entry.schema ) ) {
		return mw.msg( 'neowiki-bundle-conflict-subject-schema', state.schema ?? '' );
	}

	if ( state.item !== null && state.hostRootId !== null ) {
		return mw.msg( 'neowiki-bundle-conflict-id-in-use', id );
	}

	for ( const dependent of entry.dependentSubjects ) {
		const dependentState = states.get( itemId( 'subject', dependent.id ) ) as CurrentState;

		if ( dependentState.error !== null ) {
			return mw.msg( 'neowiki-bundle-conflict-unreadable', dependentState.error );
		}

		const heldByThisSubject = dependentState.hostRootId === id && sameSchema( dependentState.schema, dependent.schema );

		if ( dependentState.item !== null && !heldByThisSubject ) {
			return mw.msg( 'neowiki-bundle-conflict-id-in-use', dependent.id );
		}
	}

	return null;
}

/**
 * The wiki refuses a Dependent Subject whose Schema it does not have as one of Dependent Subjects, so a
 * Subject to write with one is in conflict, unless the import, or an earlier bundle of it, creates that Schema.
 */
function withDependentSchemasChecked(
	items: ImportItem[],
	states: Map<string, CurrentState>,
	earlier: readonly ImportPlan[],
): ImportItem[] {
	const created = new Set( [ ...earlier.flatMap( ( plan ) => plan.items ), ...items ]
		.filter( ( item ) => item.action === 'create' && item.content?.type === 'schema' && subjectsAreDependent( item.content.json ) )
		.map( ( item ) => item.key ) );
	const isAvailable = ( schema: string ): boolean => {
		const here = states.get( itemId( 'schema', schema ) )?.item ?? null;
		return created.has( schema ) || ( here !== null && subjectsAreDependent( here ) );
	};

	return items.map( ( item ) => {
		const missing = item.action !== null && item.content?.type === 'subject' ?
			item.content.entry.dependentSubjects.find( ( dependent ) => !isAvailable( dependent.schema ) ) :
			undefined;

		return missing === undefined ? item : {
			...item,
			status: 'conflict',
			action: null,
			selectedByDefault: false,
			reason: mw.msg( 'neowiki-bundle-conflict-dependent-schema', missing.schema ),
		};
	} );
}

function sameSchema( a: string | null, b: string ): boolean {
	return a !== null && normalizeSchemaName( a ) === normalizeSchemaName( b );
}

/**
 * What the bundle no longer has leaves the record whether or not it is removed. Removal is not offered for
 * a Subject that Subjects outside the bundle still point at, nor for a Schema with Subjects that stay.
 */
async function classifyRemoved(
	removed: readonly RecordedItem[],
	states: Map<string, CurrentState>,
	bundle: ModelBundle,
	recorded: Map<string, RecordedItem>,
	repository: DataModelRepository,
): Promise<ImportItem[]> {
	const present = removed.filter( ( item ) => {
		const state = states.get( itemId( item.type, item.key ) ) as CurrentState;
		return state.item !== null || state.error !== null;
	} );
	const bundleSubjects = withDependents( [
		...bundle.subjects.keys(),
		...[ ...recorded.values() ].filter( ( item ) => item.type === 'subject' ).map( ( item ) => item.key ),
	], states );

	const subjects = await mapConcurrently(
		present.filter( ( item ) => item.type === 'subject' ),
		async ( item ) => removedItem( item, states, await subjectRemovalBlocker( item.key, bundleSubjects, repository ) ),
	);

	const removableSubjects = withDependents( subjects.filter( ( item ) => item.action === 'remove' ).map( ( item ) => item.key ), states );

	const others = await mapConcurrently(
		present.filter( ( item ) => item.type !== 'subject' ),
		async ( item ) => removedItem(
			item,
			states,
			item.type === 'schema' && await repository.hasSubjectsOutside( item.key, removableSubjects ) ?
				mw.msg( 'neowiki-bundle-remove-blocked-used' ) :
				null,
		),
	);

	return [ ...others, ...subjects ];
}

/**
 * Dependent Subjects go with their Host Subject, as the wiki holds them.
 */
function withDependents( ids: readonly string[], states: Map<string, CurrentState> ): Set<string> {
	return new Set( ids.flatMap( ( id ) => [ id, ...dependentIdsOf( states.get( itemId( 'subject', id ) ) ) ] ) );
}

function dependentIdsOf( state: CurrentState | undefined ): string[] {
	const dependents = state?.item?.dependentSubjects;
	return Array.isArray( dependents ) ? dependents.map( ( dependent ) => ( dependent as { id: string } ).id ) : [];
}

async function subjectRemovalBlocker(
	id: string,
	bundleSubjects: ReadonlySet<string>,
	repository: DataModelRepository,
): Promise<string | null> {
	const referencing = await repository.getReferencingSubjectIds( id );

	return referencing.truncated || referencing.ids.some( ( referencingId ) => !bundleSubjects.has( referencingId ) ) ?
		mw.msg( 'neowiki-bundle-remove-blocked-referenced' ) :
		null;
}

function removedItem( recorded: RecordedItem, states: Map<string, CurrentState>, blocker: string | null ): ImportItem {
	const state = states.get( itemId( recorded.type, recorded.key ) ) as CurrentState;
	const reason = state.error !== null ? mw.msg( 'neowiki-bundle-remove-blocked-unreadable', state.error ) : blocker;

	return {
		...baseItem(
			recorded.type,
			recorded.key,
			recorded.type === 'subject' ? state.label ?? recorded.label : recorded.key,
			recorded.type === 'subject' ? state.schema ?? recorded.schema : null,
		),
		status: 'removed-upstream',
		action: reason === null ? 'remove' : null,
		selectedByDefault: false,
		reason,
		upstreamHash: null,
		recorded,
	};
}

function baseItem( type: ItemType, key: string, name: string | null, schema: string | null ): ImportItem {
	return {
		id: itemId( type, key ),
		type,
		key,
		name,
		schema,
		status: 'unchanged',
		action: null,
		selectedByDefault: false,
		reason: null,
		pageTitle: null,
		pageTitleTaken: false,
		versionHere: null,
		versionUpstream: null,
		content: null,
		upstreamHash: null,
		recorded: null,
		revisionId: null,
	};
}

function upstreamName( upstream: Upstream ): string | null {
	return upstream.content.type === 'subject' ?
		upstream.content.entry.label ?? upstream.content.entry.pageTitle :
		upstream.key;
}

function upstreamSchema( upstream: Upstream ): string | null {
	return upstream.content.type === 'subject' ? upstream.content.entry.schema : null;
}

function versions( state: CurrentState, upstream: Upstream ): Pick<ImportItem, 'versionHere' | 'versionUpstream'> {
	if ( state.item === null || state.hash === upstream.hash ) {
		return { versionHere: null, versionUpstream: null };
	}

	return { versionHere: itemText( state.item ), versionUpstream: itemText( upstream.item ) };
}

/**
 * On a page-first wiki a new Subject's page takes the title the file names, else its label, else its ID.
 * A title that is taken would fail the create, so the page takes the Subject's ID instead.
 */
async function withPageTitles( items: ImportItem[], context: PlanContext ): Promise<ImportItem[]> {
	if ( context.subjectFirst ) {
		return items;
	}

	const wanted = new Map( items.flatMap( ( item ) => {
		const title = item.action === 'create' && item.content?.type === 'subject' ?
			item.content.entry.pageTitle ?? item.content.entry.label :
			null;
		return title === null ? [] : [ [ item.id, title ] as const ];
	} ) );

	const unavailable = await context.repository.getUnavailablePageTitles( [ ...new Set( wanted.values() ) ] );

	return items.map( ( item ) => {
		const title = wanted.get( item.id );

		if ( title === undefined || item.content?.type !== 'subject' ) {
			return item;
		}

		return unavailable.has( title ) ?
			{ ...item, pageTitle: item.key, pageTitleTaken: true } :
			{ ...item, pageTitle: item.content.entry.pageTitle };
	} );
}
