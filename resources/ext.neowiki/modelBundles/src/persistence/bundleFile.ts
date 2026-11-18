import {
	isValueEmpty,
	Neo,
	normalizeSchemaName,
	StatementList,
	statementsToJson,
	SubjectId,
} from 'ext.neowiki';
import { isUsableBundleName, normalizeBundleName } from '../application/bundleName';
import {
	canonicalJson,
	isJsonObject,
	normalizedLabel,
	pageItem,
	subjectItem,
} from '../application/canonical';
import type {
	BundleMetadata,
	DependentSubjectEntry,
	JsonObject,
	ModelBundle,
	SubjectEntry,
} from '../application/ModelBundle';
import { savedForm } from './savedForm';

const SECTIONS = [ 'schemas', 'layouts', 'mappings', 'subjects' ] as const;

export function serializeBundleFile( bundle: ModelBundle ): string {
	const file: JsonObject = {
		bundle: bundle.metadata === null ? undefined : canonicalJson( {
			name: bundle.metadata.name,
			version: bundle.metadata.version || undefined,
			description: bundle.metadata.description || undefined,
		} ),
		layouts: sortedSection( bundle.layouts, ( json ) => pageItem( 'layout', json ) ),
		mappings: sortedSection( bundle.mappings, ( json ) => pageItem( 'mapping', json ) ),
		// A Schemas-only export reads as it did before Model bundles: with its "schemas" object even when empty.
		schemas: sortedSection( bundle.schemas, ( json ) => pageItem( 'schema', json ) ) ?? {},
		subjects: sortedSection( bundle.subjects, subjectFileItem ),
	};

	return JSON.stringify( file, null, 4 ) + '\n';
}

/**
 * Undefined, which JSON.stringify leaves out, for a section without items.
 */
function sortedSection<T>( items: ReadonlyMap<string, T>, toJson: ( item: T ) => JsonObject ): JsonObject | undefined {
	if ( items.size === 0 ) {
		return undefined;
	}

	return Object.fromEntries(
		[ ...items.keys() ].sort().map( ( key ) => [ key, toJson( items.get( key ) as T ) ] ),
	);
}

function subjectFileItem( entry: SubjectEntry ): JsonObject {
	return canonicalJson( { ...subjectItem( entry ), pageTitle: entry.pageTitle ?? undefined } );
}

/**
 * Throws an Error saying what is wrong, naming the entry at fault where there is one. Top-level keys it does
 * not know are ignored, so that a later format can add some.
 */
export function parseBundleFile( text: string ): ModelBundle {
	const file = parseJson( text );

	if ( !isJsonObject( file ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-empty' ) );
	}

	for ( const section of SECTIONS ) {
		if ( file[ section ] !== undefined && !isJsonObject( file[ section ] ) ) {
			throw new Error( mw.msg( 'neowiki-bundle-file-section-not-object', section ) );
		}
	}

	const bundle: ModelBundle = {
		metadata: file.bundle === undefined ? null : readMetadata( file.bundle ),
		schemas: readSection( file.schemas, readSchema, normalizeSchemaName ),
		layouts: readSection( file.layouts, readLayout, normalizeSchemaName ),
		mappings: readSection( file.mappings, readMapping, normalizeSchemaName ),
		subjects: readSection( file.subjects, readSubject, ( id ) => id ),
	};

	if ( bundle.schemas.size + bundle.layouts.size + bundle.mappings.size + bundle.subjects.size === 0 ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-empty' ) );
	}

	return bundle;
}

function parseJson( text: string ): unknown {
	try {
		return JSON.parse( text );
	} catch ( _error ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-not-json' ) );
	}
}

function readMetadata( json: unknown ): BundleMetadata {
	if ( !isJsonObject( json ) || !isNonEmptyString( json.name ) ||
		!isOptionalString( json.version ) || !isOptionalString( json.description ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-invalid-metadata' ) );
	}

	const name = normalizeBundleName( json.name );

	if ( !isUsableBundleName( name ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-name-unusable' ) );
	}

	return {
		name,
		version: ( json.version ?? '' ).trim(),
		description: ( json.description ?? '' ).trim(),
	};
}

/**
 * Keyed as the wiki will name each item, so that two spellings of one page title are caught here.
 */
function readSection<T>(
	section: unknown,
	read: ( key: string, json: unknown ) => T,
	normalizeKey: ( key: string ) => string,
): Map<string, T> {
	const items = new Map<string, T>();

	for ( const [ key, json ] of Object.entries( ( section ?? {} ) as JsonObject ) ) {
		const normalizedKey = normalizeKey( key );

		if ( items.has( normalizedKey ) ) {
			throw new Error( mw.msg( 'neowiki-bundle-file-duplicate', normalizedKey ) );
		}

		items.set( normalizedKey, read( normalizedKey, json ) );
	}

	return items;
}

function readSchema( name: string, json: unknown ): JsonObject {
	if ( !isJsonObject( json ) || !isJsonObject( json.propertyDefinitions ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-schema-no-property-definitions', name ) );
	}

	try {
		return savedForm( 'schema', name, json );
	} catch ( error ) {
		throw new Error( mw.msg(
			'neowiki-bundle-file-invalid-schema',
			name,
			error instanceof Error ? error.message : String( error ),
		) );
	}
}

function readLayout( name: string, json: unknown ): JsonObject {
	if ( !isJsonObject( json ) || !isNonEmptyString( json.schema ) || !isNonEmptyString( json.type ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-invalid-layout', name, mw.msg( 'neowiki-bundle-file-layout-fields' ) ) );
	}

	try {
		return savedForm( 'layout', name, json );
	} catch ( error ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-invalid-layout', name, error instanceof Error ? error.message : String( error ) ) );
	}
}

function readMapping( name: string, json: unknown ): JsonObject {
	if ( !isJsonObject( json ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-invalid-mapping', name ) );
	}

	return json;
}

function readSubject( id: string, json: unknown ): SubjectEntry {
	if ( !isLocalSubjectId( id ) || !isJsonObject( json ) || !isNonEmptyString( json.schema ) ||
		!isJsonObject( json.statements ) || !isOptionalString( json.label ) || !isOptionalString( json.pageTitle ) ||
		!isOptionalDependentSubjects( json.dependentSubjects ) ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-invalid-subject', id ) );
	}

	try {
		return {
			schema: normalizeSchemaName( json.schema ),
			label: normalizedLabel( json.label ?? null ),
			statements: storedStatements( json.statements ),
			dependentSubjects: ( json.dependentSubjects ?? [] ).map( ( dependent ) => ( {
				id: dependent.id,
				schema: normalizeSchemaName( dependent.schema ),
				statements: storedStatements( dependent.statements ),
			} ) ),
			pageTitle: json.pageTitle?.trim() || null,
		};
	} catch ( _error ) {
		throw new Error( mw.msg( 'neowiki-bundle-file-invalid-subject', id ) );
	}
}

/**
 * As the wiki stores them, which is how they are compared and written: each value as the Subject editor
 * builds it, and those that hold nothing left out.
 */
function storedStatements( statements: JsonObject ): JsonObject {
	const deserializer = Neo.getInstance().getStatementDeserializer();

	return statementsToJson( new StatementList( Object.entries( statements ).map( ( [ name, statement ] ) => {
		if ( !isJsonObject( statement ) || typeof statement.propertyType !== 'string' || statement.value === null ) {
			throw new Error( `Statement ${ name } has no value or no Property Type` );
		}

		return deserializer.deserialize( name, statement );
	} ).filter( ( statement ) => !isValueEmpty( statement.value ) ) ) );
}

function isOptionalDependentSubjects( json: unknown ): json is DependentSubjectEntry[] | undefined {
	return json === undefined || ( Array.isArray( json ) && json.every(
		( item ) => isJsonObject( item ) && typeof item.id === 'string' && isLocalSubjectId( item.id ) &&
			isNonEmptyString( item.schema ) && isJsonObject( item.statements ),
	) );
}

function isLocalSubjectId( id: string ): boolean {
	return SubjectId.isValid( id ) && new SubjectId( id ).isLocal();
}

function isNonEmptyString( value: unknown ): value is string {
	return typeof value === 'string' && value.trim() !== '';
}

function isOptionalString( value: unknown ): value is string | undefined {
	return value === undefined || typeof value === 'string';
}
