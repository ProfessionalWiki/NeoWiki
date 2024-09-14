import { describe, it, expect, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { useNeoWikiStore } from '@/stores/Store';
import PropertyList from '@/components/PropertyList.vue';

describe( 'PropertyList', () => {
	let wrapper: ReturnType<typeof shallowMount>;
	let store: ReturnType<typeof useNeoWikiStore>;

	beforeEach( () => {
		const pinia = createPinia();
		setActivePinia( pinia );

		store = useNeoWikiStore();

		wrapper = shallowMount( PropertyList, {
			global: {
				plugins: [ pinia ],
				provide: {
					store: store
				}
			}
		} );
	} );

	it( 'displays the correct schema type', async () => {
		await store.updateSchemaType( 'Person' );

		expect( wrapper.text() ).toContain( 'Current Schema: Person' );
	} );

	it( 'displays "None selected" when no schema is selected', () => {
		expect( wrapper.text() ).toContain( 'Current Schema: None selected' );
	} );
} );
