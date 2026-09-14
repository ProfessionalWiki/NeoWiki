import type { InjectionKey } from 'vue';
import type { Value } from '@/domain/Value';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { SubjectWithContext } from '@/domain/SubjectWithContext';

export interface ValueDisplayProps<T extends PropertyDefinition> {
	value: Value;
	property: T;
}

/**
 * Provided by hosts that show Subjects rather than pages, to say where a relation leads.
 * RelationDisplay links each relation to what this returns for its target. Without it, a
 * relation links to the page its target is stored on.
 */
export const RelationTargetUrlKey: InjectionKey<( target: SubjectWithContext ) => string> = Symbol( 'NeoWikiRelationTargetUrl' );
