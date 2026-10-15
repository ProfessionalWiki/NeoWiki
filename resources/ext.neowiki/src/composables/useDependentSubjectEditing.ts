import { computed, provide, shallowReactive, type ComputedRef, type Ref } from 'vue';
import type { Subject } from '@/domain/Subject';
import type { Schema } from '@/domain/Schema';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { localTargetSchemaNames, type RelationProperty, targetsDependentSubjects } from '@/domain/propertyTypes/Relation';
import type { DependentSubjectLoader, DependentSubjectsForEditing } from '@/application/DependentSubjectLoader';
import {
	DependentSubjectEditingKey,
	type DependentSubjectEditing,
} from '@/components/SubjectEditor/DependentSubjectEditing';

export interface UseDependentSubjectEditing {
	/**
	 * Drops what an earlier opening held. `added` tells whether the Dependent Subjects were added in the editor
	 * rather than read from the wiki.
	 */
	startFrom( forEditing: DependentSubjectsForEditing, added: boolean ): void;
	include( forEditing: DependentSubjectsForEditing, added: boolean ): void;
	/**
	 * After a save: a later save replaces rather than creates these, and writes them only once edited again.
	 */
	markWritten( subjects: readonly Subject[] ): void;
	/**
	 * The ones among these a save writes: those added in the editor or edited since the last save.
	 */
	edited( subjects: readonly Subject[] ): Subject[];
	/**
	 * The ids of the drafts and of the Dependent Subjects added in the editor.
	 */
	unsavedTargetIds: ComputedRef<string[]>;
	schemas: ReadonlyMap<string, Schema>;
}

interface HeldDependentSubject {
	subject: Subject;
	added: boolean;
}

/**
 * `addingAllowed` is false while the editor saves: a Dependent Subject added after the save read the form would be
 * neither written nor kept. `draftIds` are the other Subjects the editor holds that the wiki does not.
 */
export function useDependentSubjectEditing(
	loader: Pick<DependentSubjectLoader, 'newDependentSubject'>,
	addingAllowed: () => boolean,
	draftIds: Readonly<Ref<readonly string[]>>,
): UseDependentSubjectEditing {
	const schemas = shallowReactive( new Map<string, Schema>() );
	const subjects = shallowReactive( new Map<string, HeldDependentSubject>() );
	const changedIds = new Set<string>();

	// Bumped by every start, so that an addition outliving its opening lands nowhere.
	let session = 0;

	function include( forEditing: DependentSubjectsForEditing, added: boolean ): void {
		forEditing.schemas.forEach( ( schema ) => schemas.set( schema.getName(), schema ) );
		forEditing.subjects.forEach( ( subject ) => {
			subjects.set( subject.getId().text, { subject, added } );
			changedIds.delete( subject.getId().text );
		} );
	}

	function startFrom( forEditing: DependentSubjectsForEditing, added: boolean ): void {
		session++;
		schemas.clear();
		subjects.clear();
		changedIds.clear();
		include( forEditing, added );
	}

	function markWritten( written: readonly Subject[] ): void {
		for ( const subject of written ) {
			const held = subjects.get( subject.getId().text );

			if ( held !== undefined ) {
				subjects.set( subject.getId().text, { subject: held.subject, added: false } );
				changedIds.delete( subject.getId().text );
			}
		}
	}

	function edited( candidates: readonly Subject[] ): Subject[] {
		return candidates.filter( ( subject ) =>
			subjects.get( subject.getId().text )?.added === true || changedIds.has( subject.getId().text ) );
	}

	const unsavedTargetIds = computed( (): string[] => [
		...draftIds.value,
		...[ ...subjects.entries() ].filter( ( [ , held ] ) => held.added ).map( ( [ id ] ) => id ),
	] );

	async function add( schemaName: string ): Promise<Subject | null> {
		const schema = schemas.get( schemaName );
		const addingSession = session;

		try {
			if ( schema === undefined || !addingAllowed() ) {
				throw new Error( `Cannot add a Dependent Subject of the Schema ${ schemaName }` );
			}

			const [ subject, ...startedWith ] = await loader.newDependentSubject(
				schema,
				PageIdentifiers.notYetCreated(),
				schemas,
			);

			if ( addingSession !== session || !addingAllowed() ) {
				return null;
			}

			include( { subjects: [ subject, ...startedWith ], schemas: [] }, true );

			return subject;
		} catch ( error ) {
			if ( addingSession === session ) {
				console.error( 'Failed to add a dependent subject:', error );
				mw.notify( mw.msg( 'neowiki-dependent-subject-add-error' ), { type: 'error' } );
			}

			return null;
		}
	}

	const editing: DependentSubjectEditing = {
		targetsDependentSubjects: ( property: RelationProperty ): boolean => targetsDependentSubjects(
			localTargetSchemaNames( property ),
			( schemaName ) => schemas.get( schemaName )?.subjectsAreDependent(),
		) === true,
		schema: ( schemaName: string ): Schema | undefined => schemas.get( schemaName ),
		subject: ( id: string ): Subject | undefined => subjects.get( id )?.subject,
		isNew: ( id: string ): boolean => subjects.get( id )?.added === true,
		markChanged: ( id: string ): void => {
			changedIds.add( id );
		},
		add,
		unsavedTargetIds: (): readonly string[] => unsavedTargetIds.value,
	};

	provide( DependentSubjectEditingKey, editing );

	return { startFrom, include, markWritten, edited, unsavedTargetIds, schemas };
}
