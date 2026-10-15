import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SubjectDeleteDialog from '@/components/SubjectsManager/SubjectDeleteDialog.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { Statement } from '@/domain/Statement.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { newRelation, newStringValue, RelationValue } from '@/domain/Value.ts';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation.ts';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { HostSubject } from '@/domain/HostSubject.ts';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { CdxDialogStub, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const BIRTH_ID = 's1birthAAAAAAA1';
const RECORD_ID = 's1recordAAAAAA1';

const PICASSO: HostSubject = {
	id: 's1picassoAAAAA1',
	schemaName: 'Person',
	displayName: 'Pablo Picasso',
	displayNameIsGenerated: false,
	hostSubject: null,
};

function birth( ...statements: Statement[] ): Subject {
	return newSubject( {
		id: BIRTH_ID,
		label: null,
		displayNameIsGenerated: true,
		schemaName: 'Birth',
		hostSubject: PICASSO,
		statements: new StatementList( [
			new Statement( new PropertyName( 'Date' ), TextType.typeName, newStringValue( '1881' ) ),
			...statements,
		] ),
	} );
}

describe( 'SubjectDeleteDialog', () => {

	beforeEach( () => {
		setupMwMock();
		setActivePinia( createPinia() );
		useSchemaStore().setSchema( 'Birth', newSchema( {
			title: 'Birth',
			subjectsAreDependent: true,
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Date' } ),
				newRelationProperty( { name: 'Record', targetSchemas: [ 'Record' ] } ),
			] ),
		} ) );
	} );

	function mountDialog( subject: Subject ): VueWrapper {
		return mount( SubjectDeleteDialog, {
			props: { open: true, subject },
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: { CdxDialog: CdxDialogStub, SummaryAction: true },
			},
		} );
	}

	it( 'asks about a Subject by its name', () => {
		const wrapper = mountDialog( newSubject( { label: 'Rijksmuseum' } ) );

		expect( wrapper.find( 'strong' ).text() ).toBe( 'Rijksmuseum' );
	} );

	it( 'asks about a Dependent Subject by the name that tells it apart from others of its Host Subject', () => {
		const wrapper = mountDialog( birth() );

		expect( wrapper.find( 'strong' ).text() ).toBe( 'Birth of Pablo Picasso (Date: 1881)' );
	} );

	it( 'says that a Dependent Subject also goes from its Host Subject', () => {
		const wrapper = mountDialog( birth() );

		expect( wrapper.text() ).toContain( 'neowiki-managesubjects-delete-confirm-removed-from-hostPablo Picasso' );
	} );

	it( 'says that the Dependent Subjects inside a Dependent Subject go with it', () => {
		useSubjectStore().setSubject( newSubject( {
			id: RECORD_ID,
			label: null,
			displayNameIsGenerated: true,
			schemaName: 'Record',
			hostSubject: { id: BIRTH_ID, schemaName: 'Birth', displayName: 'Birth', displayNameIsGenerated: true, hostSubject: PICASSO },
		} ) );

		const wrapper = mountDialog( birth( new Statement(
			new PropertyName( 'Record' ),
			RelationType.typeName,
			new RelationValue( [ newRelation( undefined, RECORD_ID ) ] ),
		) ) );

		expect( wrapper.text() ).toContain( 'neowiki-managesubjects-delete-confirm-removed-from-host-with-dependentsPablo Picasso' );
	} );

	it( 'says nothing more of a standalone Subject', () => {
		const wrapper = mountDialog( newSubject( { label: 'Rijksmuseum' } ) );

		expect( wrapper.text() ).not.toContain( 'neowiki-managesubjects-delete-confirm-removed-from-host' );
	} );

} );
