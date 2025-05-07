import { mount } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import { Relation, RelationValue } from '@neo/domain/Value.ts';
import { SubjectId } from '@neo/domain/SubjectId.ts';
import { newRelationProperty } from '@neo/domain/propertyTypes/Relation.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { SubjectWithContext } from '@neo/domain/SubjectWithContext.ts';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers.ts';

vi.mock( '@/stores/SubjectStore.ts' );

function createSubject( id: string, label: string, pageName: string ): SubjectWithContext {
	return new SubjectWithContext(
		new SubjectId( id ),
		label,
		'' as any,
		{} as any,
		new PageIdentifiers( 42, pageName )
	);
}

async function createWrapper( ...relations: Relation[] ): Promise<ReturnType<typeof mount>> {
	const wrapper = createWrapperWithValue( new RelationValue( relations ) );
	await wrapper.vm.$nextTick();
	await Promise.resolve();
	await wrapper.vm.$nextTick();
	return wrapper;
}

function createWrapperWithValue( value: RelationValue ): ReturnType<typeof mount> {
	return mount( RelationDisplay, {
		props: {
			value: value,
			property: newRelationProperty()
		}
	} );
}

describe( 'RelationDisplay.vue', () => {
	let mockGetOrFetchSubject: ReturnType<typeof vi.fn>;
	let mockGetUrl: ReturnType<typeof vi.fn>;

	beforeEach( () => {
		mockGetOrFetchSubject = vi.fn();
		vi.mocked( useSubjectStore ).mockReturnValue( {
			$id: 'subject',
			getOrFetchSubject: mockGetOrFetchSubject
		} as any );

		mockGetUrl = vi.fn();
		vi.stubGlobal( 'mw', {
			util: {
				getUrl: mockGetUrl
			},
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it.each( [
		{
			description: 'renders a single relation correctly as a link',
			targetId: 's1111111111111A',
			pageName: 'Page_Name_1',
			subjectLabel: 'Test Subject 1',
			mockSetup: {
				fetchResult: () => createSubject( 's1111111111111A', 'Test Subject 1', 'Page_Name_1' ),
				shouldReject: false
			},
			mockUrlResult: '/wiki/Page_Name_1',
			expectedTagName: 'a',
			expectedText: 'Test Subject 1',
			expectedHref: '/wiki/Page_Name_1',
			expectedTitle: undefined,
			expectErrorClass: false
		},
		{
			description: 'renders a span with error info when subject is not found',
			targetId: 's1111111111111B',
			pageName: 'NonExistent_Page',
			subjectLabel: null,
			mockSetup: {
				fetchResult: () => undefined,
				shouldReject: false
			},
			mockUrlResult: undefined,
			expectedTagName: 'span',
			expectedText: 's1111111111111B',
			expectedHref: undefined,
			expectedTitle: 'Subject not found: s1111111111111B',
			expectErrorClass: true
		},
		{
			description: 'renders a span with error info when fetching subject fails',
			targetId: 's1111111111111C',
			pageName: 'Error_Fetch_Page',
			subjectLabel: null,
			mockSetup: {
				fetchResult: () => {
					const err = new Error( 'Network Connection Error' );
					err.name = 'NetworkError';
					throw err;
				},
				shouldReject: true
			},
			mockUrlResult: undefined,
			expectedTagName: 'span',
			expectedText: 's1111111111111C',
			expectedHref: undefined,
			expectedTitle: 'NetworkError: Network Connection Error',
			expectErrorClass: true
		},
		{
			description: 'renders a span (not link) if mw.util.getUrl returns undefined for a subject',
			targetId: 's1111111111111D',
			pageName: 'No_Url_Page',
			subjectLabel: 'No Valid Url Subject',
			mockSetup: {
				fetchResult: () => createSubject( 's1111111111111D', 'No Valid Url Subject', 'No_Url_Page' ),
				shouldReject: false
			},
			mockUrlResult: undefined,
			expectedTagName: 'span',
			expectedText: 'No Valid Url Subject',
			expectedHref: undefined,
			expectedTitle: '',
			expectErrorClass: false
		}
	] )( '$description', async ( testCaseData: any ) => {
		const targetId = testCaseData.targetId;
		const pageName = testCaseData.pageName;
		const { fetchResult, shouldReject } = testCaseData.mockSetup;
		const mockUrlResult = testCaseData.mockUrlResult;
		const expectedTagName = testCaseData.expectedTagName;
		const expectedText = testCaseData.expectedText;
		const expectedHref = testCaseData.expectedHref;
		const expectedTitle = testCaseData.expectedTitle;
		const expectErrorClass = testCaseData.expectErrorClass;

		const subjectId = new SubjectId( targetId );

		if ( shouldReject ) {
			mockGetOrFetchSubject.mockImplementation( fetchResult as () => never );
		} else {
			mockGetOrFetchSubject.mockResolvedValue( ( fetchResult as () => SubjectWithContext | undefined )() );
		}

		mockGetUrl.mockReturnValue( mockUrlResult );

		const wrapper = await createWrapper( new Relation( 'not-important', new SubjectId( targetId ) ) );

		const element = wrapper.find( expectedTagName );
		expect( element.exists() ).toBe( true );
		expect( element.text() ).toBe( expectedText );

		if ( expectedHref !== undefined ) {
			expect( element.attributes( 'href' ) ).toBe( expectedHref );
		}

		if ( expectedTitle !== undefined ) {
			expect( element.attributes( 'title' ) ).toContain( expectedTitle );
		}

		if ( expectErrorClass ) {
			expect( element.classes() ).toContain( 'error' );
		} else {
			expect( element.classes().includes( 'error' ) ).toBe( false );
		}

		expect( mockGetOrFetchSubject ).toHaveBeenCalledWith( subjectId );
		if ( mockUrlResult !== undefined && expectedTagName === 'a' ) {
			expect( mockGetUrl ).toHaveBeenCalledWith( pageName );
		}
	} );

	it( 'renders multiple relations correctly as links', async () => {
		const TEST_DATA = [
			{
				ID: 's2222222222222A',
				LABEL: 'Subject Alpha',
				PAGE_NAME: 'Page_Alpha',
				URL: '/wiki/Page_Alpha'
			},
			{
				ID: 's2222222222222B',
				LABEL: 'Subject Beta',
				PAGE_NAME: 'Page_Beta',
				URL: '/wiki/Page_Beta'
			}
		];

		mockGetOrFetchSubject
			.mockResolvedValueOnce(
				createSubject( TEST_DATA[ 0 ].ID, TEST_DATA[ 0 ].LABEL, TEST_DATA[ 0 ].PAGE_NAME )
			)
			.mockResolvedValueOnce(
				createSubject( TEST_DATA[ 1 ].ID, TEST_DATA[ 1 ].LABEL, TEST_DATA[ 1 ].PAGE_NAME )
			);
		mockGetUrl
			.mockReturnValueOnce( TEST_DATA[ 0 ].URL )
			.mockReturnValueOnce( TEST_DATA[ 1 ].URL );

		const wrapper = await createWrapper(
			new Relation( 'not-important', new SubjectId( TEST_DATA[ 0 ].ID ) ),
			new Relation( 'not-important', new SubjectId( TEST_DATA[ 1 ].ID ) )
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

	it( 'handles a mix of successful fetches, not found, and errors correctly', async () => {
		const TEST_DATA = {
			LINKABLE: {
				ID: 's3333333333333A',
				LABEL: 'Linkable Subject',
				PAGE_NAME: 'Link_Page',
				URL: '/wiki/Link_Page'
			},
			ERROR_FETCH: {
				ID: 's3333333333333B',
				PAGE_NAME: 'Error_Mixed_Page',
				MESSAGE: 'Fetch s3333333333333B Failed'
			},
			NOT_FOUND: {
				ID: 's3333333333333C',
				PAGE_NAME: 'NotFound_Mixed_Page',
				MESSAGE: 'Subject not found: s3333333333333C'
			},
			NO_URL: {
				ID: 's3333333333333D',
				LABEL: 'NoUrl Mixed Subject',
				PAGE_NAME: 'NoUrl_Mixed_Page'
			}
		};

		const subjectLink = createSubject( TEST_DATA.LINKABLE.ID, TEST_DATA.LINKABLE.LABEL, TEST_DATA.LINKABLE.PAGE_NAME );
		const noUrlSubject = createSubject( TEST_DATA.NO_URL.ID, TEST_DATA.NO_URL.LABEL, TEST_DATA.NO_URL.PAGE_NAME );

		mockGetOrFetchSubject.mockImplementation( async ( target: SubjectId ) => {
			if ( target.text === TEST_DATA.LINKABLE.ID ) {
				return subjectLink;
			}
			if ( target.text === TEST_DATA.ERROR_FETCH.ID ) {
				throw new Error( TEST_DATA.ERROR_FETCH.MESSAGE );
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

		const wrapper = await createWrapper(
			new Relation( 'not-important', new SubjectId( TEST_DATA.LINKABLE.ID ) ),
			new Relation( 'not-important', new SubjectId( TEST_DATA.ERROR_FETCH.ID ) ),
			new Relation( 'not-important', new SubjectId( TEST_DATA.NOT_FOUND.ID ) ),
			new Relation( 'not-important', new SubjectId( TEST_DATA.NO_URL.ID ) )
		);

		const children = wrapper.findAll( 'div > div > :is(a, span)' );
		expect( children ).toHaveLength( 4 );

		const linkChild = children[ 0 ];
		expect( linkChild.element.tagName ).toBe( 'A' );
		expect( linkChild.attributes( 'href' ) ).toBe( TEST_DATA.LINKABLE.URL );
		expect( linkChild.text() ).toBe( TEST_DATA.LINKABLE.LABEL );

		const errorChild = children[ 1 ];
		expect( errorChild.element.tagName ).toBe( 'SPAN' );
		expect( errorChild.text() ).toBe( TEST_DATA.ERROR_FETCH.ID );
		expect( errorChild.classes() ).toContain( 'error' );
		expect( errorChild.attributes( 'title' ) ).toContain( 'Error: ' + TEST_DATA.ERROR_FETCH.MESSAGE );

		const notFoundChild = children[ 2 ];
		expect( notFoundChild.element.tagName ).toBe( 'SPAN' );
		expect( notFoundChild.text() ).toBe( TEST_DATA.NOT_FOUND.ID );
		expect( notFoundChild.classes() ).toContain( 'error' );
		expect( notFoundChild.attributes( 'title' ) ).toContain( TEST_DATA.NOT_FOUND.MESSAGE );

		const noUrlChild = children[ 3 ];
		expect( noUrlChild.element.tagName ).toBe( 'SPAN' );
		expect( noUrlChild.text() ).toBe( TEST_DATA.NO_URL.LABEL );
		expect( noUrlChild.classes().includes( 'error' ) ).toBe( false );
		expect( noUrlChild.attributes( 'title' ) ).toBe( '' );
	} );

	it( 'does not render items if value prop is not a RelationValue', async () => {
		const wrapper = mount( RelationDisplay, {
			props: {
				value: { notARelationValue: true } as any,
				property: newRelationProperty()
			}
		} );

		await wrapper.vm.$nextTick();

		expect( wrapper.find( 'div > div > :is(a, span)' ).exists() ).toBe( false );
	} );
} );
