import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectCreator from '@/components/SubjectCreator/SubjectCreator.vue';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
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
	let subjectStore: any;

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

		subjectStore = useSubjectStore();
		subjectStore.initNewSubject = vi.fn().mockResolvedValue( { id: 'test-subject' } );
	} );

	it( 'switches to new schema mode', async () => {
		const wrapper = mountComponent();
		const toggleGroup = wrapper.findComponent( CdxToggleButtonGroup );

		await toggleGroup.vm.$emit( 'update:modelValue', 'new' );

		expect( wrapper.findComponent( SchemaLookup ).exists() ).toBe( false );
		expect( wrapper.text() ).toContain( 'TODO: New schema UI' );
	} );

	it( 'initializes new subject when schema is selected', async () => {
		const wrapper = mountComponent();
		const schemaLookup = wrapper.findComponent( SchemaLookup );

		await schemaLookup.vm.$emit( 'select', 'TestSchema' );
		await flushPromises();

		expect( subjectStore.initNewSubject ).toHaveBeenCalledWith( 'TestSchema' );
		expect( wrapper.emitted( 'create' ) ).toBeTruthy();
		expect( wrapper.emitted( 'create' )![ 0 ] ).toEqual( [ { id: 'test-subject' } ] );
	} );
} );
