import { VueWrapper } from '@vue/test-utils';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import SubjectEditor from '@/components/Editor/SubjectEditor.vue';
import DeleteDialog from '@/components/Editor/DeleteDialog.vue';
import { Subject } from '@neo/domain/Subject';
import { SubjectId } from '@neo/domain/SubjectId';
import { StatementList } from '@neo/domain/StatementList';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { Schema, SchemaName } from '@neo/domain/Schema';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { createPropertyDefinitionFromJson } from '@neo/domain/PropertyDefinition';
import { createPinia, setActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore';
import { createTestWrapper } from '../../VueTestHelpers.ts';

vi.mock( '@/stores/SubjectStore', () => ( {
	useSubjectStore: () => ( {
		deleteSubject: vi.fn().mockImplementation( () => Promise.resolve() )
	} )
} ) );

describe( 'SubjectEditor - Delete Subject', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore;

	const mockSchema = new Schema(
		'TestSchema' as SchemaName,
		'A test schema',
		new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'name', { type: 'string', format: 'text' } )
		] )
	);

	const mockSubject = new Subject(
		new SubjectId( 's1demo1aaaaaaa1' ),
		'Test Subject',
		'TestSchema' as SchemaName,
		new StatementList( [] ),
		new PageIdentifiers( 1, 'Test_Subject' )
	);

	const mountComponent = async ( subject?: Subject ): Promise<VueWrapper> => {
		const wrapper = createTestWrapper( SubjectEditor, {
			subject: subject,
			canEditSchema: false,
			selectedSchema: 'TestSchema'
		} );

		await wrapper.vm.openDialog();

		return wrapper;
	};

	beforeAll( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str
			} ) ),
			config: {
				get: vi.fn().mockReturnValue( 1 )
			}
		} );
		pinia = createPinia();
		setActivePinia( pinia );
		schemaStore = useSchemaStore();
		schemaStore.setSchema( 'TestSchema', mockSchema );
	} );

	it( 'shows delete button when editing existing subject', async () => {
		const wrapper = await mountComponent( mockSubject );

		const deleteButton = wrapper.findComponent( '[test-id="delete-subject-button"]' );
		expect( deleteButton.exists() ).toBe( true );
	} );

	it( 'shows back button when creating new subject', async () => {
		const wrapper = await mountComponent();

		const deleteButton = wrapper.findComponent( '[test-id="delete-subject-button"]' );
		expect( deleteButton.exists() ).toBe( false );
	} );

	it( 'shows confirmation dialog when delete button is clicked', async () => {
		const wrapper = await mountComponent( mockSubject );

		const deleteButton = wrapper.findComponent( '[test-id="delete-subject-button"]' );
		await deleteButton.trigger( 'click' );

		const confirmDialog = wrapper.findComponent( DeleteDialog );
		expect( confirmDialog.exists() ).toBe( true );
		expect( confirmDialog.props( 'isOpen' ) ).toBe( true );
	} );

	it( 'emits save event when deletion is confirmed', async () => {
		const wrapper = await mountComponent( mockSubject );

		const deleteButton = wrapper.findComponent( '[test-id="delete-subject-button"]' );
		await deleteButton.trigger( 'click' );

		const confirmDialog = wrapper.findComponent( DeleteDialog );
		await confirmDialog.vm.$emit( 'delete' );

		expect( wrapper.emitted( 'save' ) ).toBeTruthy();
		expect( wrapper.emitted( 'save' )![ 0 ] ).toEqual( [ null ] );
	} );
} );
