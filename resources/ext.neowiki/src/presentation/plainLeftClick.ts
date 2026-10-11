/**
 * Whether a click is a plain left click, which a component may take over. A modifier or another button
 * asks the browser to open the link elsewhere, which is left to it.
 */
export function isPlainLeftClick( event: MouseEvent ): boolean {
	return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
}
