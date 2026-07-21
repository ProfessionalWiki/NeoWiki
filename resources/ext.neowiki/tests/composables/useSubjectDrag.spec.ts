import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { ref, Ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { useSubjectDrag, SubjectDragHandlers } from '@/composables/useSubjectDrag';
import { subjectRowDomId } from '@/presentation/subjectRowDomId';

interface UseSortableCall {
	containerRef: Ref<HTMLElement | null>;
	options: any;
}

const useSortableCalls: UseSortableCall[] = [];

vi.mock( '@/composables/useSortable', () => ( {
	useSortable: ( containerRef: Ref<HTMLElement | null>, options: any ) => {
		useSortableCalls.push( { containerRef, options } );
	},
} ) );

enableAutoUnmount( afterEach );

beforeEach( () => {
	useSortableCalls.length = 0;
} );

function newHandlers( overrides: Partial<SubjectDragHandlers> = {} ): SubjectDragHandlers {
	return {
		onPromote: vi.fn(),
		onDemote: vi.fn(),
		onReorderChildren: vi.fn(),
		...overrides,
	};
}

function mountComposable(
	mainSlot: HTMLElement | null,
	childList: HTMLElement | null,
	handlers: SubjectDragHandlers,
): void {
	const mainSlotRef = ref<HTMLElement | null>( mainSlot );
	const childListRef = ref<HTMLElement | null>( childList );
	mount( {
		setup() {
			useSubjectDrag( mainSlotRef, childListRef, handlers );
			return () => null;
		},
	} );
}

function findCallByContainer( container: HTMLElement | null ): UseSortableCall {
	const call = useSortableCalls.find( ( c ) => c.containerRef.value === container );
	if ( !call ) {
		throw new Error( 'No useSortable call matched the given container' );
	}
	return call;
}

const VALID_ID = 's12345abcdefghj';

describe( 'useSubjectDrag', () => {

	it( 'calls onPromote with subject id and source slot when a child is dropped on the main slot', () => {
		const mainSlot = document.createElement( 'div' );
		const childList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, childList, handlers );

		const dragged = document.createElement( 'li' );
		dragged.id = subjectRowDomId( VALID_ID );

		findCallByContainer( mainSlot ).options.onDropIn( dragged, 2, 0 );

		expect( handlers.onPromote ).toHaveBeenCalledTimes( 1 );
		expect( ( handlers.onPromote as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 0 ].text ).toBe( VALID_ID );
		expect( ( handlers.onPromote as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 1 ] ).toBe( 2 );
		expect( handlers.onDemote ).not.toHaveBeenCalled();
		expect( handlers.onReorderChildren ).not.toHaveBeenCalled();
	} );

	it( 'calls onDemote with the target slot when the main row is dropped into the child list', () => {
		const mainSlot = document.createElement( 'div' );
		const childList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, childList, handlers );

		const dragged = document.createElement( 'div' );
		dragged.id = subjectRowDomId( VALID_ID );

		findCallByContainer( childList ).options.onDropIn( dragged, 0, 1 );

		expect( handlers.onDemote ).toHaveBeenCalledTimes( 1 );
		expect( ( handlers.onDemote as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 0 ] ).toBe( 1 );
		expect( handlers.onPromote ).not.toHaveBeenCalled();
	} );

	it( 'calls onReorderChildren when a child is moved within the child list', () => {
		const mainSlot = document.createElement( 'div' );
		const childList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, childList, handlers );

		findCallByContainer( childList ).options.onReorder( 0, 2 );

		expect( handlers.onReorderChildren ).toHaveBeenCalledWith( 0, 2 );
		expect( handlers.onPromote ).not.toHaveBeenCalled();
		expect( handlers.onDemote ).not.toHaveBeenCalled();
	} );

	it( 'does not call onPromote when the dropped element has no recognized subject id', () => {
		const mainSlot = document.createElement( 'div' );
		const childList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, childList, handlers );

		const dragged = document.createElement( 'li' );
		dragged.id = 'something-else';

		findCallByContainer( mainSlot ).options.onDropIn( dragged, 0, 0 );

		expect( handlers.onPromote ).not.toHaveBeenCalled();
	} );

	it( 'does not call onPromote when the prefix matches but the body is not a valid subject id', () => {
		const mainSlot = document.createElement( 'div' );
		const childList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, childList, handlers );

		const dragged = document.createElement( 'li' );
		dragged.id = subjectRowDomId( 'not-a-valid-id' );

		expect( () => findCallByContainer( mainSlot ).options.onDropIn( dragged, 0, 0 ) ).not.toThrow();
		expect( handlers.onPromote ).not.toHaveBeenCalled();
	} );

} );
