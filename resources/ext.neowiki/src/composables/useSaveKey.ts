import { computed, onMounted, onUpdated, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';

interface SaveKeyAttributes {
	accesskey?: string;
	title?: string;
}

/**
 * Attributes that give the button MediaWiki's save key while `held` holds, and a tooltip of `label` that
 * names the key. Bind them on the button that `button` refers to.
 */
export function useSaveKey(
	button: Ref<{ $el: HTMLElement } | null>,
	label: MaybeRefOrGetter<string>,
	held: MaybeRefOrGetter<boolean>,
): ComputedRef<SaveKeyAttributes> {
	// As in core, a message of "-" or nothing switches the key off.
	const wikiSaveKey = mw.msg( 'accesskey-save' );
	const saveKey = wikiSaveKey === '-' || wikiSaveKey === '' ? undefined : wikiSaveKey;

	const attributes = computed( (): SaveKeyAttributes => saveKey !== undefined && toValue( held ) ?
		{ accesskey: saveKey, title: toValue( label ) } :
		{} );

	// Vue resets the title whenever the key or the label changes, which drops the key's name.
	function nameKeyInTooltip(): void {
		if ( attributes.value.accesskey !== undefined && button.value !== null ) {
			$( button.value.$el ).updateTooltipAccessKeys();
		}
	}

	onMounted( nameKeyInTooltip );
	onUpdated( nameKeyInTooltip );

	return attributes;
}
