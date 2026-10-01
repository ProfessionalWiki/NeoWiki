import type { TableColumn, TableSort } from '@wikimedia/codex';
import type { SortDirection, SubjectSummary, SubjectSummarySort } from '@/application/SubjectSummaryLookup.ts';
import { generatedSubjectName } from '@/presentation/subjectDisplayName.ts';
import { subjectRowLinkUrl } from '@/presentation/subjectLinks.ts';

export type SubjectColumnId = 'name' | 'id' | 'schema' | 'page' | 'edited';

export interface SubjectColumn extends TableColumn {
	id: SubjectColumnId;
}

const COLUMN_LABELS: Record<SubjectColumnId, string> = {
	name: 'neowiki-subjects-column-name',
	id: 'neowiki-subjects-column-id',
	schema: 'neowiki-subjects-column-schema',
	page: 'neowiki-subjects-column-page',
	edited: 'neowiki-subjects-column-edited',
};

type ColumnSort = Exclude<SubjectSummarySort, 'newest'>;

const COLUMN_SORTS: readonly ColumnSort[] = [ 'name', 'schema', 'page', 'edited' ];

/**
 * The fixed columns of a Subject table. The ID is there to tell Subjects apart and to search by, not to sort by:
 * its order is the creation order the table shows when no column is sorted.
 */
export function subjectColumns( options: { showSchema: boolean; showPage: boolean; sortable: boolean } ): SubjectColumn[] {
	const ids: SubjectColumnId[] = [
		'name',
		'id',
		...( options.showSchema ? [ 'schema' as const ] : [] ),
		...( options.showPage ? [ 'page' as const ] : [] ),
		'edited',
	];

	return ids.map( ( id ) => ( {
		id,
		label: mw.msg( COLUMN_LABELS[ id ] ),
		allowSort: options.sortable && isColumnSort( id ),
	} ) );
}

/** Whether the listing can be sorted by this column, under the sort of the same name. */
function isColumnSort( column: string ): column is ColumnSort {
	return ( COLUMN_SORTS as readonly string[] ).includes( column );
}

/**
 * The listing order for a CdxTable sort: the sorted column, or newest first when none is.
 */
export function summaryOrder( sort: TableSort ): { sort: SubjectSummarySort; direction: SortDirection } {
	const [ column, order ] = Object.entries( sort )[ 0 ] ?? [];

	if ( column === undefined || !isColumnSort( column ) || order === undefined || order === 'none' ) {
		return { sort: 'newest', direction: 'desc' };
	}

	return { sort: column, direction: order };
}

export function summaryName( summary: SubjectSummary ): string {
	return summary.displayNameIsGenerated ? generatedSubjectName( summary.id ) : summary.displayName;
}

export function summaryUrl( summary: SubjectSummary ): string {
	return subjectRowLinkUrl( summary.pageTitle, summary.id );
}

export interface LastEdited {
	readonly relative: string;
	readonly full: string;
}

const RELATIVE_TIME_UNITS: readonly [ Intl.RelativeTimeFormatUnit, number ][] = [
	[ 'year', 365 * 86400 ], [ 'month', 30 * 86400 ], [ 'week', 7 * 86400 ], [ 'day', 86400 ], [ 'hour', 3600 ],
	[ 'minute', 60 ],
];

// Built once, as every row's Edited cell uses both.
const RELATIVE_TIME_FORMAT = new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } );
const FULL_TIME_FORMAT = new Intl.DateTimeFormat( undefined, {
	year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
} );

/**
 * When the page was last edited: how long before `now`, and in full. Both in the browser's locale, like the other
 * dates the interface shows. An edit dated after `now`, by a server clock ahead of the browser's, reads as now.
 */
export function describeLastEdited( iso: string, now: Date ): LastEdited | null {
	const date = new Date( iso );

	if ( isNaN( date.getTime() ) ) {
		return null;
	}

	return {
		relative: relativeTime( Math.max( 0, ( now.getTime() - date.getTime() ) / 1000 ) ),
		full: FULL_TIME_FORMAT.format( date ),
	};
}

function relativeTime( secondsAgo: number ): string {
	const [ unit, unitSeconds ] = RELATIVE_TIME_UNITS.find( ( [ , size ] ) => secondsAgo >= size ) ?? [ 'second', 1 ];

	return RELATIVE_TIME_FORMAT.format( -Math.floor( secondsAgo / unitSeconds ), unit );
}

/**
 * The Schema menu's names in order. The selected Schema is among them even when the Schema list lacks it, as it does
 * before it loads, so the menu always shows the selection.
 */
export function schemaMenuNames( schemaNames: string[], selected: string | null ): string[] {
	const names = selected === null || schemaNames.includes( selected ) ? schemaNames : [ ...schemaNames, selected ];

	return [ ...names ].sort( ( a, b ) => a.localeCompare( b ) );
}

export function emptyStateMessage( search: string, schema: string | null ): string {
	if ( search !== '' ) {
		return mw.msg( 'neowiki-subjects-no-match', search );
	}

	return schema === null ? mw.msg( 'neowiki-subjects-empty' ) : mw.msg( 'neowiki-subjects-empty-schema', schema );
}
