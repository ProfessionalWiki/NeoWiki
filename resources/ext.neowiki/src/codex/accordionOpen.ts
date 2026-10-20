/**
 * Codex: hands an accordion's toggle listener the event in 1.14, and in 1.23 and later a boolean that is
 *   false whenever the event lacks newState, as in Chrome before 119, Firefox before 120 and Safari before 17.2.
 * Remove when: the Codex of the oldest MediaWiki NeoWiki supports reports the open state in every browser
 *   MediaWiki supports; not yet in Codex 2.7.0.
 */
export function isAccordionOpen( accordion: { $el: Element } | null ): boolean {
	return ( accordion?.$el as HTMLDetailsElement | undefined )?.open ?? false;
}
