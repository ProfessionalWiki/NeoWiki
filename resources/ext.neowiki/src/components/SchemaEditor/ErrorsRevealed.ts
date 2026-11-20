import type { InjectionKey, Ref } from 'vue';

/**
 * Provided by the Schema editor for the selected property: true once its host has refused a save, or once the user
 * has left the property and come back to it. While it is true, a field shows the error it holds back until the user
 * leaves it.
 */
export const ErrorsRevealedKey: InjectionKey<Readonly<Ref<boolean>>> = Symbol( 'NeoWikiErrorsRevealed' );
