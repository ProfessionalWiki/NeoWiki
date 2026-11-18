import { isHttpUrl } from 'ext.neowiki';
import { canonicalJson, itemHash, schemaItem } from './canonical';
import { itemId, type ItemType, type JsonObject } from './ModelBundle';

export const FILE_SOURCE = 'file';

/**
 * The bundle's version of an item: a Schema, Layout or Mapping as a whole, a Subject as a hash of its canonical
 * form, with the label and Schema it is shown by once this wiki deleted it. Whole Subjects could take the record
 * of a large vocabulary past the page size limit.
 */
export type RecordedItem =
	| { readonly type: Exclude<ItemType, 'subject'>; readonly key: string; readonly item: JsonObject }
	| { readonly type: 'subject'; readonly key: string; readonly hash: string; readonly label: string | null; readonly schema: string };

/**
 * An item belongs to at most one bundle.
 */
export interface InstallRecord {
	readonly name: string;
	readonly version: string;
	readonly description: string;
	/** The URL the bundle was read from, or FILE_SOURCE. */
	readonly source: string;
	readonly items: readonly RecordedItem[];
	/** Of the record's page as it was read; null for a record not saved yet. */
	readonly revisionId: number | null;
}

export function recordedItems( record: InstallRecord | null ): Map<string, RecordedItem> {
	return new Map( ( record?.items ?? [] ).map( ( item ) => [ itemId( item.type, item.key ), item ] ) );
}

/**
 * Of a stored Schema, Layout or Mapping in the canonical form of now, so that a change to that form does not
 * make it differ from the wiki's version.
 */
export function recordedHash( recorded: RecordedItem ): string {
	if ( recorded.type === 'subject' ) {
		return recorded.hash;
	}

	return itemHash( recorded.type === 'schema' ? schemaItem( recorded.item ) : canonicalJson( recorded.item ) );
}

export function isUrlSource( source: string ): boolean {
	return isHttpUrl( source );
}
