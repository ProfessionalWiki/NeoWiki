import { reactive } from 'vue';
import type { SubjectSummary, SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';

export const SUBJECT_PREVIEW_SIZE = 3;

export type SubjectPreview =
	{ state: 'loading' } |
	{ state: 'loaded'; subjects: SubjectSummary[] } |
	{ state: 'failed' };

/**
 * The newest Subjects of each Schema, asked for once per page view however often a card showing them is mounted,
 * and asked for again only after a failure.
 */
export class SubjectPreviews {

	private readonly previews = reactive( new Map<string, SubjectPreview>() );

	public constructor( private readonly lookup: SubjectSummaryLookup ) {
	}

	public get( schemaName: string ): SubjectPreview | undefined {
		return this.previews.get( schemaName );
	}

	public load( schemaName: string ): void {
		const known = this.previews.get( schemaName );

		if ( known !== undefined && known.state !== 'failed' ) {
			return;
		}

		this.previews.set( schemaName, { state: 'loading' } );

		this.lookup.getSubjectSummaries( {
			schema: schemaName,
			search: '',
			sort: 'newest',
			direction: 'desc',
			cursor: null,
			limit: SUBJECT_PREVIEW_SIZE,
		} ).then(
			( summaries ) => this.previews.set( schemaName, { state: 'loaded', subjects: summaries.subjects } ),
			() => this.previews.set( schemaName, { state: 'failed' } ),
		);
	}

}
