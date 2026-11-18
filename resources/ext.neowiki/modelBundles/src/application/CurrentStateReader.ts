import { normalizeSchemaName } from 'ext.neowiki';
import { itemHash, pageItem, subjectItem } from './canonical';
import { itemId, type ItemType, type JsonObject } from './ModelBundle';
import type { DataModelRepository, ReadPage } from './DataModelRepository';
import { mapConcurrently } from './mapConcurrently';

export interface CurrentState {
	/** The item in canonical form; null when the wiki does not have it or it could not be read. */
	readonly item: JsonObject | null;
	readonly hash: string | null;
	readonly error: string | null;
	readonly schema: string | null;
	readonly label: string | null;
	/** For a Dependent Subject, the standalone Subject its chain of Host Subjects starts at. */
	readonly hostRootId: string | null;
	/** Of a Schema, Layout or Mapping page the wiki has. */
	readonly revisionId: number | null;
}

const ABSENT: CurrentState = { item: null, hash: null, error: null, schema: null, label: null, hostRootId: null, revisionId: null };

/**
 * Reads each item once however often it is asked for, so that a preview can be planned again, as for another
 * bundle name, without reading the wiki again. What it failed to read it reads again when asked again.
 */
export class CurrentStateReader {

	private readonly states = new Map<string, Promise<CurrentState>>();

	private names: Promise<Map<ItemType, Set<string>>> | null = null;

	public constructor( private readonly repository: DataModelRepository ) {
	}

	public async readAll( items: readonly { type: ItemType; key: string }[] ): Promise<Map<string, CurrentState>> {
		const states = await mapConcurrently( items, ( { type, key } ) => this.read( type, key ) );
		return new Map( items.map( ( { type, key }, index ) => [ itemId( type, key ), states[ index ] ] ) );
	}

	public read( type: ItemType, key: string ): Promise<CurrentState> {
		const id = itemId( type, key );
		let state = this.states.get( id );

		if ( state === undefined ) {
			state = this.readUncached( type, key ).catch( ( error ) => {
				this.states.delete( id );
				return { ...ABSENT, error: error instanceof Error ? error.message : String( error ) };
			} );
			this.states.set( id, state );
		}

		return state;
	}

	private async readUncached( type: ItemType, key: string ): Promise<CurrentState> {
		if ( type === 'subject' ) {
			return this.readSubject( key );
		}

		if ( !( await this.getNames() ).get( type )?.has( key ) ) {
			return ABSENT;
		}

		const page = await this.readPage( type, key );
		return { ...present( pageItem( type, page.json ) ), revisionId: page.revisionId };
	}

	private readPage( type: Exclude<ItemType, 'subject'>, name: string ): Promise<ReadPage> {
		switch ( type ) {
			case 'schema':
				return this.repository.getSchema( name );
			case 'layout':
				return this.repository.getLayout( name );
			case 'mapping':
				return this.repository.getMapping( name );
		}
	}

	private async readSubject( id: string ): Promise<CurrentState> {
		const subject = await this.repository.getSubject( id );

		if ( subject === null ) {
			return ABSENT;
		}

		return {
			...present( subjectItem( subject.entry ) ),
			schema: subject.entry.schema,
			label: subject.entry.label,
			hostRootId: subject.hostRootId,
		};
	}

	/**
	 * The names of the wiki's Schemas, Layouts and Mappings, as their pages are titled.
	 */
	public getNames(): Promise<Map<ItemType, Set<string>>> {
		this.names ??= Promise.all( [
			this.repository.getSchemaSummaries(),
			this.repository.getLayoutSummaries(),
			this.repository.getMappingNames(),
		] ).then( ( [ schemas, layouts, mappings ] ) => new Map<ItemType, Set<string>>( [
			[ 'schema', new Set( schemas.map( ( schema ) => normalizeSchemaName( schema.name ) ) ) ],
			[ 'layout', new Set( layouts.map( ( layout ) => normalizeSchemaName( layout.name ) ) ) ],
			[ 'mapping', new Set( mappings.map( normalizeSchemaName ) ) ],
		] ) ).catch( ( error ) => {
			this.names = null;
			throw error;
		} );

		return this.names;
	}

}

function present( item: JsonObject ): CurrentState {
	return { ...ABSENT, item, hash: itemHash( item ) };
}
