import { NeoWikiServices } from '@/NeoWikiServices';
import { Schema } from '@/domain/Schema';

interface UseSchemaSaverReturn {
	saveSchema: ( schema: Schema, comment?: string ) => Promise<void>;
}

export function useSchemaSaver(): UseSchemaSaverReturn {
	const schemaRepository = NeoWikiServices.getSchemaRepository();

	const saveSchema = async ( schema: Schema, comment?: string ): Promise<void> => {
		try {
			await schemaRepository.saveSchema( schema, comment );
			mw.notify( `Updated ${ schema.getName() } schema`, { type: 'success' } );
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
