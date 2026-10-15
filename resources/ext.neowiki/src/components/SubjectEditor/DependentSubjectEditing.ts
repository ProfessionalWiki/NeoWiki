import type { InjectionKey, Ref } from 'vue';
import type { Subject } from '@/domain/Subject';
import type { Schema } from '@/domain/Schema';
import type { RelationProperty } from '@/domain/propertyTypes/Relation';
import type { SubjectViolation } from '@/domain/SubjectViolation';

/**
 * Provided by an editor so that its relation fields edit Dependent Subjects inline. Without it, a field picks its
 * targets.
 */
export interface DependentSubjectEditing {

	targetsDependentSubjects( property: RelationProperty ): boolean;

	schema( schemaName: string ): Schema | undefined;

	/**
	 * As editing started from it, not as its fields now hold it. Undefined for one that did not load.
	 */
	subject( id: string ): Subject | undefined;

	/**
	 * Whether the wiki does not hold it yet.
	 */
	isNew( id: string ): boolean;

	/**
	 * Makes the save write this Dependent Subject.
	 */
	markChanged( id: string ): void;

	/**
	 * Also adds the Dependent Subjects it starts with. Resolves to null on failure, which it has then reported.
	 */
	add( schemaName: string ): Promise<Subject | null>;

	/**
	 * The Subjects the editor holds that the wiki does not yet: a relation may name one though the server
	 * cannot resolve it.
	 */
	unsavedTargetIds(): readonly string[];

}

export const DependentSubjectEditingKey: InjectionKey<DependentSubjectEditing> = Symbol( 'NeoWikiDependentSubjectEditing' );

/**
 * The violations a refused save reported on a pane's Dependent Subjects, each naming its Subject.
 */
export const DependentSubjectViolationsKey: InjectionKey<Readonly<Ref<readonly SubjectViolation[]>>> =
	Symbol( 'NeoWikiDependentSubjectViolations' );

/**
 * Counts the writes of the Subject a pane edits. A removal of a Dependent Subject that a write carried is final.
 */
export const PaneWritesKey: InjectionKey<Readonly<Ref<number>>> = Symbol( 'NeoWikiPaneWrites' );
