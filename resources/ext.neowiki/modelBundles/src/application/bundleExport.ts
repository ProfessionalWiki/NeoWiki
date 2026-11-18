import { normalizeSchemaName } from 'ext.neowiki';
import { isJsonObject } from './canonical';
import { mapConcurrently } from './mapConcurrently';
import type { DataModelRepository } from './DataModelRepository';
import type { BundleMetadata, JsonObject, ModelBundle, SubjectEntry } from './ModelBundle';

export interface ExportChoice {
	readonly schemas: readonly { readonly name: string; readonly withSubjects: boolean }[];
	/** Whether to include the Layouts of the chosen Schemas. */
	readonly layouts: boolean;
	/** The names of the Mappings to include. */
	readonly mappings: readonly string[];
	/** Null for a plain export. */
	readonly metadata: BundleMetadata | null;
}

/**
 * Rejects, naming what it could not read, rather than leave anything out of the file. A Schema whose
 * Subjects are dependent gets none of its own: they come with their Host Subjects, and the Schema with them.
 */
export async function exportBundle( choice: ExportChoice, repository: DataModelRepository ): Promise<ModelBundle> {
	const chosen = new Set( choice.schemas.map( ( schema ) => normalizeSchemaName( schema.name ) ) );
	const readSchema = async ( name: string ): Promise<[ string, JsonObject ]> => [
		normalizeSchemaName( name ),
		( await read( repository.getSchema( name ), 'neowiki-bundle-export-unreadable-page', `Schema:${ name }` ) ).json,
	];

	const schemas = await mapConcurrently( choice.schemas.map( ( schema ) => schema.name ), readSchema );

	const layoutNames = choice.layouts ?
		( await repository.getLayoutSummaries() )
			.filter( ( layout ) => chosen.has( normalizeSchemaName( layout.schema ) ) )
			.map( ( layout ) => layout.name ) :
		[];

	const layouts = await mapConcurrently( layoutNames, async ( name ) => [
		name,
		( await read( repository.getLayout( name ), 'neowiki-bundle-export-unreadable-page', `Layout:${ name }` ) ).json,
	] as [ string, JsonObject ] );

	const mappings = await mapConcurrently( choice.mappings, async ( name ) => [
		name,
		( await read( repository.getMapping( name ), 'neowiki-bundle-export-unreadable-page', `Mapping:${ name }` ) ).json,
	] as [ string, JsonObject ] );

	const subjects = new Map<string, SubjectEntry>();

	for ( const schema of choice.schemas.filter( ( chosenSchema ) => chosenSchema.withSubjects ) ) {
		const ofSchema = await read(
			repository.getSubjectsOfSchema( schema.name ),
			'neowiki-bundle-export-unreadable-subjects',
			schema.name,
		);
		ofSchema.forEach( ( entry, id ) => subjects.set( id, entry ) );
	}

	const dependentSchemas = await mapConcurrently(
		[ ...new Set( [ ...subjects.values() ].flatMap( ( entry ) => entry.dependentSubjects.map( ( dependent ) => normalizeSchemaName( dependent.schema ) ) ) ) ]
			.filter( ( name ) => !chosen.has( name ) ),
		readSchema,
	);

	return {
		metadata: choice.metadata,
		schemas: new Map( [ ...schemas, ...dependentSchemas ] ),
		layouts: new Map( layouts ),
		mappings: new Map( mappings ),
		subjects,
	};
}

async function read<T>( reading: Promise<T>, message: string, name: string ): Promise<T> {
	try {
		return await reading;
	} catch ( _error ) {
		throw new Error( mw.msg( message, name ) );
	}
}

/**
 * Those of the dependent Schemas given that a Schema's relations point at: the Schemas an export of its
 * Subjects adds, for their Dependent Subjects.
 */
export function dependentSchemasOf( schema: JsonObject, dependentSchemas: ReadonlySet<string> ): string[] {
	const definitions = isJsonObject( schema.propertyDefinitions ) ? Object.values( schema.propertyDefinitions ) : [];
	const targets = definitions.flatMap( ( definition ) => isJsonObject( definition ) && definition.type === 'relation' &&
		Array.isArray( definition.targetSchemas ) ? definition.targetSchemas : [] );

	return [ ...new Set( targets.filter( ( target ) => typeof target === 'string' ).map( normalizeSchemaName ) ) ]
		.filter( ( name ) => dependentSchemas.has( name ) );
}
