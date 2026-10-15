import type { InjectionKey } from 'vue';
import type { Schema } from '@/domain/Schema';

/**
 * How a dialog lets a picker inside it create the Schema it does not find, in the Create schema dialog
 * stacked in front of it. A picker without it offers only the Schemas the wiki already has.
 */
export interface SchemaCreation {

	/**
	 * Opens the Create schema dialog with the given name filled in, and `subjectsAreDependent` decided for the
	 * user unless null. Resolves to the Schema created there, or to null once the dialog is closed without
	 * creating one.
	 */
	create( initialName: string, subjectsAreDependent: boolean | null ): Promise<Schema | null>;

}

export const SchemaCreationKey: InjectionKey<SchemaCreation> = Symbol( 'NeoWikiSchemaCreation' );
