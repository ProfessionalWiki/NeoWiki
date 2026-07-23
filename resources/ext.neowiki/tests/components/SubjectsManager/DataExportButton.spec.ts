import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { shallowMount, VueWrapper } from '@vue/test-utils';
import { CdxButton, CdxMenu } from '@wikimedia/codex';
import DataExportButton from '@/components/SubjectsManager/DataExportButton.vue';
import { setupMwMock } from '../../VueTestHelpers.ts';

// useFloatingMenu drives FloatingUI against real geometry; neutralise it under jsdom while keeping
// the real CdxButton/CdxMenu so shallowMount stubs them by name and we can read props / emit events.
vi.mock( '@wikimedia/codex', async ( importOriginal ) => ( {
	...await importOriginal<typeof import( '@wikimedia/codex' )>(),
	useFloatingMenu: vi.fn(),
} ) );

function mountButton(): VueWrapper {
	return shallowMount( DataExportButton, {
		props: {
			label: 'Export',
			jsonUrl: 'JSON_URL',
			rdfUrl: ( projection: string, format: string ) => `RDF:${ projection }:${ format }`,
			projections: [ 'native', 'EDM' ],
		},
	} );
}

function menuValues( wrapper: VueWrapper ): unknown[] {
	return ( wrapper.findComponent( CdxMenu ).props( 'menuItems' ) as { value: unknown }[] )
		.map( ( item ) => item.value );
}

function triggerEl( wrapper: VueWrapper ): Element {
	return wrapper.findComponent( CdxButton ).element;
}

async function open( wrapper: VueWrapper ): Promise<void> {
	// A genuine pointer click carries detail >= 1; the component ignores detail-0 clicks (the ones a
	// native button synthesises on Enter/Space, which the keydown handler owns). vue-test-utils'
	// trigger() cannot set the read-only `detail`, so dispatch a real MouseEvent.
	triggerEl( wrapper ).dispatchEvent( new MouseEvent( 'click', { detail: 1, bubbles: true } ) );
	await wrapper.vm.$nextTick();
}

async function select( wrapper: VueWrapper, value: string ): Promise<void> {
	const menu = wrapper.findComponent( CdxMenu );
	menu.vm.$emit( 'update:selected', value );
	// Real CdxMenu always emits update:expanded(false) right after a single-select pick;
	// the component must ignore it (it owns close transitions itself). Simulate that here.
	menu.vm.$emit( 'update:expanded', false );
	await wrapper.vm.$nextTick();
}

describe( 'DataExportButton', () => {
	let openSpy: ReturnType<typeof vi.spyOn>;

	beforeEach( () => {
		setupMwMock( {
			functions: [ 'msg' ],
			messages: {
				'neowiki-managesubjects-export-json': 'JSON',
				'neowiki-managesubjects-export-format-turtle': 'Turtle',
				'neowiki-managesubjects-export-format-trig': 'TriG',
				'neowiki-managesubjects-export-back': 'Back',
				'neowiki-managesubjects-export-native': 'Native',
			},
		} );
		openSpy = vi.spyOn( window, 'open' ).mockImplementation( () => null );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'shows the three serialization choices when opened', async () => {
		const wrapper = mountButton();
		await open( wrapper );
		expect( menuValues( wrapper ) ).toEqual( [ 'json', 'turtle', 'trig' ] );
	} );

	it( 'downloads JSON in a new tab and closes', async () => {
		const wrapper = mountButton();
		await open( wrapper );
		await select( wrapper, 'json' );
		expect( openSpy ).toHaveBeenCalledWith( 'JSON_URL', '_blank', 'noopener' );
		expect( wrapper.findComponent( CdxMenu ).props( 'expanded' ) ).toBe( false );
	} );

	it( 'reveals the projection list after choosing an RDF format without navigating', async () => {
		const wrapper = mountButton();
		await open( wrapper );
		await select( wrapper, 'turtle' );
		expect( openSpy ).not.toHaveBeenCalled();
		expect( menuValues( wrapper ) ).toEqual( [ '__back__', 'native', 'EDM' ] );
		expect( wrapper.findComponent( CdxMenu ).props( 'expanded' ) ).toBe( true );
	} );

	it( 'downloads the chosen projection in the chosen format', async () => {
		const wrapper = mountButton();
		await open( wrapper );
		await select( wrapper, 'turtle' );
		await select( wrapper, 'EDM' );
		expect( openSpy ).toHaveBeenCalledWith( 'RDF:EDM:turtle', '_blank', 'noopener' );
		expect( wrapper.findComponent( CdxMenu ).props( 'expanded' ) ).toBe( false );
	} );

	it( 'returns to the serialization choice via Back', async () => {
		const wrapper = mountButton();
		await open( wrapper );
		await select( wrapper, 'trig' );
		await select( wrapper, '__back__' );
		expect( menuValues( wrapper ) ).toEqual( [ 'json', 'turtle', 'trig' ] );
		expect( openSpy ).not.toHaveBeenCalled();
	} );

	it( 'resets to the serialization level when reopened after a pick', async () => {
		const wrapper = mountButton();
		await open( wrapper );
		await select( wrapper, 'turtle' );
		await select( wrapper, 'EDM' );
		// The projection pick closed the menu; reopening must show level 1 again -- the whole
		// design relies on `expanded` only ever going false through close(), which resets the step.
		await open( wrapper );
		expect( wrapper.findComponent( CdxMenu ).props( 'expanded' ) ).toBe( true );
		expect( menuValues( wrapper ) ).toEqual( [ 'json', 'turtle', 'trig' ] );
	} );

	it( 'opens from the trigger on Enter', async () => {
		const wrapper = mountButton();
		triggerEl( wrapper ).dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );
		await wrapper.vm.$nextTick();
		expect( wrapper.findComponent( CdxMenu ).props( 'expanded' ) ).toBe( true );
		expect( menuValues( wrapper ) ).toEqual( [ 'json', 'turtle', 'trig' ] );
	} );

	it( 'closes from the trigger on Escape', async () => {
		const wrapper = mountButton();
		await open( wrapper );
		triggerEl( wrapper ).dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
		await wrapper.vm.$nextTick();
		expect( wrapper.findComponent( CdxMenu ).props( 'expanded' ) ).toBe( false );
	} );

	it( 'ignores the detail-0 click a keyboard activation synthesises', async () => {
		const wrapper = mountButton();
		// Enter opens via the keydown handler; the native button also fires a detail-0 click, which
		// must NOT toggle the menu back closed (that was the flash). Only detail > 0 clicks toggle.
		const el = triggerEl( wrapper );
		el.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );
		el.dispatchEvent( new MouseEvent( 'click', { detail: 0, bubbles: true } ) );
		await wrapper.vm.$nextTick();
		expect( wrapper.findComponent( CdxMenu ).props( 'expanded' ) ).toBe( true );
	} );
} );
