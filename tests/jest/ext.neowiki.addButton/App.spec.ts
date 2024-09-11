import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import * as App from '@/ext.neowiki.addButton/ts/components/App.vue';
import { useNeoWikiStore } from '@/ext.neowiki.addButton/ts/store';

describe('App.vue', () => {
	let wrapper: ReturnType<typeof shallowMount>;
	let store: ReturnType<typeof useNeoWikiStore>;

	beforeEach(() => {
		const pinia = createPinia();
		setActivePinia(pinia);

		store = useNeoWikiStore();

		wrapper = shallowMount(App, {
			global: {
				plugins: [pinia],
				provide: {
					store: store
				}
			}
		});
	});

	it('renders correctly and handles schema selection', async () => {
		// Check if the component renders
		expect(wrapper.find('.neowiki-component').exists()).toBe(true);
		expect(wrapper.find('h2').text()).toBe('NeoWiki Schema Selector');

		// Check if the select element exists and has the correct number of options
		const select = wrapper.find('select');
		expect(select.exists()).toBe(true);
		expect(select.findAll('option').length).toBe(6); // 5 schema types + 1 default option

		// Simulate selecting a schema type
		await select.setValue('Person');

		// Check if the store's selectedSchemaType was updated
		expect(store.selectedSchemaType).toBe('Person');

		// Check if the selected schema is displayed correctly
		const selectedSchema = wrapper.find('.selected-schema');
		expect(selectedSchema.exists()).toBe(true);
		expect(selectedSchema.text()).toContain('Selected Schema: "Person"');

		// You can add more specific tests for the properties of the selected schema
		const propertyList = wrapper.findComponent({ name: 'PropertyList' });
		expect(propertyList.exists()).toBe(true);
		// Note: With shallowMount, child components are stubbed, so we can't directly test their content
	});
});
