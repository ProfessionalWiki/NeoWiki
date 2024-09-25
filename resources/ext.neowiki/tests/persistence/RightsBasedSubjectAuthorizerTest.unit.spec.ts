import { describe, expect, it, vi } from 'vitest';
import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer';
import { SubjectId } from '@neo/domain/SubjectId';
import { TestUserObjectBasedRightsFetcher } from './UserObjectBasedRightsFetcher.unit.spec';

describe( 'Rights Based Subject Authorizer', async () => {

	const GUID = '00000000-7777-0000-0000-000000000001';

	it( 'can edit subject with right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'edit', 'bar', 'writeapi', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canEditSubject( new SubjectId( GUID ) ) ).toBe( true );
	} );

	it( 'cannot edit subject without edit right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'writeapi', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canEditSubject( new SubjectId( GUID ) ) ).toBe( false );
	} );

	it( 'cannot edit subject without writeapi right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'edit', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canEditSubject( new SubjectId( GUID ) ) ).toBe( false );
	} );

	it( 'can delete subject with right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'delete', 'bar', 'writeapi', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canDeleteSubject( new SubjectId( GUID ) ) ).toBe( true );
	} );

	it( 'cannot delete subject without delete right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'writeapi', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canDeleteSubject( new SubjectId( GUID ) ) ).toBe( false );
	} );

	it( 'cannot delete subject without writeapi right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'delete', 'bar', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canDeleteSubject( new SubjectId( GUID ) ) ).toBe( false );
	} );

	it( 'can create child subject with rights', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'writeapi', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( true );
	} );

	it( 'cannot create child subject without createpage right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'writeapi', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( false );
	} );

	it( 'cannot create child subject without writeapi right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( false );
	} );

	it( 'cannot create child subject without edit right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'writeapi', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( false );
	} );

	it( 'can create main subject with rights', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'writeapi', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateMainSubject() ).toBe( true );
	} );

	it( 'cannot create main subject without createpage right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'bar', 'writeapi', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateMainSubject() ).toBe( false );
	} );

	it( 'cannot create main subject without writeapi right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'baz', 'edit' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateMainSubject() ).toBe( false );
	} );

	it( 'cannot create main subject without edit right', async () => {
		const rightsFetcher = new TestUserObjectBasedRightsFetcher( [ 'foo', 'createpage', 'bar', 'writeapi', 'baz' ] );
		const authorizer = new RightsBasedSubjectAuthorizer( rightsFetcher );

		expect( await authorizer.canCreateMainSubject() ).toBe( false );
	} );

} );
