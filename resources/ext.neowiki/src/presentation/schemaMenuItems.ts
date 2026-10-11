import type { MenuItemData } from '@wikimedia/codex';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';

/**
 * The menu of a field for choosing Schemas: those whose name contains the search text, in any case.
 */
export function schemaMenuItems( summaries: readonly SchemaSummary[], search: string ): MenuItemData[] {
	const text = search.trim().toLowerCase();

	return summaries
		.filter( ( summary ) => summary.name.toLowerCase().includes( text ) )
		.map( ( summary ) => ( {
			label: summary.name,
			value: summary.name,
			description: summary.description || undefined,
		} ) );
}
