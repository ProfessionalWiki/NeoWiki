import { type ActionApi, actionApiCall } from './ActionApi';
import { LayoutDeserializer, LayoutSerializer, SchemaDeserializer, SchemaSerializer } from 'ext.neowiki';
import { isJsonObject } from '../application/canonical';
import { ITEM_TYPES, type JsonObject } from '../application/ModelBundle';
import type { InstallRecordRepository } from '../application/DataModelRepository';
import { type DeclinedItem, FILE_SOURCE, type InstallRecord, type RecordedItem } from '../application/InstallRecord';
import { normalizeBundleName } from '../application/bundleName';

const MEDIAWIKI_NAMESPACE = 8;
const RECORD_TITLE_PREFIX = 'MediaWiki:NeoWiki/Model bundles/';
const RECORD_TITLE_SUFFIX = '.json';

interface RecordPagesResponse {
	continue?: Record<string, string>;
	query?: {
		pages?: { title: string; revisions?: { revid?: number; slots?: { main?: { content?: string } } }[] }[];
	};
}

/**
 * A record is the JSON page `MediaWiki:NeoWiki/Model bundles/<name>.json`, whose title names the bundle.
 */
export class MediaWikiInstallRecordRepository implements InstallRecordRepository {

	public constructor( private readonly api: ActionApi ) {
	}

	public async getInstallRecords(): Promise<InstallRecord[]> {
		const records: InstallRecord[] = [];
		let continuation: Record<string, string> = {};

		do {
			const response: RecordPagesResponse = await actionApiCall<RecordPagesResponse>( this.api.get( {
				action: 'query',
				generator: 'allpages',
				gapnamespace: MEDIAWIKI_NAMESPACE,
				gapprefix: RECORD_TITLE_PREFIX.slice( 'MediaWiki:'.length ),
				gaplimit: 'max',
				prop: 'revisions',
				rvprop: 'ids|content',
				rvslots: 'main',
				formatversion: 2,
				...continuation,
			} ) );

			for ( const page of response.query?.pages ?? [] ) {
				const revision = page.revisions?.[ 0 ];
				const record = recordOf( page.title, revision?.revid ?? null, revision?.slots?.main?.content ?? '' );

				if ( record !== null ) {
					records.push( record );
				}
			}

			continuation = response.continue ?? {};
		} while ( Object.keys( continuation ).length > 0 );

		return records.sort( ( a, b ) => a.name.localeCompare( b.name ) );
	}

	/**
	 * Saves over the revision the record was read from, and creates the page of one read from none, so that
	 * a record saved meanwhile is not overwritten. An edit that a hook such as AbuseFilter stops comes back
	 * as a result, not as an error.
	 */
	public async saveInstallRecord( record: InstallRecord, comment: string ): Promise<void> {
		const response = await actionApiCall<{ edit?: { result?: string } }>( this.api.postWithEditToken( {
			action: 'edit',
			title: installRecordTitle( record.name ),
			text: pageText( record ),
			contentmodel: 'json',
			summary: comment,
			...( record.revisionId === null ? { createonly: true } : { baserevid: record.revisionId } ),
			formatversion: 2,
		} ) );

		if ( response.edit?.result !== 'Success' ) {
			throw new Error( response.edit?.result ?? 'edit failed' );
		}
	}

}

export function installRecordTitle( bundleName: string ): string {
	return RECORD_TITLE_PREFIX + normalizeBundleName( bundleName ) + RECORD_TITLE_SUFFIX;
}

function pageText( record: InstallRecord ): string {
	return JSON.stringify( {
		version: record.version,
		description: record.description,
		source: record.source,
		items: [ ...record.items ].sort( compareItems ).map( pageItem ),
		declined: [ ...record.declined ].sort( compareItems ).map( ( item ) => ( { type: item.type, key: item.key } ) ),
	}, null, '\t' );
}

function pageItem( item: RecordedItem ): JsonObject {
	if ( item.type !== 'subject' ) {
		return { type: item.type, key: item.key, item: item.item };
	}

	return { type: item.type, key: item.key, hash: item.hash, ...( item.label === null ? {} : { label: item.label } ), schema: item.schema };
}

function compareItems( a: DeclinedItem, b: DeclinedItem ): number {
	const byType = ITEM_TYPES.indexOf( a.type ) - ITEM_TYPES.indexOf( b.type );
	return byType !== 0 ? byType : ( a.key < b.key ? -1 : 1 );
}

/**
 * Null for a page that is not a record, or one with an item it cannot read, as a record from before records
 * held the items has, so that one broken page does not hide the others.
 */
function recordOf( title: string, revisionId: number | null, text: string ): InstallRecord | null {
	const json = parsedJson( text );

	if ( !title.endsWith( RECORD_TITLE_SUFFIX ) || !isJsonObject( json ) || !Array.isArray( json.items ) ) {
		return null;
	}

	const items = json.items.map( recordedItemOf );

	if ( items.includes( null ) ) {
		return null;
	}

	return {
		name: title.slice( RECORD_TITLE_PREFIX.length, -RECORD_TITLE_SUFFIX.length ),
		version: typeof json.version === 'string' ? json.version : '',
		description: typeof json.description === 'string' ? json.description : '',
		source: typeof json.source === 'string' ? json.source : FILE_SOURCE,
		items: items as RecordedItem[],
		declined: Array.isArray( json.declined ) ? json.declined.flatMap( declinedItemOf ) : [],
		revisionId,
	};
}

function parsedJson( text: string ): unknown {
	try {
		return JSON.parse( text );
	} catch ( _error ) {
		return null;
	}
}

function declinedItemOf( json: unknown ): DeclinedItem[] {
	return isJsonObject( json ) && typeof json.key === 'string' && ITEM_TYPES.includes( json.type as DeclinedItem[ 'type' ] ) ?
		[ { type: json.type as DeclinedItem[ 'type' ], key: json.key } ] :
		[];
}

function recordedItemOf( json: unknown ): RecordedItem | null {
	if ( !isJsonObject( json ) || typeof json.key !== 'string' ) {
		return null;
	}

	if ( json.type === 'subject' ) {
		return typeof json.hash === 'string' && typeof json.schema === 'string' && ( json.label === undefined || typeof json.label === 'string' ) ?
			{ type: 'subject', key: json.key, hash: json.hash, label: json.label ?? null, schema: json.schema } :
			null;
	}

	if ( ( json.type === 'schema' || json.type === 'layout' || json.type === 'mapping' ) && isJsonObject( json.item ) ) {
		try {
			return { type: json.type, key: json.key, item: savedForm( json.type, json.key, json.item ) };
		} catch ( _error ) {
			return null;
		}
	}

	return null;
}

/**
 * As NeoWiki saves the item now, as the wiki's own version is read, so that a change to how NeoWiki saves
 * Schemas or Layouts does not make the recorded version differ from it.
 */
function savedForm( type: 'schema' | 'layout' | 'mapping', name: string, item: JsonObject ): JsonObject {
	switch ( type ) {
		case 'schema':
			return new SchemaSerializer().schemaToJson( new SchemaDeserializer().deserialize( name, item ) );
		case 'layout':
			return new LayoutSerializer().layoutToJson( new LayoutDeserializer().deserialize( name, item ) );
		case 'mapping':
			return item;
	}
}
