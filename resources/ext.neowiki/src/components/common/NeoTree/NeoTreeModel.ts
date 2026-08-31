export interface NeoTreeItem<T> {
	// Unique across the whole tree, so it cannot be taken from the payload: one thing reachable
	// by two paths is two items, and a shared key collapses them into one focus target.
	key: string;
	label: string;
	secondaryLabel?: string;
	active?: boolean;
	// Caption printed once above this item and the contiguous siblings sharing it. One caption
	// repeated after an interruption prints twice, as two groups.
	groupLabel?: string;
	attrs?: Record<string, string>;
	children?: NeoTreeItem<T>[];
	data: T;
}
