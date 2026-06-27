import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import SchemaLookup from '@/components/common/SchemaLookup.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

const CdxComboboxStub = {
	props: [ 'selected', 'menuItems' ],
	emits: [ 'update:selected', 'input', 'blur' ],
	template: '<div class="cdx-combobox-stub"></div>',
};

const SUMMARIES = [
	{ name: 'Product', description: 'A product', propertyCount: 2 },
	{ name: 'Office', description: 'A physical location', propertyCount: 4 },
	{ name: 'City', description: '', propertyCount: 3 },
];

describe( 'SchemaLookup', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: any;

	const mountComponent = ( props: Record<string, unknown> = {} ): VueWrapper => (
		mount( SchemaLookup, {
			props,
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				stubs: {
					CdxCombobox: CdxComboboxStub,
				},
			},
		} )
	);

	async function mountLoaded( props: Record<string, unknown> = {} ): Promise<VueWrapper> {
		const wrapper = mountComponent( props );
		await flushPromises();
		return wrapper;
	}

	function typeText( combobox: VueWrapper, value: string ): void {
		combobox.vm.$emit( 'input', { target: { value } } );
	}

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.getAllSchemaSummaries = vi.fn().mockResolvedValue( SUMMARIES );
	} );

	describe( 'browsing and filtering', () => {
		it( 'populates the menu with all schemas and their descriptions on mount', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			expect( combobox.props( 'menuItems' ) ).toEqual( [
				{ label: 'Product', value: 'Product', description: 'A product' },
				{ label: 'Office', value: 'Office', description: 'A physical location' },
				{ label: 'City', value: 'City', description: undefined },
			] );
		} );

		it( 'filters the menu to schemas matching the typed text', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			typeText( combobox, 'off' );
			await nextTick();

			expect( combobox.props( 'menuItems' ) ).toEqual( [
				{ label: 'Office', value: 'Office', description: 'A physical location' },
			] );
		} );

		it( 'shows all schemas again when the input is cleared', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			typeText( combobox, 'off' );
			typeText( combobox, '' );
			await nextTick();

			expect( combobox.props( 'menuItems' ) ).toHaveLength( 3 );
		} );

		it( 'leaves the menu empty without throwing when loading schemas fails', async () => {
			const consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
			schemaStore.getAllSchemaSummaries = vi.fn().mockRejectedValue( new Error( 'load failed' ) );

			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			expect( combobox.props( 'menuItems' ) ).toEqual( [] );
			expect( consoleError ).toHaveBeenCalled();
			consoleError.mockRestore();
		} );
	} );

	describe( 'committing a selection', () => {
		it( 'emits the schema when an exact schema name is selected', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			combobox.vm.$emit( 'update:selected', 'Office' );

			expect( wrapper.emitted( 'select' )?.[ 0 ] ).toEqual( [ 'Office' ] );
		} );

		it( 'does not emit for a value that is not a schema name', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			combobox.vm.$emit( 'update:selected', 'Off' );

			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'commits the canonical schema name when the input has surrounding whitespace', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			combobox.vm.$emit( 'update:selected', 'Office ' );

			expect( wrapper.emitted( 'select' )?.[ 0 ] ).toEqual( [ 'Office' ] );
		} );

		it( 'does not re-emit when the value already equals the committed schema', async () => {
			const wrapper = await mountLoaded( { selected: 'Office' } );
			const combobox = wrapper.findComponent( CdxComboboxStub );

			combobox.vm.$emit( 'update:selected', 'Office' );

			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );
	} );

	describe( 'rejecting invalid input', () => {
		it( 'reverts to the committed schema and restores the menu on blur', async () => {
			const wrapper = await mountLoaded( { selected: 'Product' } );
			const combobox = wrapper.findComponent( CdxComboboxStub );

			combobox.vm.$emit( 'update:selected', 'xyz' );
			combobox.vm.$emit( 'blur' );
			await nextTick();

			expect( combobox.props( 'selected' ) ).toBe( 'Product' );
			expect( combobox.props( 'menuItems' ) ).toHaveLength( 3 );
			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'leaves a not-yet-set field empty on blur', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			combobox.vm.$emit( 'update:selected', 'xyz' );
			combobox.vm.$emit( 'blur' );
			await nextTick();

			expect( combobox.props( 'selected' ) ).toBe( '' );
			expect( wrapper.emitted( 'select' ) ).toBeFalsy();
		} );

		it( 'emits blur so the consumer can mark the field touched', async () => {
			const wrapper = await mountLoaded();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			combobox.vm.$emit( 'blur' );

			expect( wrapper.emitted( 'blur' ) ).toBeTruthy();
		} );
	} );

	describe( 'reflecting the selected prop', () => {
		it( 'shows the selected schema in the field', () => {
			const wrapper = mountComponent( { selected: 'Product' } );
			const combobox = wrapper.findComponent( CdxComboboxStub );

			expect( combobox.props( 'selected' ) ).toBe( 'Product' );
		} );

		it( 'updates the field when the selected prop changes', async () => {
			const wrapper = mountComponent();
			const combobox = wrapper.findComponent( CdxComboboxStub );

			await wrapper.setProps( { selected: 'NewSchema' } );
			expect( combobox.props( 'selected' ) ).toBe( 'NewSchema' );

			await wrapper.setProps( { selected: null } );
			expect( combobox.props( 'selected' ) ).toBe( '' );
		} );
	} );

	it( 'exposes focus method', () => {
		const CdxComboboxInputStub = {
			template: '<div><input /></div>',
		};

		const wrapper = mount( SchemaLookup, {
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				stubs: {
					CdxCombobox: CdxComboboxInputStub,
				},
			},
		} );

		const input = wrapper.find( 'input' );
		const focusSpy = vi.spyOn( input.element, 'focus' );

		( wrapper.vm as any ).focus();

		expect( focusSpy ).toHaveBeenCalled();
	} );
} );
