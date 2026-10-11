import type { InjectionKey, Ref } from 'vue';
import type { Value } from '@/domain/Value';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Subject } from '@/domain/Subject';

export interface ValueDisplayProps<T extends PropertyDefinition> {
	value: Value;
	property: T;
}

/**
 * Provided by hosts that show Subjects rather than pages, to say where a relation leads.
 * RelationDisplay links each relation to what this returns for its target. Without it, a
 * relation links to the page its target is stored on.
 */
export const RelationTargetUrlKey: InjectionKey<( target: Subject ) => string> = Symbol( 'NeoWikiRelationTargetUrl' );

/**
 * Whether this user may create a relation target no read found, with the host's Subject creator.
 * Absent, a red link only leads to the target's Special:Subject page.
 */
export const RelationTargetCreationKey: InjectionKey<Readonly<Ref<boolean>>> = Symbol( 'NeoWikiRelationTargetCreation' );
