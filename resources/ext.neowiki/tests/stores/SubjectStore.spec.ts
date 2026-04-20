import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore';

describe( 'SubjectStore — subjectCreatorOpen', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'starts closed by default', () => {
		const store = useSubjectStore();
		expect( store.subjectCreatorOpen ).toBe( false );
	} );

	it( 'opens the creator when openSubjectCreator is called', () => {
		const store = useSubjectStore();
		store.openSubjectCreator();
		expect( store.subjectCreatorOpen ).toBe( true );
	} );

	it( 'closes the creator when closeSubjectCreator is called', () => {
		const store = useSubjectStore();
		store.openSubjectCreator();
		store.closeSubjectCreator();
		expect( store.subjectCreatorOpen ).toBe( false );
	} );
} );

describe( 'SubjectStore — pageSubjectsOpen', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'starts closed by default', () => {
		const store = useSubjectStore();
		expect( store.pageSubjectsOpen ).toBe( false );
	} );

	it( 'opens when openPageSubjects is called', () => {
		const store = useSubjectStore();
		store.openPageSubjects();
		expect( store.pageSubjectsOpen ).toBe( true );
	} );

	it( 'closes when closePageSubjects is called', () => {
		const store = useSubjectStore();
		store.openPageSubjects();
		store.closePageSubjects();
		expect( store.pageSubjectsOpen ).toBe( false );
	} );
} );
