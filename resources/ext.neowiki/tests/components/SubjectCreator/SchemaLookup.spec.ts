import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { CdxLookup } from '@wikimedia/codex';
import { createI18nMock } from '../../VueTestHelpers.ts';

const $i18n = createI18nMock();

describe( 'SchemaLookup', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: any;

	const mountComponent = (): VueWrapper => (
		mount( SchemaLookup, {
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				stubs: {
					CdxLookup: true,
				},
			},
		} )
	);

	beforeEach( () => {
		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.searchAndFetchMissingSchemas = vi.fn().mockResolvedValue( [] );
	} );

	it( 'searches for schemas when input changes', () => {
		const wrapper = mountComponent();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'test query' );

		expect( schemaStore.searchAndFetchMissingSchemas ).toHaveBeenCalledWith( 'test query' );
	} );

	it( 'updates menu items with search results', async () => {
		const mockResults = [ 'Schema1', 'Schema2' ];
		schemaStore.searchAndFetchMissingSchemas.mockResolvedValue( mockResults );

		const wrapper = mountComponent();
		const lookup = wrapper.findComponent( CdxLookup );

		lookup.vm.$emit( 'input', 'test' );
		await flushPromises();

		expect( lookup.props( 'menuItems' ) ).toEqual( [
			{ label: 'Schema1', value: 'Schema1' },
			{ label: 'Schema2', value: 'Schema2' },
		] );
	} );

	it( 'exposes focus method', () => {
		const CdxLookupStub = {
			template: '<div><input /></div>',
		};

		const wrapper = mount( SchemaLookup, {
			global: {
				mocks: {
					$i18n,
				},
				plugins: [ pinia ],
				stubs: {
					CdxLookup: CdxLookupStub,
				},
			},
		} );

		const input = wrapper.find( 'input' );
		const focusSpy = vi.spyOn( input.element, 'focus' );

		( wrapper.vm as any ).focus();

		expect( focusSpy ).toHaveBeenCalled();
	} );
} );
