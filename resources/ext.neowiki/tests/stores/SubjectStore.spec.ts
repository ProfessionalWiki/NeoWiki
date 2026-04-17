import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSubjectStore } from '@/stores/SubjectStore';

describe( 'SubjectStore — subjectCreatorOpen', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'defaults subjectCreatorOpen to false', () => {
		const store = useSubjectStore();
		expect( store.subjectCreatorOpen ).toBe( false );
	} );

	it( 'openSubjectCreator sets the flag to true', () => {
		const store = useSubjectStore();
		store.openSubjectCreator();
		expect( store.subjectCreatorOpen ).toBe( true );
	} );

	it( 'closeSubjectCreator sets the flag to false', () => {
		const store = useSubjectStore();
		store.openSubjectCreator();
		store.closeSubjectCreator();
		expect( store.subjectCreatorOpen ).toBe( false );
	} );
} );
