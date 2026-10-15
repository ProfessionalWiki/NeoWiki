import { inject, provide, ref, type InjectionKey, type Ref } from 'vue';
import type { Schema } from '@/domain/Schema.ts';
import { SchemaCreationKey } from '@/components/common/SchemaCreation.ts';

export interface StackedSchemaCreator {
	/** The name the Create schema dialog opened with, null while it is closed. */
	initialName: Ref<string | null>;
	/** Decided for the user; null leaves it to them. */
	subjectsAreDependent: Ref<boolean | null>;
	created: ( schema: Schema ) => void;
	closed: () => void;
	/** How many Schema dialogs the calling dialog stands in front of. */
	stackLevel: number;
}

const StackLevelKey: InjectionKey<number> = Symbol( 'NeoWikiSchemaDialogStackLevel' );

/**
 * Provides SchemaCreation to the calling dialog's content. The dialog mounts the Create schema dialog
 * next to its own CdxDialog while `initialName` is set, never inside it: from Codex 2.3 a dialog
 * mounted inside another is teleported into it, where Escape closes both.
 */
export function useSchemaCreation(): StackedSchemaCreator {
	const initialName = ref<string | null>( null );
	const subjectsAreDependent = ref<boolean | null>( null );
	let settle: ( schema: Schema | null ) => void = () => undefined;
	const stackLevel = inject( StackLevelKey, 0 );

	provide( SchemaCreationKey, {
		create: ( name: string, dependent: boolean | null ): Promise<Schema | null> => {
			initialName.value = name;
			subjectsAreDependent.value = dependent;

			return new Promise( ( resolve ) => {
				settle = resolve;
			} );
		},
	} );

	provide( StackLevelKey, stackLevel + 1 );

	return {
		initialName,
		subjectsAreDependent,
		created: ( schema: Schema ): void => settle( schema ),
		closed: (): void => {
			initialName.value = null;
			settle( null );
		},
		stackLevel,
	};
}
