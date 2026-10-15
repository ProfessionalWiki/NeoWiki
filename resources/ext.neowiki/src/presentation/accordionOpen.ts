/**
 * Codex 1.14 hands a toggle listener the event, and Codex 1.23 and later a boolean that is false whenever
 * the event lacks newState, as in Chrome before 119, Firefox before 120 and Safari before 17.2.
 */
export function isAccordionOpen( accordion: { $el: Element } | null ): boolean {
	return ( accordion?.$el as HTMLDetailsElement | undefined )?.open ?? false;
}
