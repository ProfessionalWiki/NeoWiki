import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { ref, Ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { useSubjectDrag, SubjectDragHandlers } from '@/composables/useSubjectDrag';
import { subjectRowDomId } from '@/presentation/subjectRowAnchor';

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
		onReorderOthers: vi.fn(),
		...overrides,
	};
}

function mountComposable(
	mainSlot: HTMLElement | null,
	otherList: HTMLElement | null,
	handlers: SubjectDragHandlers,
): void {
	const mainSlotRef = ref<HTMLElement | null>( mainSlot );
	const otherListRef = ref<HTMLElement | null>( otherList );
	mount( {
		setup() {
			useSubjectDrag( mainSlotRef, otherListRef, handlers );
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

	it( 'calls onPromote with subject id and source slot when a row from the other list is dropped on the main slot', () => {
		const mainSlot = document.createElement( 'div' );
		const otherList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, otherList, handlers );

		const dragged = document.createElement( 'li' );
		dragged.id = subjectRowDomId( VALID_ID );

		findCallByContainer( mainSlot ).options.onDropIn( dragged, 2, 0 );

		expect( handlers.onPromote ).toHaveBeenCalledTimes( 1 );
		expect( ( handlers.onPromote as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 0 ].text ).toBe( VALID_ID );
		expect( ( handlers.onPromote as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 1 ] ).toBe( 2 );
		expect( handlers.onDemote ).not.toHaveBeenCalled();
		expect( handlers.onReorderOthers ).not.toHaveBeenCalled();
	} );

	it( 'calls onDemote with the target slot when the main row is dropped into the other list', () => {
		const mainSlot = document.createElement( 'div' );
		const otherList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, otherList, handlers );

		const dragged = document.createElement( 'div' );
		dragged.id = subjectRowDomId( VALID_ID );

		findCallByContainer( otherList ).options.onDropIn( dragged, 0, 1 );

		expect( handlers.onDemote ).toHaveBeenCalledTimes( 1 );
		expect( ( handlers.onDemote as ReturnType<typeof vi.fn> ).mock.calls[ 0 ][ 0 ] ).toBe( 1 );
		expect( handlers.onPromote ).not.toHaveBeenCalled();
	} );

	it( 'calls onReorderOthers when a row is moved within the other list', () => {
		const mainSlot = document.createElement( 'div' );
		const otherList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, otherList, handlers );

		findCallByContainer( otherList ).options.onReorder( 0, 2 );

		expect( handlers.onReorderOthers ).toHaveBeenCalledWith( 0, 2 );
		expect( handlers.onPromote ).not.toHaveBeenCalled();
		expect( handlers.onDemote ).not.toHaveBeenCalled();
	} );

	it( 'does not call onPromote when the dropped element has no recognized subject id', () => {
		const mainSlot = document.createElement( 'div' );
		const otherList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, otherList, handlers );

		const dragged = document.createElement( 'li' );
		dragged.id = 'something-else';

		findCallByContainer( mainSlot ).options.onDropIn( dragged, 0, 0 );

		expect( handlers.onPromote ).not.toHaveBeenCalled();
	} );

	it( 'does not call onPromote when the prefix matches but the body is not a valid subject id', () => {
		const mainSlot = document.createElement( 'div' );
		const otherList = document.createElement( 'ul' );
		const handlers = newHandlers();

		mountComposable( mainSlot, otherList, handlers );

		const dragged = document.createElement( 'li' );
		dragged.id = subjectRowDomId( 'not-a-valid-id' );

		expect( () => findCallByContainer( mainSlot ).options.onDropIn( dragged, 0, 0 ) ).not.toThrow();
		expect( handlers.onPromote ).not.toHaveBeenCalled();
	} );

} );
