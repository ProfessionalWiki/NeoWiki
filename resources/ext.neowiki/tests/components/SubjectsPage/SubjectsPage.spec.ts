import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SubjectsPage from '@/components/SubjectsPage/SubjectsPage.vue';
import SubjectsTable from '@/components/SubjectsTable/SubjectsTable.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

let mayCreateSubjectPages = false;
const canCreateSubjectPageRef = ref( false );

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateSubjectPage: canCreateSubjectPageRef,
		checkCreateSubjectPagePermission: vi.fn( async (): Promise<void> => {
			canCreateSubjectPageRef.value = mayCreateSubjectPages;
		} ),
	} ),
} ) );

const SubjectsTableStub = {
	name: 'SubjectsTable',
	template: '<div class="subjects-table-stub"></div>',
	props: [ 'initialSchema', 'canCreate' ],
	emits: [ 'update:schema', 'create' ],
};

const SubjectCreatorDialogStub = {
	template: '<div></div>',
	props: [ 'open', 'hostPage', 'initialSchemaName' ],
	emits: [ 'update:open' ],
};

function mountPage( initialSchema: string | null = null ): VueWrapper {
	setupMwMock( { functions: [ 'config', 'msg', 'util' ] } );
	const pinia = createPinia();
	setActivePinia( pinia );

	return mount( SubjectsPage, {
		props: { initialSchema },
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			stubs: { SubjectsTable: SubjectsTableStub, SubjectCreatorDialog: SubjectCreatorDialogStub },
		},
	} );
}

describe( 'SubjectsPage', () => {

	beforeEach( () => {
		mayCreateSubjectPages = false;
		canCreateSubjectPageRef.value = false;
	} );

	it( 'starts the table on the Schema from the URL', () => {
		expect( mountPage( 'Computer' ).findComponent( SubjectsTable ).props( 'initialSchema' ) ).toBe( 'Computer' );
	} );

	it( 'offers the Create button to a user who may create Subject pages', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.findComponent( SubjectsTable ).props( 'canCreate' ) ).toBe( true );
	} );

	it( 'withholds the Create button from a user who may not create Subject pages', async () => {
		const wrapper = mountPage();
		await flushPromises();

		expect( wrapper.findComponent( SubjectsTable ).props( 'canCreate' ) ).toBe( false );
	} );

	it( 'opens the Subject creator on the Schema the table asks for', async () => {
		mayCreateSubjectPages = true;
		const wrapper = mountPage();
		await flushPromises();

		wrapper.findComponent( SubjectsTable ).vm.$emit( 'create', 'Computer' );
		await flushPromises();

		const dialog = wrapper.findComponent( SubjectCreatorDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'Computer' );
		expect( dialog.props( 'hostPage' ) ).toBeNull();
	} );

	it( 'puts the chosen Schema in the URL', () => {
		const replaceState = vi.spyOn( window.history, 'replaceState' );
		const wrapper = mountPage();

		wrapper.findComponent( SubjectsTable ).vm.$emit( 'update:schema', 'Computer' );

		expect( replaceState ).toHaveBeenLastCalledWith( window.history.state, '', '/wiki/Special:Subjects/Computer' );
	} );

	it( 'takes the Schema out of the URL for every Schema', () => {
		const replaceState = vi.spyOn( window.history, 'replaceState' );
		const wrapper = mountPage( 'Computer' );

		wrapper.findComponent( SubjectsTable ).vm.$emit( 'update:schema', null );

		expect( replaceState ).toHaveBeenLastCalledWith( window.history.state, '', '/wiki/Special:Subjects' );
	} );

} );
