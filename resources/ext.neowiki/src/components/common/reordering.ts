import type { MenuButtonItemData } from '@wikimedia/codex';

export type ReorderMove = 'move-to-top' | 'move-up' | 'move-down' | 'move-to-bottom';

const MOVE_MESSAGES: Record<ReorderMove, string> = {
	'move-to-top': 'neowiki-reorder-move-to-top',
	'move-up': 'neowiki-reorder-move-up',
	'move-down': 'neowiki-reorder-move-down',
	'move-to-bottom': 'neowiki-reorder-move-to-bottom',
};

const MOVES = Object.keys( MOVE_MESSAGES ) as ReorderMove[];

/**
 * Matches the button of every ReorderHandle, which SortableJS drags a list's items by.
 */
export const REORDER_HANDLE_SELECTOR = '[data-mw-neowiki-reorder-handle]';

export function isReorderMove( value: unknown ): value is ReorderMove {
	return MOVES.includes( value as ReorderMove );
}

export function positionAfter( move: ReorderMove, position: number, count: number ): number {
	switch ( move ) {
		case 'move-to-top':
			return 0;
		case 'move-up':
			return Math.max( position - 1, 0 );
		case 'move-down':
			return Math.min( position + 1, count - 1 );
		case 'move-to-bottom':
			return count - 1;
	}
}

/**
 * The moves on offer to an item, those that would leave it where it is disabled.
 */
export function reorderMenuItems( atTop: boolean, atBottom: boolean ): MenuButtonItemData[] {
	const stays: Record<ReorderMove, boolean> = {
		'move-to-top': atTop,
		'move-up': atTop,
		'move-down': atBottom,
		'move-to-bottom': atBottom,
	};

	return MOVES.map( ( move ) => ( {
		value: move,
		label: mw.message( MOVE_MESSAGES[ move ] ).text(),
		disabled: stays[ move ],
	} ) );
}

export function reorderHandleLabel( itemName: string ): string {
	return mw.message( 'neowiki-reorder-handle', itemName ).text();
}

/**
 * What a list says once it has moved the item to the place, from 0, in a list of count items.
 */
export function movedAnnouncement( itemName: string, to: number, count: number ): string {
	return mw.message( 'neowiki-reorder-moved', itemName, to + 1, count ).text();
}

export function withItemMoved<T>( items: readonly T[], from: number, to: number ): T[] {
	const moved = [ ...items ];
	const [ item ] = moved.splice( from, 1 );
	moved.splice( to, 0, item );

	return moved;
}
