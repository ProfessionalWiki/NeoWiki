import type { MenuConfig, MenuItemData } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { schemaDisplayName, sortedByDisplayName } from '@/presentation/schemaDisplayName.ts';

/**
 * The value of the menu item offering to create a Schema. Codex has no value of its own for such an item,
 * so it carries one no Schema name can take: a name is a page title, and a title never starts with an
 * underscore.
 */
export const CREATE_SCHEMA = '__create__';

/**
 * A Codex lookup's `menu-items` and `menu-config`. The lookup hands its whole menuConfig to its CdxMenu,
 * whose footer Codex's MenuConfig type leaves out.
 */
export interface SchemaMenu {
	items: MenuItemData[];
	config: MenuConfig & { footer: MenuItemData | null };
}

/**
 * Which Schemas a menu shows the name of beside the label the reader sees: every one, for a field that shows the
 * chosen Schemas by name, or only those the reader sees under the same label as another.
 */
export type SchemaNamesInMenu = 'beside-every-label' | 'beside-shared-labels';

/**
 * The menu of a lookup for choosing Schemas: those that match the search text, each under the label the reader
 * sees, in the order of those labels, and, unless `offeredName` is null, the offer to create a Schema of that name.
 *
 * Below Schemas, the offer is the menu's footer, which Codex keeps in view while the list scrolls. Alone,
 * it is the list's one item: a Codex lookup neither opens a menu nor moves through it by keyboard while it
 * has no items.
 */
export function schemaMenu(
	summaries: readonly SchemaSummary[],
	search: string,
	offeredName: string | null,
	names: SchemaNamesInMenu,
): SchemaMenu {
	const items = matchingItems( summaries, search, names );

	if ( offeredName === null ) {
		return { items, config: { footer: null } };
	}

	const offer = creationOffer( offeredName );

	return items.length === 0 ?
		{ items: [ offer ], config: { footer: null } } :
		{ items, config: { footer: offer } };
}

/**
 * Whether the Schema's name or the label the reader sees contains the search text, in any case.
 */
export function matchesSchemaSearch( summary: SchemaSummary, search: string ): boolean {
	const text = search.trim().toLowerCase();

	return summary.name.toLowerCase().includes( text ) || schemaDisplayName( summary ).toLowerCase().includes( text );
}

function matchingItems( summaries: readonly SchemaSummary[], search: string, names: SchemaNamesInMenu ): MenuItemData[] {
	const sharedLabels = names === 'beside-every-label' ? null : labelsSharedBy( summaries );

	return sortedByDisplayName( summaries.filter( ( summary ) => matchesSchemaSearch( summary, search ) ) )
		.map( ( summary ) => {
			const shownName = schemaDisplayName( summary );
			const showsName = shownName !== summary.name && ( sharedLabels === null || sharedLabels.has( shownName ) );

			return {
				label: shownName,
				value: summary.name,
				description: summary.description || undefined,
				supportingText: showsName ? summary.name : undefined,
			};
		} );
}

/**
 * The labels the reader sees more than one of the Schemas under.
 */
function labelsSharedBy( summaries: readonly SchemaSummary[] ): Set<string> {
	const seen = new Set<string>();
	const shared = new Set<string>();

	for ( const shownName of summaries.map( schemaDisplayName ) ) {
		if ( seen.has( shownName ) ) {
			shared.add( shownName );
		}

		seen.add( shownName );
	}

	return shared;
}

function creationOffer( name: string ): MenuItemData {
	return {
		value: CREATE_SCHEMA,
		label: name === '' ?
			mw.msg( 'neowiki-schema-picker-create' ) :
			mw.msg( 'neowiki-schema-picker-create-named', name ),
		icon: cdxIconAdd,
	};
}
