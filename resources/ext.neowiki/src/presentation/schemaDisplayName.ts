import { Schema } from '@/domain/Schema';
import type { SchemaSummary } from '@/application/SchemaLookup';
import { labelForReader } from '@/presentation/languages';

/**
 * What the UI calls the Schema: its label in the first language the reader reads that it has a label in, or else
 * its name.
 */
export function schemaDisplayName( schema: Schema | SchemaSummary ): string {
	return schema instanceof Schema ?
		schemaDisplayNameOf( schema.getName(), schema.getLabels() ) :
		schemaDisplayNameOf( schema.name, schema.labels );
}

/**
 * As schemaDisplayName(), for a Schema known by its name and labels, such as the Schema of a Subject.
 */
export function schemaDisplayNameOf( schemaName: string, labels: Readonly<Record<string, string>> | undefined ): string {
	return labelForReader( labels ) ?? schemaName;
}

/**
 * In the order of what the reader sees them called.
 */
export function sortedByDisplayName( summaries: readonly SchemaSummary[] ): SchemaSummary[] {
	return summaries
		.map( ( summary ) => ( { summary: summary, shownName: schemaDisplayName( summary ) } ) )
		.sort( ( a, b ) => a.shownName.localeCompare( b.shownName ) )
		.map( ( { summary } ) => summary );
}

export type SchemaNamer = ( schemaName: string ) => string;

/**
 * Names a Schema of the list as schemaDisplayName() does, and one the list lacks by its name.
 */
export function schemaNamerFor( schemas: readonly ( Schema | SchemaSummary )[] ): SchemaNamer {
	const shownNames = new Map( schemas.map( ( schema ) => [
		schema instanceof Schema ? schema.getName() : schema.name,
		schemaDisplayName( schema ),
	] ) );

	return ( schemaName ) => shownNames.get( schemaName ) ?? schemaName;
}
