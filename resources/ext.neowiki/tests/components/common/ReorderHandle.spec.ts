import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import ReorderHandle from '@/components/common/ReorderHandle.vue';
import { withItemMoved } from '@/components/common/reordering.ts';
import { chooseReorderMove, enabledReorderMoves, REORDER_MESSAGES, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'ReorderHandle', () => {

	beforeEach( () => {
		setupMwMock( { messages: REORDER_MESSAGES } );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	function newHandle( position: number, count: number ): VueWrapper {
		return mount( ReorderHandle, {
			props: { position, count, label: 'Reorder Stage' },
			attachTo: document.body,
		} );
	}

	function handleButton( wrapper: VueWrapper | DOMWrapper<Element> ): DOMWrapper<HTMLButtonElement> {
		return wrapper.find<HTMLButtonElement>( 'button[data-mw-neowiki-reorder-handle]' );
	}

	async function press( wrapper: VueWrapper, key: string, init: KeyboardEventInit = {} ): Promise<void> {
		await handleButton( wrapper ).trigger( 'keydown', { key, ...init } );
		await flushPromises();
	}

	// Down and up, as a browser sends them: Codex 1.14's menu button acts on Enter's keyup as well as its keydown.
	async function pressAndRelease( wrapper: VueWrapper, key: string ): Promise<void> {
		await handleButton( wrapper ).trigger( 'keydown', { key } );
		await handleButton( wrapper ).trigger( 'keyup', { key } );
		await flushPromises();
	}

	function moves( wrapper: VueWrapper ): unknown[][] | undefined {
		return wrapper.emitted( 'move' );
	}

	it.each( [
		[ 'first', 0, [ 'Move down', 'Move to bottom' ] ],
		[ 'last', 2, [ 'Move to top', 'Move up' ] ],
	] )( 'offers the %s item only the moves that take it somewhere else', async ( _, position, offered ) => {
		const wrapper = newHandle( position, 3 );

		await handleButton( wrapper ).trigger( 'click' );

		expect( enabledReorderMoves( wrapper ) ).toEqual( offered );
	} );

	it( 'reports where a chosen move takes its item', async () => {
		const wrapper = newHandle( 1, 4 );

		await chooseReorderMove( wrapper, 'Reorder Stage', 'Move to bottom' );

		expect( moves( wrapper ) ).toEqual( [ [ 3 ] ] );
	} );

	it( 'moves its item one place down on Alt+ArrowDown', async () => {
		const wrapper = newHandle( 1, 4 );

		await press( wrapper, 'ArrowDown', { altKey: true } );

		expect( moves( wrapper ) ).toEqual( [ [ 2 ] ] );
	} );

	it( 'moves its item one place up on Alt+ArrowUp', async () => {
		const wrapper = newHandle( 2, 4 );

		await press( wrapper, 'ArrowUp', { altKey: true } );

		expect( moves( wrapper ) ).toEqual( [ [ 1 ] ] );
	} );

	it( 'keeps Alt+ArrowUp on the first item to itself', async () => {
		const wrapper = newHandle( 0, 4 );

		await press( wrapper, 'ArrowUp', { altKey: true } );

		expect( moves( wrapper ) ).toBeUndefined();
		expect( handleButton( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'leaves the last item where it is on Alt+ArrowDown', async () => {
		const wrapper = newHandle( 3, 4 );

		await press( wrapper, 'ArrowDown', { altKey: true } );

		expect( moves( wrapper ) ).toBeUndefined();
	} );

	it( 'moves nothing on Alt+ArrowDown pressed with another modifier', async () => {
		const wrapper = newHandle( 1, 4 );

		await press( wrapper, 'ArrowDown', { altKey: true, ctrlKey: true } );

		expect( moves( wrapper ) ).toBeUndefined();
	} );

	it( 'makes no move highlighted in its menu when Tab leaves it', async () => {
		const wrapper = newHandle( 1, 4 );
		await press( wrapper, 'ArrowDown' );
		await press( wrapper, 'ArrowDown' );

		await press( wrapper, 'Tab' );

		expect( moves( wrapper ) ).toBeUndefined();
	} );

	it( 'leaves an arrow key without Alt to its menu', async () => {
		const wrapper = newHandle( 1, 4 );

		await press( wrapper, 'ArrowDown' );

		expect( moves( wrapper ) ).toBeUndefined();
		expect( handleButton( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'opens its menu on Enter', async () => {
		const wrapper = newHandle( 1, 4 );

		await pressAndRelease( wrapper, 'Enter' );

		expect( handleButton( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'opens its menu on Space', async () => {
		const wrapper = newHandle( 1, 4 );

		await pressAndRelease( wrapper, ' ' );

		expect( handleButton( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'makes the move highlighted in its menu on Enter, and closes the menu', async () => {
		const wrapper = newHandle( 2, 4 );
		await press( wrapper, 'ArrowDown' );
		await press( wrapper, 'ArrowDown' );
		await press( wrapper, 'ArrowDown' );

		await pressAndRelease( wrapper, 'Enter' );

		expect( moves( wrapper ) ).toEqual( [ [ 1 ] ] );
		expect( handleButton( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'closes its menu when the focus leaves it', async () => {
		const elsewhere = document.body.appendChild( document.createElement( 'button' ) );
		const wrapper = newHandle( 1, 4 );
		handleButton( wrapper ).element.focus();
		await handleButton( wrapper ).trigger( 'click' );

		elsewhere.focus();
		await flushPromises();

		expect( handleButton( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'keeps a click on it from reaching the row around it', async () => {
		const wrapper = newHandle( 1, 4 );
		const listener = vi.fn();
		document.body.addEventListener( 'click', listener );

		await handleButton( wrapper ).trigger( 'click' );
		document.body.removeEventListener( 'click', listener );

		expect( listener ).not.toHaveBeenCalled();
	} );

	it( 'leaves a details element it sits in the summary of closed when a move is chosen', async () => {
		const details = document.createElement( 'details' );
		const summary = details.appendChild( document.createElement( 'summary' ) );
		document.body.appendChild( details );
		const wrapper = mount( ReorderHandle, {
			props: { position: 1, count: 4, label: 'Reorder Stage' },
			attachTo: summary.appendChild( document.createElement( 'span' ) ),
		} );

		await chooseReorderMove( wrapper, 'Reorder Stage', 'Move up' );

		expect( details.open ).toBe( false );
	} );

	it( 'cannot be used while its item is the only one', () => {
		const wrapper = newHandle( 0, 1 );

		expect( handleButton( wrapper ).element.disabled ).toBe( true );
	} );

	it( 'tells assistive technology about its shortcut', () => {
		expect( handleButton( newHandle( 1, 4 ) ).attributes( 'aria-keyshortcuts' ) ).toBe( 'Alt+ArrowUp Alt+ArrowDown' );
	} );

	describe( 'in a list', () => {

		// A late list moves the item only once landPendingMove() is called, as the Subjects manager does after its save.
		let landPendingMove = (): void => undefined;

		const List = defineComponent( {
			props: { late: { type: Boolean, default: false } },
			setup( props ) {
				const items = ref( [ 'a', 'b', 'c', 'd' ] );

				return () => h( 'ol', items.value.map( ( item, index ) => h( 'li', { key: item, id: item }, [
					h( ReorderHandle, {
						position: index,
						count: items.value.length,
						label: 'Reorder ' + item,
						onMove: ( to: number ) => {
							landPendingMove = () => {
								items.value = withItemMoved( items.value, index, to );
							};

							if ( !props.late ) {
								landPendingMove();
							}
						},
					} ),
				] ) ) );
			},
		} );

		async function land(): Promise<void> {
			landPendingMove();
			await flushPromises();
		}

		function handleOf( wrapper: VueWrapper, item: string ): DOMWrapper<HTMLButtonElement> {
			return handleButton( wrapper.find( '#' + item ) );
		}

		it( 'keeps the focus on the handle of the item it moved', async () => {
			const wrapper = mount( List, { attachTo: document.body } );
			handleOf( wrapper, 'c' ).element.focus();

			await chooseReorderMove( wrapper, 'Reorder c', 'Move to top' );

			expect( wrapper.findAll( 'ol > li' ).map( ( item ) => item.attributes( 'id' ) ) ).toEqual( [ 'c', 'a', 'b', 'd' ] );
			expect( document.activeElement ).toBe( handleOf( wrapper, 'c' ).element );
		} );

		it( 'takes the focus back when its item arrives late', async () => {
			const wrapper = mount( List, { props: { late: true }, attachTo: document.body } );
			handleOf( wrapper, 'c' ).element.focus();
			await chooseReorderMove( wrapper, 'Reorder c', 'Move to top' );

			await land();

			expect( document.activeElement ).toBe( handleOf( wrapper, 'c' ).element );
		} );

		it( 'forgets a move that never arrived once the user has moved the focus on', async () => {
			const elsewhere = document.body.appendChild( document.createElement( 'button' ) );
			const wrapper = mount( List, { props: { late: true }, attachTo: document.body } );
			handleOf( wrapper, 'c' ).element.focus();
			await chooseReorderMove( wrapper, 'Reorder c', 'Move to top' );
			elsewhere.focus();
			elsewhere.blur();

			await chooseReorderMove( wrapper, 'Reorder d', 'Move to top' );
			await land();

			expect( document.activeElement ).not.toBe( handleOf( wrapper, 'c' ).element );
		} );

		it( 'leaves the focus where the user put it while the move was pending', async () => {
			const elsewhere = document.body.appendChild( document.createElement( 'button' ) );
			const wrapper = mount( List, { props: { late: true }, attachTo: document.body } );
			handleOf( wrapper, 'c' ).element.focus();
			await chooseReorderMove( wrapper, 'Reorder c', 'Move to top' );
			elsewhere.focus();

			await land();

			expect( document.activeElement ).toBe( elsewhere );
		} );

	} );

} );
