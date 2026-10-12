import type { InjectionKey, Ref } from 'vue';

/**
 * Provided by the Schema editor: true once its host has refused a save, from when on a field shows the
 * error it holds back until the user leaves it.
 */
export const ErrorsRevealedKey: InjectionKey<Readonly<Ref<boolean>>> = Symbol( 'NeoWikiErrorsRevealed' );
