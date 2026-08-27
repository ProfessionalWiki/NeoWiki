import { mount, DOMWrapper, VueWrapper } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import NeoTree from '@/components/common/NeoTree/NeoTree.vue';
import type { NeoTreeItem } from '@/components/common/NeoTree/NeoTreeModel.ts';

// NeoTree reaches no store, service locator or domain type, so every fixture below is a
// literal of the shape it accepts and the payload it hands back is a plain string.

const TREE_LABEL = 'Course of study';

function item(
	key: string,
	label: string,
	overrides: Partial<NeoTreeItem<string>> = {},
): NeoTreeItem<string> {
	return { key, label, data: key, ...overrides };
}

// root
// └── Sonatas: first, second
//     └── (under first) Movements: allegro
const rootWithGroups: NeoTreeItem<string> = item( 'root', 'Root', {
	active: true,
	children: [
		item( 'first', 'First sonata', {
			groupLabel: 'Sonatas',
			children: [ item( 'allegro', 'Allegro', { groupLabel: 'Movements' } ) ],
		} ),
		item( 'second', 'Second sonata', { groupLabel: 'Sonatas' } ),
	],
} );

function mountTree(
	items: NeoTreeItem<string>[] = [ rootWithGroups ],
	options: { label?: string; slots?: Record<string, string> } = {},
): VueWrapper {
	return mount( NeoTree, {
		props: {
			items,
			label: options.label ?? TREE_LABEL,
		},
		slots: options.slots ?? {},
	} );
}

// Every item, in printed order: the order Up/Down move through.
function nodes( wrapper: VueWrapper ): DOMWrapper<Element>[] {
	return wrapper.findAll( '[role="treeitem"]' );
}

function node( wrapper: VueWrapper, key: string ): DOMWrapper<Element> {
	return nodes( wrapper ).filter(
		( candidate ) => candidate.get( '.ext-neowiki-tree__node-label' ).text() === labelOf( key ),
	)[ 0 ];
}

function labelOf( key: string ): string {
	const labels: Record<string, string> = {
		root: 'Root',
		first: 'First sonata',
		second: 'Second sonata',
		allegro: 'Allegro',
	};
	return labels[ key ];
}

// A node's own element spans its whole subtree, so this row is the part a user clicks.
function row( treeNode: DOMWrapper<Element> ): Omit<DOMWrapper<Element>, 'exists'> {
	return treeNode.get( '.ext-neowiki-tree__node-name' );
}

// How many role=group containers a node sits inside: the level a browser computes for it.
function groupDepthOf( treeNode: DOMWrapper<Element> ): number {
	let depth = 0;
	let ancestor = treeNode.element.parentElement;

	while ( ancestor !== null ) {
		if ( ancestor.getAttribute( 'role' ) === 'group' ) {
			depth++;
		}
		ancestor = ancestor.parentElement;
	}

	return depth;
}

function tabStops( wrapper: VueWrapper ): DOMWrapper<Element>[] {
	return nodes( wrapper ).filter( ( treeNode ) => treeNode.attributes( 'tabindex' ) === '0' );
}

describe( 'NeoTree', () => {
	describe( 'Tree structure', () => {
		it( 'marks the item list as a tree', () => {
			const wrapper = mountTree();

			expect( wrapper.get( '.ext-neowiki-tree__list' ).attributes( 'role' ) ).toBe( 'tree' );
		} );

		// A widget role takes no name from the landmark around it, and the panel prints no
		// caption it could be named from.
		it( 'names the tree widget from the label prop', () => {
			const wrapper = mountTree( [ rootWithGroups ], { label: 'Parts of the engine' } );

			expect( wrapper.get( '[role="tree"]' ).attributes( 'aria-label' ) )
				.toBe( 'Parts of the engine' );
		} );

		it( 'names the navigation landmark from the same label', () => {
			const wrapper = mountTree( [ rootWithGroups ], { label: 'Parts of the engine' } );

			expect( wrapper.get( 'nav' ).attributes( 'aria-label' ) ).toBe( 'Parts of the engine' );
		} );

		// A treeitem's children may only be reached through a group, and that group has to sit
		// inside the item it belongs to, or the browser computes the wrong depth beneath it.
		it( 'puts each item\'s children in a role=group inside that item', () => {
			const wrapper = mountTree();

			const first = node( wrapper, 'first' );
			expect( first.attributes( 'role' ) ).toBe( 'treeitem' );
			expect( groupDepthOf( first ) ).toBe( 1 );
			expect( groupDepthOf( node( wrapper, 'allegro' ) ) ).toBe( 2 );

			// Containment, from the parent's side: the group holding Allegro is inside First.
			expect( first.get( '[role="group"]' ).element.contains( node( wrapper, 'allegro' ).element ) )
				.toBe( true );
		} );

		it( 'marks an item owning a group as expanded and a leaf as neither expanded nor collapsed', () => {
			const wrapper = mountTree();

			expect( node( wrapper, 'first' ).attributes( 'aria-expanded' ) ).toBe( 'true' );
			expect( node( wrapper, 'second' ).attributes( 'aria-expanded' ) ).toBeUndefined();
		} );

		it( 'spreads an item\'s attrs onto its own element', () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				attrs: { 'data-mw-neowiki-subject-id': 's1person1111111' },
				children: [ item( 'child', 'Child' ) ],
			} ) ] );

			expect( nodes( wrapper )[ 0 ].attributes( 'data-mw-neowiki-subject-id' ) )
				.toBe( 's1person1111111' );
			expect( nodes( wrapper )[ 1 ].attributes( 'data-mw-neowiki-subject-id' ) ).toBeUndefined();
		} );
	} );

	describe( 'Roving tabindex', () => {
		// One tab stop for the whole widget: tabbed into once, then moved through by arrow key.
		it( 'gives the whole tree exactly one tab stop', () => {
			const wrapper = mountTree();

			expect( nodes( wrapper ).length ).toBe( 4 );
			expect( tabStops( wrapper ).length ).toBe( 1 );
		} );

		// WAI-ARIA APG: roving focus starts on the active item, however deep it sits.
		it( 'starts the tab stop on the item marked active', () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				children: [
					item( 'first', 'First sonata' ),
					item( 'second', 'Second sonata', { active: true } ),
				],
			} ) ] );

			expect( tabStops( wrapper ).length ).toBe( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'second' ).element );
		} );

		it( 'falls back to the first item when none is active', () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				children: [ item( 'first', 'First sonata' ) ],
			} ) ] );

			expect( tabStops( wrapper ).length ).toBe( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'root' ).element );
		} );
	} );

	describe( 'Keyboard', () => {
		it( 'ArrowDown moves the tab stop to the next item in printed order', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowDown' } );

			expect( tabStops( wrapper ).length ).toBe( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'first' ).element );
		} );

		// Printed order runs through the nesting, so the item after a parent is its own child.
		it( 'ArrowUp moves the tab stop to the previous item in printed order', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'allegro' ).trigger( 'keydown', { key: 'ArrowUp' } );

			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'first' ).element );
		} );

		it( 'ArrowUp wraps from the first item round to the last', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowUp' } );

			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'second' ).element );
		} );

		it( 'End moves the tab stop to the last item', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'End' } );

			expect( tabStops( wrapper ).length ).toBe( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'second' ).element );
		} );

		it( 'Home moves the tab stop back to the first item', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'End' } );
			await node( wrapper, 'second' ).trigger( 'keydown', { key: 'Home' } );

			expect( tabStops( wrapper ).length ).toBe( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'root' ).element );
		} );

		// The tree is always fully expanded, so it claims neither expand key. Pressed from a node
		// that is neither the first nor the active one: from the root those are the same element,
		// where a Right wired to Home would look identical to a Right that does nothing.
		it( 'leaves the tab stop where it is on Right and Left', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowDown' } );
			const moved = tabStops( wrapper )[ 0 ].element;
			expect( moved ).not.toBe( node( wrapper, 'root' ).element );

			await nodes( wrapper )[ 1 ].trigger( 'keydown', { key: 'ArrowRight' } );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( moved );

			await nodes( wrapper )[ 1 ].trigger( 'keydown', { key: 'ArrowLeft' } );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( moved );
		} );
	} );

	describe( 'Selection', () => {
		it( 'emits select with the item whose row was clicked', async () => {
			const wrapper = mountTree();

			await row( node( wrapper, 'root' ) ).trigger( 'click' );

			const selected = wrapper.emitted( 'select' )![ 0 ][ 0 ] as NeoTreeItem<string>;
			expect( wrapper.emitted( 'select' ) ).toHaveLength( 1 );
			expect( selected.key ).toBe( 'root' );
			expect( selected.data ).toBe( 'root' );
		} );

		// A node's own element spans its whole subtree, so a click between rows lands inside
		// every ancestor of whatever sits there.
		it( 'emits nothing for a click inside a subtree that is on no row', async () => {
			const wrapper = mountTree();

			await wrapper.get( '.ext-neowiki-tree__relation' ).trigger( 'click' );

			expect( wrapper.emitted( 'select' ) ).toBeUndefined();
		} );

		it( 'emits once for a nested item, not once per ancestor', async () => {
			const wrapper = mountTree();

			await row( node( wrapper, 'allegro' ) ).trigger( 'click' );

			expect( wrapper.emitted( 'select' ) ).toHaveLength( 1 );
			expect( ( wrapper.emitted( 'select' )![ 0 ][ 0 ] as NeoTreeItem<string> ).key )
				.toBe( 'allegro' );
		} );

		it( 'emits select for the item whose row was pressed with Enter', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'allegro' ).trigger( 'keydown', { key: 'Enter' } );

			expect( wrapper.emitted( 'select' ) ).toHaveLength( 1 );
			expect( ( wrapper.emitted( 'select' )![ 0 ][ 0 ] as NeoTreeItem<string> ).key )
				.toBe( 'allegro' );
		} );
	} );

	// One thing reachable by two routes is two items, and only the key tells them apart: these
	// two carry the very same `data`, so a key derived from it would collapse them into one
	// node and one focus target.
	describe( 'Item keys', () => {
		const twiceReached: NeoTreeItem<string>[] = [ {
			key: 'left:shared',
			label: 'Shared',
			data: 'shared',
			children: [],
		}, {
			key: 'right:shared',
			label: 'Shared',
			data: 'shared',
			children: [],
		} ];

		it( 'renders two items sharing one payload as two nodes under distinct keys', () => {
			const wrapper = mountTree( twiceReached );

			const keys = wrapper.findAllComponents( { name: 'NeoTreeNode' } )
				.map( ( treeNode ) => treeNode.vm.$.vnode.key );

			expect( keys ).toEqual( [ 'left:shared', 'right:shared' ] );
		} );

		it( 'keeps two items sharing one payload separately reachable', async () => {
			const wrapper = mountTree( twiceReached );

			await nodes( wrapper )[ 0 ].trigger( 'keydown', { key: 'ArrowDown' } );

			expect( tabStops( wrapper ).length ).toBe( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( nodes( wrapper )[ 1 ].element );
		} );
	} );

	describe( 'Group captions', () => {
		// Two runs of one caption each, and a third child with none.
		const captioned: NeoTreeItem<string>[] = [ item( 'root', 'Root', {
			children: [
				item( 'first', 'First sonata', { groupLabel: 'Sonatas' } ),
				item( 'second', 'Second sonata', { groupLabel: 'Sonatas' } ),
				item( 'third', 'Third fugue', { groupLabel: 'Fugues' } ),
			],
		} ) ];

		it( 'prints a caption once above a contiguous run of siblings sharing it', () => {
			const wrapper = mountTree( captioned );

			expect( wrapper.findAll( '.ext-neowiki-tree__edge' ).map( ( edge ) => edge.text() ) )
				.toEqual( [ 'Sonatas', 'Fugues' ] );
		} );

		it( 'gathers the siblings sharing a caption into that caption\'s own group', () => {
			const wrapper = mountTree( captioned );

			const groups = wrapper.findAll( '[role="group"]' );
			expect( groups.length ).toBe( 2 );
			expect( groups[ 0 ].findAll( '[role="treeitem"]' ).map(
				( treeNode ) => treeNode.get( '.ext-neowiki-tree__node-label' ).text(),
			) ).toEqual( [ 'First sonata', 'Second sonata' ] );
		} );

		// Only treeitem and group may be children of a tree, so the caption is hidden from the
		// accessibility tree and the group is named by pointing at it.
		it( 'names each group by its own visible caption', () => {
			const wrapper = mountTree( captioned );

			const group = wrapper.findAll( '[role="group"]' )[ 0 ];
			const caption = wrapper.get( `#${ group.attributes( 'aria-labelledby' ) }` );
			expect( caption.text() ).toBe( 'Sonatas' );
			expect( caption.attributes( 'aria-hidden' ) ).toBe( 'true' );
		} );

		it( 'prints no caption for a group whose items carry none', () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				children: [ item( 'first', 'First sonata' ) ],
			} ) ] );

			expect( wrapper.findAll( '.ext-neowiki-tree__edge' ) ).toHaveLength( 0 );
			expect( wrapper.get( '[role="group"]' ).attributes( 'aria-labelledby' ) ).toBeUndefined();
		} );
	} );

	describe( 'The trailing slot', () => {
		it( 'renders the slot inside the row of the item it is given', () => {
			const wrapper = mountTree( [ rootWithGroups ], {
				slots: { trailing: '<i class="mark">{{ params.item.key }}</i>' },
			} );

			expect( row( node( wrapper, 'allegro' ) ).get( '.mark' ).text() ).toBe( 'allegro' );
			expect( wrapper.findAll( '.mark' ).map( ( mark ) => mark.text() ) )
				.toEqual( [ 'root', 'first', 'allegro', 'second' ] );
		} );
	} );

	describe( 'Item labels', () => {
		it( 'prints the secondary label after the label, set apart from it', () => {
			const wrapper = mountTree( [ item( 'root', 'Root', { secondaryLabel: 'Person' } ) ] );

			expect( row( node( wrapper, 'root' ) ).get( '.ext-neowiki-tree__node-secondary' ).text() )
				.toBe( 'Person' );
		} );

		it( 'prints nothing in place of an absent secondary label', () => {
			const wrapper = mountTree( [ item( 'root', 'Root' ) ] );

			expect( row( node( wrapper, 'root' ) ).find( '.ext-neowiki-tree__node-secondary' ).exists() )
				.toBe( false );
		} );

		it( 'marks the active item as selected and highlighted', () => {
			const wrapper = mountTree();

			expect( node( wrapper, 'root' ).attributes( 'aria-selected' ) ).toBe( 'true' );
			expect( node( wrapper, 'root' ).classes() ).toContain( 'ext-neowiki-tree__node--active' );
			expect( node( wrapper, 'first' ).attributes( 'aria-selected' ) ).toBe( 'false' );
			expect( node( wrapper, 'first' ).classes() )
				.not.toContain( 'ext-neowiki-tree__node--active' );
		} );
	} );
} );
