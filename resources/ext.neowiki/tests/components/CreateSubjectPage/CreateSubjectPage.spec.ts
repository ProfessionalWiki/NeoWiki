import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import CreateSubjectPage from '@/components/CreateSubjectPage/CreateSubjectPage.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const SubjectCreatorDialogStub = {
	template: '<div class="subject-creator-stub" />',
	props: [ 'hostPage', 'initialSchemaName', 'open' ],
	emits: [ 'update:open' ],
};

describe( 'CreateSubjectPage', () => {
	let pinia: ReturnType<typeof createPinia>;

	function mountPage( props: Record<string, unknown> = {} ): VueWrapper {
		return mount( CreateSubjectPage, {
			props,
			global: {
				plugins: [ pinia ],
				mocks: { $i18n: createI18nMock() },
				stubs: { SubjectCreatorDialog: SubjectCreatorDialogStub, CdxIcon: true },
			},
		} );
	}

	beforeEach( () => {
		setupMwMock( { functions: [ 'msg' ] } );
		pinia = createPinia();
		setActivePinia( pinia );
	} );

	it( 'opens the creator as soon as the page loads', () => {
		mountPage();

		expect( useSubjectStore().subjectCreatorOpen ).toBe( true );
	} );

	it( 'hands the creator the pinned schema, and no page of its own to offer', () => {
		const wrapper = mountPage( { schemaName: 'Person' } );
		const dialog = wrapper.findComponent( SubjectCreatorDialog );

		expect( dialog.props( 'hostPage' ) ).toBeNull();
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'Person' );
	} );

	it( 'reopens the creator from its button after it was closed', async () => {
		const wrapper = mountPage();
		const store = useSubjectStore();
		store.closeSubjectCreator();

		await wrapper.find( 'button' ).trigger( 'click' );

		expect( store.subjectCreatorOpen ).toBe( true );
	} );

	it( 'shows the creator open while the store says so', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'closes the creator in the store when the dialog closes', () => {
		const wrapper = mountPage();

		wrapper.findComponent( SubjectCreatorDialog ).vm.$emit( 'update:open', false );

		expect( useSubjectStore().subjectCreatorOpen ).toBe( false );
	} );
} );
