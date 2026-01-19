import { mount } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import { Relation, RelationValue } from '@/domain/Value.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newRelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';

vi.mock( '@/stores/SubjectStore.ts' );

function createSubject( id: string, label: string, pageName: string ): SubjectWithContext {
	return new SubjectWithContext(
		new SubjectId( id ),
		label,
		'' as any,
		{} as any,
		new PageIdentifiers( 42, pageName ),
	);
}

async function createWrapper( ...relations: Relation[] ): Promise<ReturnType<typeof mount>> {
	const wrapper = createWrapperWithValue( new RelationValue( relations ) );
	return wrapper;
}

function createWrapperWithValue( value: RelationValue ): ReturnType<typeof mount> {
	return mount( RelationDisplay, {
		props: {
			value: value,
			property: newRelationProperty(),
		},
	} );
}

describe( 'RelationDisplay.vue', () => {
	let mockGetSubject: ReturnType<typeof vi.fn>;
	let mockGetUrl: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		mockGetSubject = vi.fn();
		vi.mocked( useSubjectStore ).mockReturnValue( {
			$id: 'subject',
			getSubject: mockGetSubject,
		} as any );

		mockGetUrl = vi.fn();
		vi.stubGlobal( 'mw', {
			util: {
				getUrl: mockGetUrl,
			},
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str,
			} ) ),
		} );
	} );

	it( 'renders a single relation correctly as a link', async () => {
		mockGetSubject.mockReturnValue( createSubject( 's1111111111111A', 'Test Subject 1', 'Page_Name_1' ) );
		mockGetUrl.mockReturnValue( '/wiki/Page_Name_1' );

		const wrapper = await createWrapper( new Relation( 'not-important', new SubjectId( 's1111111111111A' ) ) );

		const element = wrapper.find( 'a' );
		expect( element.exists() ).toBe( true );
		expect( element.text() ).toBe( 'Test Subject 1' );
		expect( element.attributes( 'href' ) ).toBe( '/wiki/Page_Name_1' );
		expect( element.classes().includes( 'error' ) ).toBe( false );

		expect( mockGetSubject ).toHaveBeenCalledWith( new SubjectId( 's1111111111111A' ) );
		expect( mockGetUrl ).toHaveBeenCalledWith( 'Page_Name_1' );
	} );

	it( 'renders a span with error info when subject is not found', async () => {
		mockGetSubject.mockReturnValue( undefined );
		mockGetUrl.mockReturnValue( undefined );

		const wrapper = await createWrapper( new Relation( 'not-important', new SubjectId( 's1111111111111B' ) ) );

		const element = wrapper.find( 'span' );
		expect( element.exists() ).toBe( true );
		expect( element.text() ).toBe( 's1111111111111B' );
		expect( element.attributes( 'title' ) ).toContain( 'Subject not found: s1111111111111B' );
		expect( element.classes() ).toContain( 'error' );

		expect( mockGetSubject ).toHaveBeenCalledWith( new SubjectId( 's1111111111111B' ) );
	} );

	it( 'renders a span with error info when fetching subject fails', async () => {
		mockGetSubject.mockImplementation( () => {
			const err = new Error( 'Network Connection Error' );
			err.name = 'NetworkError';
			throw err;
		} );
		mockGetUrl.mockReturnValue( undefined );

		const wrapper = await createWrapper( new Relation( 'not-important', new SubjectId( 's1111111111111C' ) ) );

		const element = wrapper.find( 'span' );
		expect( element.exists() ).toBe( true );
		expect( element.text() ).toBe( 's1111111111111C' );
		expect( element.attributes( 'title' ) ).toContain( 'NetworkError: Network Connection Error' );
		expect( element.classes() ).toContain( 'error' );

		expect( mockGetSubject ).toHaveBeenCalledWith( new SubjectId( 's1111111111111C' ) );
	} );

	it( 'renders a span (not link) if mw.util.getUrl returns undefined for a subject', async () => {
		mockGetSubject.mockReturnValue( createSubject( 's1111111111111D', 'No Valid Url Subject', 'No_Url_Page' ) );
		mockGetUrl.mockReturnValue( undefined );

		const wrapper = await createWrapper( new Relation( 'not-important', new SubjectId( 's1111111111111D' ) ) );

		const element = wrapper.find( 'span' );
		expect( element.exists() ).toBe( true );
		expect( element.text() ).toBe( 'No Valid Url Subject' );
		expect( element.attributes( 'title' ) ).toContain( '' );
		expect( element.classes().includes( 'error' ) ).toBe( false );

		expect( mockGetSubject ).toHaveBeenCalledWith( new SubjectId( 's1111111111111D' ) );
		expect( mockGetUrl ).toHaveBeenCalledWith( 'No_Url_Page' );
	} );

	it( 'renders multiple relations correctly as links', async () => {
		const TEST_DATA = [
			{
				ID: 's2222222222222A',
				LABEL: 'Subject Alpha',
				PAGE_NAME: 'Page_Alpha',
				URL: '/wiki/Page_Alpha',
			},
			{
				ID: 's2222222222222B',
				LABEL: 'Subject Beta',
				PAGE_NAME: 'Page_Beta',
				URL: '/wiki/Page_Beta',
			},
		];

		mockGetSubject
			.mockReturnValueOnce(
				createSubject( TEST_DATA[ 0 ].ID, TEST_DATA[ 0 ].LABEL, TEST_DATA[ 0 ].PAGE_NAME ),
			)
			.mockReturnValueOnce(
				createSubject( TEST_DATA[ 1 ].ID, TEST_DATA[ 1 ].LABEL, TEST_DATA[ 1 ].PAGE_NAME ),
			);
		mockGetUrl
			.mockReturnValueOnce( TEST_DATA[ 0 ].URL )
			.mockReturnValueOnce( TEST_DATA[ 1 ].URL );

		const wrapper = await createWrapper(
			new Relation( 'not-important', new SubjectId( TEST_DATA[ 0 ].ID ) ),
			new Relation( 'not-important', new SubjectId( TEST_DATA[ 1 ].ID ) ),
		);

		const links = wrapper.findAll( 'a' );
		expect( links ).toHaveLength( 2 );
		expect( links[ 0 ].attributes( 'href' ) ).toBe( TEST_DATA[ 0 ].URL );
		expect( links[ 0 ].text() ).toBe( TEST_DATA[ 0 ].LABEL );
		expect( links[ 1 ].attributes( 'href' ) ).toBe( TEST_DATA[ 1 ].URL );
		expect( links[ 1 ].text() ).toBe( TEST_DATA[ 1 ].LABEL );
	} );

	it( 'renders nothing when there are no relations', async () => {
		const wrapper = await createWrapper();

		expect( wrapper.find( 'a' ).exists() ).toBe( false );
		expect( wrapper.find( 'span' ).exists() ).toBe( false );
	} );

	describe( 'when rendering a mix of relation types', () => {
		let wrapper: ReturnType<typeof mount>;
		const TEST_DATA = {
			LINKABLE: {
				ID: 's3333333333333A',
				LABEL: 'Linkable Subject',
				PAGE_NAME: 'Link_Page',
				URL: '/wiki/Link_Page',
			},
			ERROR_FETCH: {
				ID: 's3333333333333B',
				MESSAGE: 'Fetch s3333333333333B Failed',
			},
			NOT_FOUND: {
				ID: 's3333333333333C',
				MESSAGE: 'Subject not found: s3333333333333C',
			},
			NO_URL: {
				ID: 's3333333333333D',
				LABEL: 'NoUrl Mixed Subject',
				PAGE_NAME: 'NoUrl_Mixed_Page',
			},
		};

		beforeEach( async () => {
			const subjectLink = createSubject( TEST_DATA.LINKABLE.ID, TEST_DATA.LINKABLE.LABEL, TEST_DATA.LINKABLE.PAGE_NAME );
			const noUrlSubject = createSubject( TEST_DATA.NO_URL.ID, TEST_DATA.NO_URL.LABEL, TEST_DATA.NO_URL.PAGE_NAME );

			mockGetSubject.mockImplementation( ( target: SubjectId ) => {
				if ( target.text === TEST_DATA.LINKABLE.ID ) {
					return subjectLink;
				}
				if ( target.text === TEST_DATA.ERROR_FETCH.ID ) {
					const err = new Error( TEST_DATA.ERROR_FETCH.MESSAGE );
					err.name = 'Error';
					throw err;
				}
				if ( target.text === TEST_DATA.NOT_FOUND.ID ) {
					return undefined;
				}
				if ( target.text === TEST_DATA.NO_URL.ID ) {
					return noUrlSubject;
				}
				return undefined;
			} );

			mockGetUrl.mockImplementation( ( pageName: string ) => {
				if ( pageName === TEST_DATA.LINKABLE.PAGE_NAME ) {
					return TEST_DATA.LINKABLE.URL;
				}
				if ( pageName === TEST_DATA.NO_URL.PAGE_NAME ) {
					return undefined;
				}
				return '/wiki/' + pageName;
			} );

			wrapper = await createWrapper(
				new Relation( 'not-important', new SubjectId( TEST_DATA.LINKABLE.ID ) ),
				new Relation( 'not-important', new SubjectId( TEST_DATA.ERROR_FETCH.ID ) ),
				new Relation( 'not-important', new SubjectId( TEST_DATA.NOT_FOUND.ID ) ),
				new Relation( 'not-important', new SubjectId( TEST_DATA.NO_URL.ID ) ),
			);
		} );

		it( 'renders the linkable subject correctly', () => {
			const children = wrapper.findAll( 'div > div > :is(a, span)' );
			const linkChild = children[ 0 ];
			expect( linkChild.element.tagName ).toBe( 'A' );
			expect( linkChild.attributes( 'href' ) ).toBe( TEST_DATA.LINKABLE.URL );
			expect( linkChild.text() ).toBe( TEST_DATA.LINKABLE.LABEL );
		} );

		it( 'renders the subject that causes a fetch error correctly', () => {
			const children = wrapper.findAll( 'div > div > :is(a, span)' );
			const errorChild = children[ 1 ];
			expect( errorChild.element.tagName ).toBe( 'SPAN' );
			expect( errorChild.text() ).toBe( TEST_DATA.ERROR_FETCH.ID );
			expect( errorChild.classes() ).toContain( 'error' );
			expect( errorChild.attributes( 'title' ) ).toContain( 'Error: ' + TEST_DATA.ERROR_FETCH.MESSAGE );
		} );

		it( 'renders the not-found subject correctly', () => {
			const children = wrapper.findAll( 'div > div > :is(a, span)' );
			const notFoundChild = children[ 2 ];
			expect( notFoundChild.element.tagName ).toBe( 'SPAN' );
			expect( notFoundChild.text() ).toBe( TEST_DATA.NOT_FOUND.ID );
			expect( notFoundChild.classes() ).toContain( 'error' );
			expect( notFoundChild.attributes( 'title' ) ).toContain( TEST_DATA.NOT_FOUND.MESSAGE );
		} );

		it( 'renders the subject with no URL correctly', () => {
			const children = wrapper.findAll( 'div > div > :is(a, span)' );
			const noUrlChild = children[ 3 ];
			expect( noUrlChild.element.tagName ).toBe( 'SPAN' );
			expect( noUrlChild.text() ).toBe( TEST_DATA.NO_URL.LABEL );
			expect( noUrlChild.classes().includes( 'error' ) ).toBe( false );
			expect( noUrlChild.attributes( 'title' ) ).toBe( '' );
		} );

		it( 'renders all four items', () => {
			const children = wrapper.findAll( 'div > div > :is(a, span)' );
			expect( children ).toHaveLength( 4 );
		} );
	} );

	it( 'does not render items if value prop is not a RelationValue', async () => {
		const wrapper = mount( RelationDisplay, {
			props: {
				value: { notARelationValue: true } as any,
				property: newRelationProperty(),
			},
		} );

		await wrapper.vm.$nextTick();

		expect( wrapper.find( 'div > div > :is(a, span)' ).exists() ).toBe( false );
	} );
} );
