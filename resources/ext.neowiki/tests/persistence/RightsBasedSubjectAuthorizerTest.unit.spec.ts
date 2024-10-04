import { describe, expect, it } from 'vitest';
import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer';
import { SubjectId } from '@neo/domain/SubjectId';
import { TestUserObjectBasedRightsFetcher } from './UserObjectBasedRightsFetcher.unit.spec';

describe( 'Rights Based Subject Authorizer', async () => {

	const SUBJECT_ID = new SubjectId( 's11111111111117' );

	it( 'can edit subject with right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'edit', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canEditSubject( SUBJECT_ID ) ).toBe( true );
	} );

	it( 'cannot edit subject without edit right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canEditSubject( SUBJECT_ID ) ).toBe( false );
	} );

	it( 'can delete subject with right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'delete', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canDeleteSubject( SUBJECT_ID ) ).toBe( true );
	} );

	it( 'cannot delete subject without delete right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canDeleteSubject( SUBJECT_ID ) ).toBe( false );
	} );

	it( 'can create child subject with rights', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( true );
	} );

	it( 'cannot create child subject without createpage right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( false );
	} );

	it( 'cannot create child subject without edit right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( false );
	} );

	it( 'can create main subject with rights', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateMainSubject() ).toBe( true );
	} );

	it( 'cannot create main subject without createpage right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateMainSubject() ).toBe( false );
	} );

	it( 'cannot create main subject without edit right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateMainSubject() ).toBe( false );
	} );

} );
