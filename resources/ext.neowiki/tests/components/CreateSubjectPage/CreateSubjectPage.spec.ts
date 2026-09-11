import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import CreateSubjectPage from '@/components/CreateSubjectPage/CreateSubjectPage.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const SubjectCreatorDialogStub = {
	template: '<div class="subject-creator-stub" />',
	props: [ 'pageHasMainSubject', 'choosePage', 'initialSchemaName' ],
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

	it( 'hands the creator the page choice and the pinned schema', () => {
		const wrapper = mountPage( { schemaName: 'Person' } );
		const dialog = wrapper.findComponent( SubjectCreatorDialog );

		expect( dialog.props( 'choosePage' ) ).toBe( true );
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'Person' );
	} );

	it( 'reopens the creator from its button after it was closed', async () => {
		const wrapper = mountPage();
		const store = useSubjectStore();
		store.closeSubjectCreator();

		await wrapper.find( 'button' ).trigger( 'click' );

		expect( store.subjectCreatorOpen ).toBe( true );
	} );
} );
