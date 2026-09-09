export interface NeoTreeItem<T> {
	// Unique across the whole tree, so it cannot be taken from the payload: one thing reachable
	// by two paths is two items, and a shared key collapses them into one focus target.
	key: string;
	label: string;
	active?: boolean;
	// Caption for this item and the contiguous siblings sharing it: printed above them where
	// there are several, and on the item's own row where it is alone, joining that row's
	// accessible name. Repeated after an interruption it prints twice, as two groups. Dropped
	// on a top-level item, which the tree renders without grouping.
	groupLabel?: string;
	attrs?: Record<string, string>;
	children?: NeoTreeItem<T>[];
	data: T;
}
