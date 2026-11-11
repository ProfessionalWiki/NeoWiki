import { mount, VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { h, nextTick, onMounted, ref, type Component, type Ref } from 'vue';
import EditorPanes from '@/components/common/EditorPanes.vue';
import PaneDivider from '@/components/common/PaneDivider.vue';

const START_PANE = {
	resizeLabel: 'Resize the list',
	storageKey: 'neowiki-test-pane-size',
	defaultSize: 320,
	minSize: 192,
	minOtherSize: 336,
};

function mountPanes( props: Record<string, unknown> = {} ): VueWrapper {
	return mount( EditorPanes, {
		props: { startPane: START_PANE, ...props },
		slots: {
			start: '<ul class="list"><li>Name</li></ul>',
			default: '<form class="form"></form>',
		},
	} );
}

// The host decides on each render whether to pass a start pane, as a `v-if` on its slot does.
function mountWithStartPaneWhile( showsStart: Ref<boolean>, main: Component = { render: () => h( 'form' ) } ): VueWrapper {
	return mount( {
		setup() {
			return () => h( EditorPanes, { startPane: START_PANE }, {
				...( showsStart.value ? { start: () => h( 'ul', { class: 'list' } ) } : {} ),
				default: () => h( main ),
			} );
		},
	} );
}

function paneHolding( wrapper: VueWrapper, selector: string ): Element {
	return wrapper.get( selector ).element.closest( '.ext-neowiki-editor-panes__pane' ) as Element;
}

describe( 'EditorPanes', () => {

	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	describe( 'with one pane', () => {

		it( 'shows no divider', () => {
			const wrapper = mount( EditorPanes, { slots: { default: '<form class="form"></form>' } } );

			expect( wrapper.findComponent( PaneDivider ).exists() ).toBe( false );
		} );
	} );

	describe( 'with a start pane', () => {

		it( 'puts the divider between the start pane and the main one', () => {
			const wrapper = mountPanes();

			const children = [ ...wrapper.element.children ];

			expect( children[ 0 ].contains( wrapper.get( '.list' ).element ) ).toBe( true );
			expect( children[ 1 ] ).toBe( wrapper.findComponent( PaneDivider ).element );
			expect( children[ 2 ].contains( wrapper.get( '.form' ).element ) ).toBe( true );
		} );

		it( 'points the divider at the start pane it sizes', () => {
			const wrapper = mountPanes();

			const startPane = paneHolding( wrapper, '.list' );

			expect( startPane.id ).not.toBe( '' );
			expect( wrapper.findComponent( PaneDivider ).props( 'controls' ) ).toBe( startPane.id );
		} );

	} );

	it( 'shows a start pane the host adds later', async () => {
		const showsStart = ref( false );
		const wrapper = mountWithStartPaneWhile( showsStart );

		showsStart.value = true;
		await nextTick();

		expect( wrapper.findComponent( PaneDivider ).exists() ).toBe( true );
		expect( wrapper.find( '.list' ).exists() ).toBe( true );
	} );

	it( 'drops a start pane the host takes away', async () => {
		const showsStart = ref( true );
		const wrapper = mountWithStartPaneWhile( showsStart );

		showsStart.value = false;
		await nextTick();

		expect( wrapper.findComponent( PaneDivider ).exists() ).toBe( false );
		expect( wrapper.find( '.list' ).exists() ).toBe( false );
	} );

	it( 'keeps what the main pane holds while the start pane comes and goes', async () => {
		const mounts = vi.fn();
		const Form: Component = {
			setup() {
				onMounted( mounts );
				return () => h( 'form' );
			},
		};
		const showsStart = ref( false );
		mountWithStartPaneWhile( showsStart, Form );

		showsStart.value = true;
		await nextTick();
		showsStart.value = false;
		await nextTick();

		expect( mounts ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'hands the host the main pane\'s scroller', async () => {
		const panes = ref<{ mainPane: HTMLElement | null } | null>( null );
		const wrapper = mount( {
			setup() {
				return () => h( EditorPanes, { ref: panes }, { default: () => h( 'form', { class: 'form' } ) } );
			},
		} );
		await nextTick();

		expect( panes.value?.mainPane ).toBe( paneHolding( wrapper, '.form' ) );
	} );

	// Class names its stylesheet lays the panes out by: jsdom resolves no layout.
	it( 'sets its panes side by side where it has a start pane', () => {
		const wrapper = mountPanes();

		expect( wrapper.classes() ).toContain( 'ext-neowiki-editor-panes--two' );
	} );

	it( 'stacks its panes below desktop width for a host that asks', () => {
		const wrapper = mountPanes( { stacksBelowDesktop: true } );

		expect( wrapper.classes() ).toContain( 'ext-neowiki-editor-panes--stacks-below-desktop' );
	} );
} );
