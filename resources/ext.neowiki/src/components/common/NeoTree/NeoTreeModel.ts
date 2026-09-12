export interface NeoTreeItem<T> {
	// Unique across the whole tree, so it cannot be taken from the payload: one thing reachable
	// by two paths is two items, and a shared key collapses them into one focus target.
	key: string;
	label: string;
	active?: boolean;
	// Caption for this item and the contiguous siblings sharing it: printed above them where
	// there are several, and on the item's own row where it is alone. Repeated after an
	// interruption it prints twice, as two groups. Dropped on a top-level item.
	groupLabel?: string;
	attrs?: Record<string, string>;
	// Printed only while the item is expanded, so the printed order — which the arrow keys move
	// through and the element ids are numbered in — can never reach a row nobody can see.
	children?: NeoTreeItem<T>[];
	data: T;
	// Declared apart from whether the item holds children yet: a row whose subtree has not been
	// asked for still needs a control to ask with.
	expandable?: boolean;
	// Read only where the item is expandable. The tree holds no expansion state of its own, so
	// the caller decides.
	expanded?: boolean;
	// Whether the caller will act on a `toggle`. One that says no keeps `aria-expanded`, since it
	// is still a parent, but draws no control and answers neither arrow key.
	collapsible?: boolean;
}

/**
 * The first two default to what an eagerly built tree means: an item with children has them and
 * is showing them.
 *
 * Here rather than in either component because both read them and they must agree. The tree's
 * reading decides which rows exist, the node's what reaches the document; a divergence leaves the
 * roving tab stop on an element nobody rendered, so focus stops moving while tabindex goes on
 * changing.
 */
export function isExpandable<T>( item: NeoTreeItem<T> ): boolean {
	return item.expandable ?? ( item.children ?? [] ).length > 0;
}

export function isExpanded<T>( item: NeoTreeItem<T> ): boolean {
	return item.expanded ?? ( item.children ?? [] ).length > 0;
}

// An item the caller will not close is offered neither a control nor either key's toggle:
// pressing one would do nothing, which reads as the tree being broken.
export function isTogglable<T>( item: NeoTreeItem<T> ): boolean {
	return isExpandable( item ) && ( item.collapsible ?? true );
}
