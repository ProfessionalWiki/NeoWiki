import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectCreator from '@/components/SubjectCreator/SubjectCreator.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import { createPinia, setActivePinia } from 'pinia';
import { setupMwMock } from '../../VueTestHelpers.ts';
import { CdxToggleButtonGroup } from '@wikimedia/codex';

const SchemaLookupStub = {
	template: '<div></div>',
	methods: {
		focus: vi.fn(),
	},
};

describe( 'SubjectCreator', () => {
	let pinia: ReturnType<typeof createPinia>;

	const mountComponent = (): VueWrapper => (
		mount( SubjectCreator, {
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaLookup: SchemaLookupStub,
					CdxToggleButtonGroup: true,
				},
				mocks: {
					$i18n: ( key: string ) => ( { text: () => key } ),
				},
			},
		} )
	);

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg', 'notify' ] } );
		pinia = createPinia();
		setActivePinia( pinia );
	} );

	it( 'switches to new schema mode', async () => {
		const wrapper = mountComponent();
		const toggleGroup = wrapper.findComponent( CdxToggleButtonGroup );

		await toggleGroup.vm.$emit( 'update:modelValue', 'new' );

		expect( wrapper.findComponent( SchemaLookup ).exists() ).toBe( false );
		expect( wrapper.text() ).toContain( 'TODO: New schema UI' );
	} );
} );
