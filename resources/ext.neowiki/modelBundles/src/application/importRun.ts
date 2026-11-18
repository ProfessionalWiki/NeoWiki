import { PageTitleTakenError } from 'ext.neowiki';
import { ITEM_TYPES, type ItemType, type SubjectEntry } from './ModelBundle';
import type { DataModelRepository, InstallRecordRepository } from './DataModelRepository';
import type { DeclinedItem, InstallRecord, RecordedItem } from './InstallRecord';
import type { ImportItem, ImportPlan, ItemContent } from './importPlan';
import { canonicalJson, schemaItem } from './canonical';

export type ItemResult = 'created' | 'replaced' | 'removed' | 'failed';

export interface ItemOutcome {
	readonly item: ImportItem;
	readonly result: ItemResult;
	readonly reason: string | null;
}

type RecordOutcome = { readonly saved: true } | { readonly saved: false; readonly reason: string };

export interface ImportOutcome {
	readonly items: readonly ItemOutcome[];
	readonly record: RecordOutcome;
}

interface RunContext {
	readonly repository: DataModelRepository;
	readonly recordRepository: InstallRecordRepository;
	readonly comment: string;
	/** Where a bundle was read from: its URL, or FILE_SOURCE. */
	readonly source: string;
	readonly subjectFirst: boolean;
}

/**
 * Writes the chosen items one at a time: Schemas, then Layouts, Mappings and Subjects, then the removals in the
 * opposite order. A failure does not stop the others. The install record is written last.
 */
export async function runImport(
	plan: ImportPlan,
	chosen: ReadonlySet<string>,
	context: RunContext,
	onProgress?: ( done: number, total: number ) => void,
): Promise<ImportOutcome> {
	const writes = ordered( plan.items, chosen, [ 'create', 'replace' ], ITEM_TYPES );
	const removals = ordered( plan.items, chosen, [ 'remove' ], [ ...ITEM_TYPES ].reverse() );
	const steps = [ ...writes, ...removals ];
	const outcomes: ItemOutcome[] = [];

	onProgress?.( 0, steps.length );

	for ( const item of steps ) {
		outcomes.push( await runStep( item, context ) );
		onProgress?.( outcomes.length, steps.length );
	}

	return {
		items: outcomes,
		record: await saveRecord( plan, outcomes, context ),
	};
}

function ordered(
	items: readonly ImportItem[],
	chosen: ReadonlySet<string>,
	actions: readonly string[],
	types: readonly ItemType[],
): ImportItem[] {
	return types.flatMap( ( type ) => items.filter(
		( item ) => item.type === type && item.action !== null && actions.includes( item.action ) && chosen.has( item.id ),
	) );
}

async function runStep( item: ImportItem, context: RunContext ): Promise<ItemOutcome> {
	try {
		await ( item.action === 'remove' ? remove( item, context ) : write( item, context ) );
		return { item, result: resultOf( item ), reason: null };
	} catch ( error ) {
		return { item, result: 'failed', reason: error instanceof Error ? error.message : String( error ) };
	}
}

function resultOf( item: ImportItem ): ItemResult {
	if ( item.action === 'remove' ) {
		return 'removed';
	}

	return item.action === 'create' ? 'created' : 'replaced';
}

async function write( item: ImportItem, context: RunContext ): Promise<void> {
	const { repository, comment } = context;
	const content = item.content;

	switch ( content?.type ) {
		case 'schema':
			return repository.saveSchema( item.key, content.json, comment, item.revisionId );
		case 'layout':
			return repository.saveLayout( item.key, content.json, comment, item.revisionId );
		case 'mapping':
			return repository.saveMapping( item.key, content.json, comment, item.revisionId );
		case 'subject':
			return item.action === 'create' ?
				createSubject( item, content.entry, context ) :
				repository.replaceSubject( item.key, content.entry, comment );
	}
}

/**
 * On a page-first wiki a title taken since the preview makes the page take the Subject's ID, as a title the
 * preview found taken did. When the ID is taken too, by the page that removing the Subject left empty, the
 * Subject goes back on that page.
 */
async function createSubject( item: ImportItem, entry: SubjectEntry, context: RunContext ): Promise<void> {
	const titles = context.subjectFirst || item.pageTitle === item.key ? [ item.pageTitle ] : [ item.pageTitle, item.key ];
	let refusal: PageTitleTakenError | null = null;

	for ( const title of titles ) {
		try {
			return await context.repository.createSubject( item.key, entry, title, context.comment );
		} catch ( error ) {
			if ( !( error instanceof PageTitleTakenError ) ) {
				throw error;
			}

			refusal = error;
		}
	}

	if ( !await context.repository.addSubjectToEmptyIdPage( item.key, entry, context.comment ) ) {
		throw refusal;
	}
}

/**
 * A Schema is removed after the Subjects, so that one whose Subjects were all removed goes too, and one that
 * Subjects still use stays.
 */
async function remove( item: ImportItem, context: RunContext ): Promise<void> {
	const { repository, comment } = context;

	switch ( item.type ) {
		case 'subject':
			return repository.deleteSubject( item.key, comment );
		case 'mapping':
			return repository.deleteMapping( item.key, comment );
		case 'layout':
			return repository.deleteLayout( item.key, comment );
		case 'schema':
			if ( await repository.hasSubjectsOutside( item.key, new Set() ) ) {
				throw new Error( mw.msg( 'neowiki-bundle-remove-blocked-used' ) );
			}

			return repository.deleteSchema( item.key, comment );
	}
}

async function saveRecord( plan: ImportPlan, outcomes: readonly ItemOutcome[], context: RunContext ): Promise<RecordOutcome> {
	try {
		await context.recordRepository.saveInstallRecord( nextRecord( plan, outcomes, context.source ), context.comment );
		return { saved: true };
	} catch ( error ) {
		return { saved: false, reason: error instanceof Error ? error.message : String( error ) };
	}
}

/**
 * The record keeps, for every item of the file on the wiki, the bundle's version of it: what was written,
 * and what was left as it was, which then shows as changed here. An item whose write failed keeps the
 * version recorded before. What the file no longer has leaves the record, removed or not. What the file
 * would have created, had it been ticked, is declined.
 */
function nextRecord( plan: ImportPlan, outcomes: readonly ItemOutcome[], source: string ): InstallRecord {
	const results = new Map( outcomes.map( ( outcome ) => [ outcome.item.id, outcome.result ] ) );
	const metadata = plan.bundle.metadata;

	return {
		name: plan.bundleName,
		version: metadata?.version ?? '',
		description: metadata?.description ?? '',
		source,
		items: plan.items.flatMap( ( item ): RecordedItem[] => {
			const recorded = recordedAfter( item, results.get( item.id ) );
			return recorded === null ? [] : [ recorded ];
		} ),
		declined: plan.items.flatMap( ( item ): DeclinedItem[] =>
			isAbsent( item ) && !results.has( item.id ) ? [ { type: item.type, key: item.key } ] : [] ),
		revisionId: plan.record?.revisionId ?? null,
	};
}

function recordedAfter( item: ImportItem, result: ItemResult | undefined ): RecordedItem | null {
	if ( item.content === null || item.upstreamHash === null ) {
		return null;
	}

	if ( item.status === 'conflict' || result === 'failed' ) {
		return item.recorded;
	}

	return isAbsent( item ) && result !== 'created' ? null : bundleVersion( item.key, item.content, item.upstreamHash );
}

function isAbsent( item: ImportItem ): boolean {
	return item.status === 'new' || item.status === 'declined';
}

function bundleVersion( key: string, content: ItemContent, hash: string ): RecordedItem {
	if ( content.type === 'subject' ) {
		return { type: 'subject', key, hash, label: content.entry.label, schema: content.entry.schema };
	}

	return { type: content.type, key, item: content.type === 'schema' ? schemaItem( content.json ) : canonicalJson( content.json ) };
}
