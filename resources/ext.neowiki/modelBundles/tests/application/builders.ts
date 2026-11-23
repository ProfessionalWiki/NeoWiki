import type { Schema } from '@/domain/Schema';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { itemHash, schemaItem, subjectItem } from '../../src/application/canonical';
import type { BundleMetadata, JsonObject, ModelBundle, SubjectEntry } from '../../src/application/ModelBundle';
import type { InstallRecord } from '../../src/application/InstallRecord';
import { CurrentStateReader } from '../../src/application/CurrentStateReader';
import { planInstalls, type InstallPlan } from '../../src/application/installPlan';
import type { InMemoryDataModel } from './InMemoryDataModel';

export const VOCABULARIES: BundleMetadata = { name: 'Vocabularies', version: '2', description: 'Controlled lists' };

export function subjectEntry( overrides: Partial<SubjectEntry> = {} ): SubjectEntry {
	return {
		schema: 'Gender Types',
		label: 'female',
		statements: {},
		dependentSubjects: [],
		pageTitle: null,
		...overrides,
	};
}

export function schemaJson( schema: Schema ): JsonObject {
	return new SchemaSerializer().schemaToJson( schema );
}

/**
 * A file of the Vocabularies bundle, unless the extra fields say otherwise.
 */
export function bundleOf(
	subjects: [ string, SubjectEntry ][],
	schemas: Schema[] = [],
	extra: Partial<ModelBundle> = {},
): ModelBundle {
	return {
		metadata: VOCABULARIES,
		schemas: new Map( schemas.map( ( schema ) => [ schema.getName(), schemaJson( schema ) ] ) ),
		layouts: new Map(),
		mappings: new Map(),
		subjects: new Map( subjects ),
		...extra,
	};
}

/**
 * The record of the Vocabularies bundle installed with the given Subjects and Schemas, unless the overrides
 * say otherwise.
 */
export function installed(
	subjects: [ string, SubjectEntry ][],
	schemas: Schema[] = [],
	overrides: Partial<InstallRecord> = {},
): InstallRecord {
	return {
		name: VOCABULARIES.name,
		version: '1',
		description: '',
		source: 'file',
		items: [
			...schemas.map( ( schema ) => ( { type: 'schema' as const, key: schema.getName(), item: schemaItem( schemaJson( schema ) ) } ) ),
			...subjects.map( ( [ id, entry ] ) => ( {
				type: 'subject' as const,
				key: id,
				hash: itemHash( subjectItem( entry ) ),
				label: entry.label,
				schema: entry.schema,
			} ) ),
		],
		declined: [],
		revisionId: 1,
		...overrides,
	};
}

/**
 * The plans of bundles installed together, in the order given.
 */
export function planAllOn( wiki: InMemoryDataModel, bundles: ModelBundle[] ): Promise<InstallPlan[]> {
	return planInstalls( bundles.map( ( bundle ) => ( { bundle, name: bundle.metadata?.name ?? '', source: 'file' } ) ), {
		reader: new CurrentStateReader( wiki ),
		repository: wiki,
		records: wiki.records,
		subjectFirst: false,
	} );
}

export async function planOn(
	wiki: InMemoryDataModel,
	bundle: ModelBundle,
	bundleName: string = VOCABULARIES.name,
	subjectFirst = false,
	source = 'https://example.org/vocabularies.json',
): Promise<InstallPlan> {
	const [ plan ] = await planInstalls( [ { bundle, name: bundleName, source } ], {
		reader: new CurrentStateReader( wiki ),
		repository: wiki,
		records: wiki.records,
		subjectFirst,
	} );

	return plan;
}
