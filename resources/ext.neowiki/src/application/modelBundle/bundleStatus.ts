import { itemId, type ItemType } from '@/application/modelBundle/ModelBundle';
import { type InstallRecord, type RecordedItem, recordedHash } from '@/application/modelBundle/InstallRecord';
import type { CurrentState, CurrentStateReader } from '@/application/modelBundle/CurrentStateReader';

/**
 * Of an item an installed bundle owns, against the version the bundle had when installed or last updated.
 * Unreadable when the wiki's version could not be read.
 */
export type OwnedItemStatus = 'unchanged' | 'changed-here' | 'deleted-here' | 'unreadable';

export interface DataModelItem {
	readonly type: ItemType;
	readonly key: string;
	/** Its name, or a Subject's label; null for a Subject without one. */
	readonly name: string | null;
	readonly schema: string | null;
	/** Absent for an item no bundle owns. */
	readonly status?: OwnedItemStatus;
}

export async function readOwnedItems( record: InstallRecord, reader: CurrentStateReader ): Promise<DataModelItem[]> {
	const states = await reader.readAll( record.items );

	return record.items.map( ( item ) => {
		const state = states.get( itemId( item.type, item.key ) ) as CurrentState;

		return {
			type: item.type,
			key: item.key,
			...nameAndSchema( item, state ),
			status: statusOf( state.error, state.hash, recordedHash( item ) ),
		};
	} );
}

/**
 * A Subject the wiki no longer has, or that it could not read, is shown as the bundle recorded it.
 */
function nameAndSchema( item: RecordedItem, state: CurrentState ): Pick<DataModelItem, 'name' | 'schema'> {
	if ( item.type !== 'subject' ) {
		return { name: item.key, schema: null };
	}

	return state.item === null ? { name: item.label, schema: item.schema } : { name: state.label, schema: state.schema };
}

function statusOf( error: string | null, here: string | null, recorded: string ): OwnedItemStatus {
	if ( error !== null ) {
		return 'unreadable';
	}

	if ( here === null ) {
		return 'deleted-here';
	}

	return here === recorded ? 'unchanged' : 'changed-here';
}

/**
 * The wiki's Schemas, Layouts and Mappings that no installed bundle owns.
 */
export async function readUnownedItems( records: readonly InstallRecord[], reader: CurrentStateReader ): Promise<DataModelItem[]> {
	const owned = new Set( records.flatMap( ( record ) => record.items.map( ( item ) => itemId( item.type, item.key ) ) ) );

	return [ ...await reader.getNames() ].flatMap( ( [ type, keys ] ) => [ ...keys ]
		.filter( ( key ) => !owned.has( itemId( type, key ) ) )
		.map( ( key ) => ( { type, key, name: key, schema: null } ) ) );
}
