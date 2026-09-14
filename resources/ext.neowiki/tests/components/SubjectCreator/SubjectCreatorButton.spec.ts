import { mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SubjectCreatorButton from '@/components/SubjectCreator/SubjectCreatorButton.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { CdxDialogStub, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import type { InitialPage } from '@/components/SubjectCreator/InitialPage.ts';

const canCreateSubjectPage = ref( true );
const checkCreateSubjectPagePermission = vi.fn();

vi.mock( '@/composables/useSubjectPermissions.ts', () => ( {
	useSubjectPermissions: () => ( {
		canCreateSubjectPage,
		checkCreateSubjectPagePermission,
	} ),
} ) );

const NEW_PAGE: InitialPage = { choice: 'newPage', fixed: false };

describe( 'SubjectCreatorButton', () => {
	let pinia: ReturnType<typeof createPinia>;

	beforeEach( () => {
		setupMwMock();
		pinia = createPinia();
		setActivePinia( pinia );
		canCreateSubjectPage.value = true;
		checkCreateSubjectPagePermission.mockClear();
	} );

	async function mountButton( props: Record<string, unknown> = {} ): Promise<VueWrapper> {
		const wrapper = mount( SubjectCreatorButton, {
			props: {
				hostPage: null,
				initialPage: NEW_PAGE,
				...props,
			},
			global: {
				plugins: [ pinia ],
				stubs: {
					CdxButton: { template: '<button class="cdx-button-stub"><slot /></button>' },
					CdxIcon: true,
					CdxDialog: CdxDialogStub,
					SubjectCreatorDialog: true,
				},
				mocks: { $i18n: createI18nMock() },
			},
		} );

		await flushPromises();

		return wrapper;
	}

	it( 'asks whether the user may create a Subject page', async () => {
		await mountButton();

		expect( checkCreateSubjectPagePermission ).toHaveBeenCalled();
	} );

	it( 'shows its button to a user who may not create a Subject page', async () => {
		canCreateSubjectPage.value = false;

		const wrapper = await mountButton();

		expect( wrapper.find( '.cdx-button-stub' ).exists() ).toBe( true );
	} );

	it( 'states nothing until its button is clicked', async () => {
		canCreateSubjectPage.value = false;

		const wrapper = await mountButton();

		expect( wrapper.find( '.cdx-dialog-stub' ).exists() ).toBe( false );
	} );

	it( 'states the wiki\'s reason instead of opening the creator when the user may not create', async () => {
		canCreateSubjectPage.value = false;
		setupMwMock( { config: { wgNeoWikiCreateSubjectPageDeniedReason: 'Limited to the group: Administrators.' } } );

		const wrapper = await mountButton();
		await wrapper.find( '.cdx-button-stub' ).trigger( 'click' );

		expect( wrapper.find( '.cdx-dialog-stub' ).text() ).toContain( 'Limited to the group: Administrators.' );
		expect( wrapper.findComponent( SubjectCreatorDialog ).exists() ).toBe( false );
	} );

	it( 'states a generic reason when the wiki gave none', async () => {
		canCreateSubjectPage.value = false;

		const wrapper = await mountButton();
		await wrapper.find( '.cdx-button-stub' ).trigger( 'click' );

		expect( wrapper.find( '.cdx-dialog-stub' ).text() ).toContain( 'neowiki-create-subject-denied' );
	} );

	it( 'opens its own dialog when clicked', async () => {
		const wrapper = await mountButton();

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( false );

		await wrapper.find( '.cdx-button-stub' ).trigger( 'click' );

		expect( wrapper.findComponent( SubjectCreatorDialog ).props( 'open' ) ).toBe( true );
	} );

	it( 'labels itself after the Schema when one is given', async () => {
		const wrapper = await mountButton( { schemaName: 'Person' } );

		expect( wrapper.find( '.cdx-button-stub' ).text() ).toContain( 'neowiki-schema-create-subject' );
		expect( wrapper.find( '.cdx-button-stub' ).text() ).toContain( 'Person' );
	} );

	it( 'labels itself generically without a Schema', async () => {
		const wrapper = await mountButton();

		expect( wrapper.find( '.cdx-button-stub' ).text() ).toContain( 'neowiki-create-subject-default-label' );
	} );

	it( 'prefers the text prop over the Schema label', async () => {
		const wrapper = await mountButton( { schemaName: 'Person', text: 'Add a person' } );

		expect( wrapper.find( '.cdx-button-stub' ).text() ).toBe( 'Add a person' );
	} );

	it( 'passes its props to the dialog', async () => {
		const initialPage: InitialPage = {
			choice: 'anotherPage',
			page: { pageId: 42, title: 'The target page' },
			fixed: true,
		};

		const wrapper = await mountButton( {
			schemaName: 'Person',
			hostPage: { hasMainSubject: true },
			initialPage,
		} );

		const dialog = wrapper.findComponent( SubjectCreatorDialog );
		expect( dialog.props( 'initialSchemaName' ) ).toBe( 'Person' );
		expect( dialog.props( 'initialPage' ) ).toEqual( initialPage );
		expect( dialog.props( 'hostPage' ) ).toEqual( { hasMainSubject: true } );
	} );
} );
