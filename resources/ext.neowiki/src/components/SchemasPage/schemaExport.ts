import type { DataModelRepository } from '@/application/modelBundle/DataModelRepository.ts';
import { exportBundle } from '@/application/modelBundle/bundleExport.ts';
import { serializeBundleFile } from '@/persistence/bundleFile.ts';

export async function exportSchemas( names: string[], repository: DataModelRepository ): Promise<string> {
	return serializeBundleFile( await exportBundle( {
		schemas: names.map( ( name ) => ( { name, withSubjects: false } ) ),
		layouts: false,
		mappings: [],
		metadata: null,
	}, repository ) );
}
