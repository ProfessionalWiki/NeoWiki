import type { MenuConfig, MenuItemData } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';

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
 * The menu of a lookup for choosing Schemas: those whose name contains the search text, in any case, and,
 * unless `offeredName` is null, the offer to create a Schema of that name.
 *
 * Below Schemas, the offer is the menu's footer, which Codex keeps in view while the list scrolls. Alone,
 * it is the list's one item: a Codex lookup neither opens a menu nor moves through it by keyboard while it
 * has no items.
 */
export function schemaMenu( summaries: readonly SchemaSummary[], search: string, offeredName: string | null ): SchemaMenu {
	const items = matchingItems( summaries, search );

	if ( offeredName === null ) {
		return { items, config: { footer: null } };
	}

	const offer = creationOffer( offeredName );

	return items.length === 0 ?
		{ items: [ offer ], config: { footer: null } } :
		{ items, config: { footer: offer } };
}

function matchingItems( summaries: readonly SchemaSummary[], search: string ): MenuItemData[] {
	const text = search.trim().toLowerCase();

	return summaries
		.filter( ( summary ) => summary.name.toLowerCase().includes( text ) )
		.map( ( summary ) => ( {
			label: summary.name,
			value: summary.name,
			description: summary.description || undefined,
		} ) );
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
