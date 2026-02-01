import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SubjectCreator from '@/components/SubjectCreator/SubjectCreator.vue';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { CdxDialog } from '@wikimedia/codex';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { Subject } from '@/domain/Subject.ts';

const SubjectCreatorStub = {
	template: '<div></div>',
	emits: [ 'draft' ],
};

const SubjectEditorDialogStub = {
	template: '<div></div>',
	props: [ 'subject', 'open', 'onSave', 'onSaveSchema' ],
	emits: [ 'update:open' ],
};

const CdxDialogStub = {
	template: '<div><slot /></div>',
	props: [ 'open' ],
};

describe( 'SubjectCreatorDialog', () => {
	let pinia: ReturnType<typeof createPinia>;
	let subjectStore: any;
	let schemaStore: any;

	const mountComponent = (): VueWrapper => (
		mount( SubjectCreatorDialog, {
			global: {
				plugins: [ pinia ],
				stubs: {
					SubjectCreator: SubjectCreatorStub,
					SubjectEditorDialog: SubjectEditorDialogStub,
					CdxButton: true,
					CdxDialog: CdxDialogStub,
					teleport: true,
				},
				mocks: {
					$i18n: createI18nMock(),
				},
			},
		} )
	);

	beforeEach( () => {
		setupMwMock( {
			functions: [ 'msg', 'notify', 'config' ],
			config: {
				wgArticleId: 123,
			},
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		subjectStore = useSubjectStore();
		subjectStore.createMainSubject = vi.fn().mockResolvedValue( undefined );

		schemaStore = useSchemaStore();
		schemaStore.saveSchema = vi.fn().mockResolvedValue( undefined );

		Object.defineProperty( window, 'location', {
			writable: true,
			value: { reload: vi.fn() },
		} );
	} );

	const createSubject = async ( wrapper: VueWrapper, subject = newSubject() ): Promise<Subject> => {
		await wrapper.find( '.ext-neowiki-subject-creator-trigger' ).trigger( 'click' );
		await wrapper.findComponent( SubjectCreator ).vm.$emit( 'draft', subject );
		return subject as Subject;
	};

	it( 'opens dialog when button is clicked', async () => {
		const wrapper = mountComponent();
		const button = wrapper.find( '.ext-neowiki-subject-creator-trigger' );
		expect( button.exists() ).toBe( true );

		await button.trigger( 'click' );

		const dialog = wrapper.findComponent( CdxDialog );
		expect( dialog.props( 'open' ) ).toBe( true );
	} );

	it( 'switches to editor dialog when subject is created', async () => {
		const wrapper = mountComponent();
		const mockSubject = await createSubject( wrapper );

		const dialog = wrapper.findComponent( CdxDialog );
		const editor = wrapper.findComponent( SubjectEditorDialog );

		expect( dialog.props( 'open' ) ).toBe( false );
		expect( editor.exists() ).toBe( true );
		expect( editor.props( 'open' ) ).toBe( true );
		expect( editor.props( 'subject' ) ).toBe( mockSubject );
	} );

	it( 'saves subject and reloads page', async () => {
		const wrapper = mountComponent();
		const mockSubject = newSubject( {
			label: 'Test Label',
			schemaId: 'TestSchema',
		} );

		await createSubject( wrapper, mockSubject );

		const editor = wrapper.findComponent( SubjectEditorDialog );
		await editor.props( 'onSave' )( mockSubject, 'summary' );

		expect( subjectStore.createMainSubject ).toHaveBeenCalledWith(
			123,
			'Test Label',
			'TestSchema',
			mockSubject.getStatements(),
		);
		expect( window.location.reload ).toHaveBeenCalled();
	} );

	it( 'saves schema', async () => {
		const wrapper = mountComponent();
		await createSubject( wrapper );

		const mockSchema = newSchema( { title: 'TestSchema' } );
		const editor = wrapper.findComponent( SubjectEditorDialog );
		await editor.props( 'onSaveSchema' )( mockSchema, 'comment' );

		expect( schemaStore.saveSchema ).toHaveBeenCalledWith( mockSchema, 'comment' );
	} );
} );
