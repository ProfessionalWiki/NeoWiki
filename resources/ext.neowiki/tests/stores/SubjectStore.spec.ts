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
