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
		third: 'Third fugue',
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

	// An item may declare that it has children before it holds any, so a row whose subtree has
	// not been asked for still gets a control to ask with. `expandable` is what the control and
	// `aria-expanded` are read from; `children` alone can only ever describe an open item.
	describe( 'Disclosure', () => {
		const collapsedRoot = item( 'root', 'Root', { expandable: true, expanded: false } );

		it( 'draws a disclosure control inside the row of an expandable item', () => {
			const wrapper = mountTree( [ collapsedRoot ] );

			const control = row( node( wrapper, 'root' ) ).get( '.ext-neowiki-tree__twisty' );
			expect( control.find( '.cdx-icon' ).exists() ).toBe( true );
			// Hidden from assistive technology and unreachable by Tab: the state it would
			// announce is already on the treeitem, and a control inside one would be a second
			// tab stop in a widget that may only have one.
			expect( control.attributes( 'aria-hidden' ) ).toBe( 'true' );
			expect( control.attributes( 'tabindex' ) ).toBeUndefined();
			expect( control.element.tagName ).not.toBe( 'BUTTON' );
		} );

		// Nothing is drawn and nothing is reserved: the control is laid over the guide line
		// rather than given a column, so a row without one is not paying for it.
		it( 'draws nothing at all on an item that declares no children', () => {
			const wrapper = mountTree( [ item( 'root', 'Root' ) ] );

			expect( row( node( wrapper, 'root' ) ).find( '.ext-neowiki-tree__twisty' ).exists() )
				.toBe( false );
		} );

		// An item whose state the caller will not change on request.
		it( 'still calls an uncollapsible parent expanded, and gives it no control', () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				collapsible: false,
				children: [ item( 'first', 'First sonata' ) ],
			} ) ] );

			expect( node( wrapper, 'root' ).attributes( 'aria-expanded' ) ).toBe( 'true' );
			expect( row( node( wrapper, 'root' ) )
				.find( '.ext-neowiki-tree__twisty' ).exists() ).toBe( false );
		} );

		it( 'answers neither arrow key\'s toggle on an uncollapsible parent', async () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				collapsible: false,
				children: [ item( 'first', 'First sonata' ) ],
			} ) ] );

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowLeft' } );
			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();

			// Right still descends: what it may not do is change the item's state.
			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowRight' } );
			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'first' ).element );
		} );

		it( 'reports a collapsed item as collapsed rather than as a leaf', () => {
			const wrapper = mountTree( [ collapsedRoot ] );

			expect( node( wrapper, 'root' ).attributes( 'aria-expanded' ) ).toBe( 'false' );
		} );

		it( 'emits toggle rather than select when the disclosure control is pressed', async () => {
			const wrapper = mountTree( [ collapsedRoot ] );

			await row( node( wrapper, 'root' ) ).get( '.ext-neowiki-tree__twisty' ).trigger( 'click' );

			expect( wrapper.emitted( 'toggle' ) ).toHaveLength( 1 );
			expect( ( wrapper.emitted( 'toggle' ) as unknown[][] )[ 0 ][ 0 ] )
				.toMatchObject( { key: 'root' } );
			expect( wrapper.emitted( 'select' ) ).toBeUndefined();
		} );

		// Whether the children exist at all is the caller's decision, and NeoTree does not
		// second-guess it — but an item the caller calls closed never prints them, so the
		// printed order and the arrow keys that follow it cannot reach a hidden row.
		it( 'prints no children for an item it was told is collapsed', () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				expandable: true,
				expanded: false,
				children: [ item( 'child', 'Child' ) ],
			} ) ] );

			expect( nodes( wrapper ) ).toHaveLength( 1 );
			expect( wrapper.find( '[role="group"]' ).exists() ).toBe( false );
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

		// Otherwise Shift+Tab back into the tree lands on the row an earlier arrow key left,
		// not on the one the user last chose.
		it( 'moves the tab stop to a row selected with the mouse', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowDown' } );
			await row( node( wrapper, 'second' ) ).trigger( 'click' );

			expect( tabStops( wrapper ).length ).toBe( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'second' ).element );
		} );

	} );

	// The tree pattern's two-step contract: Right opens a closed node and then descends into an
	// open one; Left closes an open node and then climbs out of a closed one.
	describe( 'Left and Right', () => {
		// root ── Sonatas ── first (closed, one child) ── second
		const closedFirst: NeoTreeItem<string> = item( 'root', 'Root', {
			children: [
				item( 'first', 'First sonata', {
					groupLabel: 'Sonatas',
					expandable: true,
					expanded: false,
					children: [ item( 'allegro', 'Allegro', { groupLabel: 'Movements' } ) ],
				} ),
				item( 'second', 'Second sonata', { groupLabel: 'Sonatas' } ),
			],
		} );

		// Up and Down walk the printed order, which must not reach a row nobody can see. Pressed
		// from the closed row rather than the root, where a guard that skipped only the root's
		// own hidden grandchildren would still look right.
		it( 'Down skips what a closed node is hiding', async () => {
			const wrapper = mountTree( [ closedFirst ] );

			await node( wrapper, 'first' ).trigger( 'keydown', { key: 'ArrowDown' } );

			expect( tabStops( wrapper ) ).toHaveLength( 1 );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'second' ).element );
		} );

		// Two children, so descending onto the bottom of the group cannot pass for the top.
		it( 'Right moves onto the first child of an open node, not the last', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowRight' } );

			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'first' ).element );
		} );

		it( 'Right opens a closed node and leaves the tab stop on it', async () => {
			const wrapper = mountTree( [ closedFirst ] );

			await node( wrapper, 'first' ).trigger( 'keydown', { key: 'ArrowRight' } );

			expect( ( wrapper.emitted( 'toggle' ) as unknown[][] )[ 0 ][ 0 ] )
				.toMatchObject( { key: 'first' } );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'first' ).element );
		} );

		it( 'Right moves into the first child of an open node', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'first' ).trigger( 'keydown', { key: 'ArrowRight' } );

			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'allegro' ).element );
		} );

		it( 'Right does nothing on a node with nothing under it', async () => {
			const wrapper = mountTree();
			// Moved onto Second first: the tab stop starts on the active root, so a Right that
			// silently moved it would otherwise look the same as a Right that did nothing.
			await node( wrapper, 'second' ).trigger( 'keydown', { key: 'Home' } );
			await node( wrapper, 'second' ).trigger( 'keydown', { key: 'End' } );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'second' ).element );

			await node( wrapper, 'second' ).trigger( 'keydown', { key: 'ArrowRight' } );

			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'second' ).element );
		} );

		it( 'Left closes an open node and leaves the tab stop on it', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'first' ).trigger( 'keydown', { key: 'ArrowLeft' } );

			expect( ( wrapper.emitted( 'toggle' ) as unknown[][] )[ 0 ][ 0 ] )
				.toMatchObject( { key: 'first' } );
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'first' ).element );
		} );

		it( 'Left moves to the parent of a closed node', async () => {
			const wrapper = mountTree( [ closedFirst ] );

			await node( wrapper, 'first' ).trigger( 'keydown', { key: 'ArrowLeft' } );

			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'root' ).element );
		} );

		it( 'Left moves to the parent of a node with nothing under it', async () => {
			const wrapper = mountTree();

			await node( wrapper, 'allegro' ).trigger( 'keydown', { key: 'ArrowLeft' } );

			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'first' ).element );
		} );

		// An open root closes on Left like any other open node; what has nowhere to go is a node
		// that is already closed and has nothing above it.
		it( 'Left does nothing on a closed node at the top of the tree', async () => {
			const wrapper = mountTree( [ item( 'root', 'Root', {
				expandable: true,
				expanded: false,
				children: [ item( 'first', 'First sonata' ) ],
			} ) ] );

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowLeft' } );

			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
			expect( tabStops( wrapper )[ 0 ].element ).toBe( node( wrapper, 'root' ).element );
		} );

		it( 'Left closes an open root', async () => {
			const wrapper = mountTree( [ closedFirst ] );

			await node( wrapper, 'root' ).trigger( 'keydown', { key: 'ArrowLeft' } );

			expect( ( wrapper.emitted( 'toggle' ) as unknown[][] )[ 0 ][ 0 ] )
				.toMatchObject( { key: 'root' } );
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

		it( 'prints a caption once above a run of more than one sibling', () => {
			const wrapper = mountTree( captioned );

			expect( wrapper.findAll( '.ext-neowiki-tree__edge' ).map( ( edge ) => edge.text() ) )
				.toEqual( [ 'Sonatas' ] );
		} );

		// A caption over a single row is a line of chrome introducing a line of content, so it
		// moves onto the row it introduces. A caption over several still heads them.
		it( 'folds a caption heading a single row onto that row', () => {
			const wrapper = mountTree( captioned );

			expect( row( node( wrapper, 'third' ) ).get( '.ext-neowiki-tree__node-caption' ).text() )
				.toBe( 'Fugues' );
			expect( row( node( wrapper, 'first' ) ).find( '.ext-neowiki-tree__node-caption' ).exists() )
				.toBe( false );
		} );

		// Folding moves the caption from naming the group to naming the row, which is the more
		// reliable carrier: a group name is announced on entry and inconsistently across AT.
		it( 'puts a folded caption inside the name the treeitem takes', () => {
			const wrapper = mountTree( captioned );

			const treeitem = node( wrapper, 'third' );
			const named = wrapper.get( `#${ treeitem.attributes( 'aria-labelledby' ) }` );
			expect( named.text() ).toBe( 'FuguesThird fugue' );
		} );

		// Otherwise the property is announced twice: once entering the group, once on the row.
		it( 'leaves a group whose caption folded without a name of its own', () => {
			const wrapper = mountTree( captioned );

			const groups = wrapper.findAll( '[role="group"]' );
			expect( groups[ 0 ].attributes( 'aria-labelledby' ) ).toBeDefined();
			expect( groups[ 1 ].attributes( 'aria-labelledby' ) ).toBeUndefined();
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
			// Nor folded onto the row, where an empty caption would still indent the name.
			expect( wrapper.findAll( '.ext-neowiki-tree__node-caption' ) ).toHaveLength( 0 );
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

	describe( 'The end slot', () => {
		// The rules that keep this level with the first line of a wrapped row select it as a
		// direct child, so a slot nested one level deeper would go on rendering while silently
		// ceasing to be pinned.
		it( 'renders the slot as a direct child of the row, outside the label\'s line', () => {
			const wrapper = mountTree( [ rootWithGroups ], {
				slots: { end: '<i class="tail">{{ params.item.key }}</i>' },
			} );

			const allegroRow = row( node( wrapper, 'allegro' ) );
			expect( allegroRow.get( '.tail' ).element.parentElement )
				.toBe( allegroRow.element );
			expect( wrapper.findAll( '.tail' ).map( ( slotted ) => slotted.text() ) )
				.toEqual( [ 'root', 'first', 'allegro', 'second' ] );
		} );
	} );

	describe( 'Item labels', () => {
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
