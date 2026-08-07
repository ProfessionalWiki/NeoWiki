import { DOMWrapper, mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

const SUMMARIES = [
	{ name: 'Product', description: 'A product', propertyCount: 2 },
	{ name: 'Office', description: 'A physical location', propertyCount: 4 },
	{ name: 'City', description: '', propertyCount: 3 },
];

// The picker is mounted with the real Codex component: which of a keystroke, a click
// or a keyboard confirmation counts as picking a schema is decided by Codex, so a
// stubbed field would only assert this component's own wiring back to itself.
describe( 'SchemaPicker', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: ReturnType<typeof useSchemaStore>;
	const wrappers: VueWrapper[] = [];

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.fetchAllSchemaSummaries = vi.fn().mockResolvedValue( SUMMARIES );
	} );

	afterEach( () => {
		wrappers.splice( 0 ).forEach( ( wrapper ) => wrapper.unmount() );
	} );

	// Attached to the document so focus lands on the field: jsdom ignores focus() on a
	// detached element, which would silently skip the menu-opening behaviour.
	function mountPicker( props: Record<string, unknown> = {} ): VueWrapper {
		const wrapper = mount( SchemaPicker, {
			props,
			attachTo: document.body,
			global: {
				mocks: { $i18n },
				plugins: [ pinia ],
			},
		} );
		wrappers.push( wrapper );
		return wrapper;
	}

	// Focused, because CdxMenu only opens on focus: assertions about what the list shows
	// would otherwise pass against a list the user cannot see.
	async function mountLoadedPicker( props: Record<string, unknown> = {} ): Promise<VueWrapper> {
		const wrapper = mountPicker( props );
		await flushPromises();
		await focusField( wrapper );
		return wrapper;
	}

	function field( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> {
		return wrapper.find( 'input' );
	}

	function fieldText( wrapper: VueWrapper ): string {
		return field( wrapper ).element.value;
	}

	function listedSchemas( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-menu-item__text__label' ).map( ( label ) => label.text() );
	}

	function listedDescriptions( wrapper: VueWrapper ): string[] {
		return wrapper.findAll( '.cdx-menu-item__text__description' ).map( ( d ) => d.text() );
	}

	function chosenSchema( wrapper: VueWrapper ): string | null {
		const chosen = wrapper.find( '[role="option"][aria-selected="true"] .cdx-menu-item__text__label' );
		return chosen.exists() ? chosen.text() : null;
	}

	async function type( wrapper: VueWrapper, text: string ): Promise<void> {
		await field( wrapper ).setValue( text );
		await flushPromises();
	}

	async function focusField( wrapper: VueWrapper ): Promise<void> {
		await field( wrapper ).trigger( 'focus' );
		await nextTick();
	}

	async function clickSchema( wrapper: VueWrapper, name: string ): Promise<void> {
		const item = wrapper.findAll( '[role="option"]' )
			.find( ( candidate ) => candidate.text().includes( name ) );
		expect( item, `no menu entry for ${ name }` ).toBeDefined();
		await item!.trigger( 'click' );
		await flushPromises();
	}

	async function pressKey( wrapper: VueWrapper, key: string ): Promise<void> {
		await field( wrapper ).trigger( 'keydown', { key } );
		await flushPromises();
	}

	describe( 'browsing and filtering', () => {
		it( 'lists every schema with its description', async () => {
			const wrapper = await mountLoadedPicker();

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City' ] );
			expect( listedDescriptions( wrapper ) ).toEqual( [ 'A product', 'A physical location' ] );
		} );

		it( 'lists only the schemas matching the typed text', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'off' );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Office' ] );
		} );

		it( 'ignores whitespace around the typed text when filtering', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, '  off  ' );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Office' ] );
		} );

		it( 'lists every schema again when the field is cleared', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'off' );
			await type( wrapper, '' );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City' ] );
		} );

		it( 'lists every schema again after one is picked', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'off' );
			await clickSchema( wrapper, 'Office' );

			expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City' ] );
		} );

		it( 'lists no schemas and reports the failure when loading them fails', async () => {
			const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
			schemaStore.fetchAllSchemaSummaries = vi.fn().mockRejectedValue( new Error( 'load failed' ) );

			const wrapper = await mountLoadedPicker();

			expect( listedSchemas( wrapper ) ).toEqual( [] );
			expect( field( wrapper ).exists() ).toBe( true );
			expect( consoleError ).toHaveBeenCalled();
			consoleError.mockRestore();
		} );
	} );

	describe( 'picking a schema', () => {
		it( 'does not pick a schema whose name is typed out in full', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'Office' );

			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'does not pick a schema while the typed text only partially matches one', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'Offic' );

			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'does not pick a schema whose name is typed out in full and confirmed with enter', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'Office' );
			await pressKey( wrapper, 'Enter' );

			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'picks the schema whose menu entry is clicked', async () => {
			const wrapper = await mountLoadedPicker();

			await clickSchema( wrapper, 'Office' );

			expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Office' ] ] );
		} );

		it( 'picks the schema highlighted with the arrow keys and confirmed with enter', async () => {
			const wrapper = await mountLoadedPicker();

			await pressKey( wrapper, 'ArrowDown' );
			await pressKey( wrapper, 'ArrowDown' );
			await pressKey( wrapper, 'Enter' );

			expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Office' ] ] );
		} );

		it( 'picks the clicked schema when its name was already typed out in full', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'Office' );
			await clickSchema( wrapper, 'Office' );

			expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Office' ] ] );
		} );

		it( 'picks the confirmed schema when its name was already typed out in full', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'Office' );
			await pressKey( wrapper, 'ArrowDown' );
			await pressKey( wrapper, 'Enter' );

			expect( wrapper.emitted( 'select' ) ).toEqual( [ [ 'Office' ] ] );
		} );

		it( 'reports nothing when the picked schema is already the committed one', async () => {
			const wrapper = await mountLoadedPicker( { selected: 'Office' } );

			await clickSchema( wrapper, 'Office' );

			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'shows the picked schema in the field', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'off' );
			await clickSchema( wrapper, 'Office' );

			expect( fieldText( wrapper ) ).toBe( 'Office' );
		} );
	} );

	describe( 'reverting uncommitted typing', () => {
		it( 'restores the committed schema in the field on blur', async () => {
			const wrapper = await mountLoadedPicker( { selected: 'Product' } );

			await type( wrapper, 'xyz' );
			await field( wrapper ).trigger( 'blur' );
			await nextTick();

			expect( fieldText( wrapper ) ).toBe( 'Product' );
			expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City' ] );
			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'empties a field that has no committed schema on blur', async () => {
			const wrapper = await mountLoadedPicker();

			await type( wrapper, 'xyz' );
			await field( wrapper ).trigger( 'blur' );
			await nextTick();

			expect( fieldText( wrapper ) ).toBe( '' );
			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'emits blur so the consumer can mark the field touched', async () => {
			const wrapper = await mountLoadedPicker();

			await field( wrapper ).trigger( 'blur' );

			expect( wrapper.emitted( 'blur' ) ).toBeTruthy();
		} );
	} );

	describe( 'reflecting the committed schema', () => {
		it( 'shows the schema it was given', async () => {
			const wrapper = await mountLoadedPicker( { selected: 'Product' } );

			expect( fieldText( wrapper ) ).toBe( 'Product' );
		} );

		it( 'shows a schema committed after it was created', async () => {
			const wrapper = await mountLoadedPicker();

			await wrapper.setProps( { selected: 'City' } );

			expect( fieldText( wrapper ) ).toBe( 'City' );
		} );

		it( 'marks a newly committed schema as the chosen one in the list', async () => {
			const wrapper = await mountLoadedPicker();

			await wrapper.setProps( { selected: 'City' } );

			expect( chosenSchema( wrapper ) ).toBe( 'City' );
		} );

		it( 'shows a committed schema that is missing from the list', async () => {
			const wrapper = await mountLoadedPicker();

			await wrapper.setProps( { selected: 'Archived' } );

			expect( fieldText( wrapper ) ).toBe( 'Archived' );
		} );

		it( 'keeps showing a committed schema that is missing from the list on blur', async () => {
			const wrapper = await mountLoadedPicker( { selected: 'Archived' } );

			await type( wrapper, 'xyz' );
			await field( wrapper ).trigger( 'blur' );
			await flushPromises();

			expect( fieldText( wrapper ) ).toBe( 'Archived' );
		} );

		it( 'empties the field when the committed schema is cleared', async () => {
			const wrapper = await mountLoadedPicker( { selected: 'Product' } );

			await wrapper.setProps( { selected: null } );

			expect( fieldText( wrapper ) ).toBe( '' );
		} );
	} );

	describe( 'focusing', () => {
		// The schemas are delivered only after focus() has been called, as they are over the
		// network: the field must end up focused with its list open regardless.
		it( 'lists every schema straight away when focused, without waiting for a second focus', async () => {
			let deliverSchemas: ( summaries: typeof SUMMARIES ) => void = () => undefined;
			schemaStore.getAllSchemaSummaries = vi.fn().mockReturnValue(
				new Promise( ( resolve ) => {
					deliverSchemas = resolve;
				} ),
			);
			const wrapper = mountPicker();

			const focusing = ( wrapper.vm as unknown as { focus: () => Promise<void> } ).focus();
			await nextTick();
			deliverSchemas( SUMMARIES );
			await focusing;
			await nextTick();

			expect( document.activeElement ).toBe( field( wrapper ).element );
			expect( field( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
			expect( listedSchemas( wrapper ) ).toEqual( [ 'Product', 'Office', 'City' ] );
		} );
	} );
} );
