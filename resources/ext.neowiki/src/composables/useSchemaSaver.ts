import { NeoWikiServices } from '@/NeoWikiServices';
import { Schema } from '@/domain/Schema';

interface UseSchemaSaverReturn {
	saveSchema: ( schema: Schema, editSummary: string ) => Promise<void>;
}

export function useSchemaSaver(): UseSchemaSaverReturn {
	const schemaRepository = NeoWikiServices.getSchemaRepository();

	const saveSchema = async ( schema: Schema, editSummary: string ): Promise<void> => {
		try {
			// TODO: Save edit summary to revision so it shows on the history page
			await schemaRepository.saveSchema( schema );
			mw.notify(
				editSummary || 'No edit summary provided.',
				{
					title: `Updated ${ schema.getName() } schema`,
					type: 'success',
				},
			);
			window.location.href = mw.util.getUrl( `Schema:${ schema.getName() }` );
		} catch ( error ) {
			mw.notify(
				error instanceof Error ? error.message : String( error ),
				{
					title: `Failed to update ${ schema.getName() } schema.`,
					type: 'error',
				},
			);
			throw error;
		}
	};

	return {
		saveSchema,
	};
}
