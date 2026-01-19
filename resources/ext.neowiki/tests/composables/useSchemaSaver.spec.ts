import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useSchemaSaver } from '@/composables/useSchemaSaver';
import { NeoWikiServices } from '@/NeoWikiServices';
import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';

vi.mock( '@/NeoWikiServices', () => ( {
	NeoWikiServices: {
		getSchemaRepository: vi.fn(),
	},
} ) );

describe( 'useSchemaSaver', () => {
	const mockSaveSchema = vi.fn();

	beforeEach( () => {
		vi.clearAllMocks();
		( NeoWikiServices.getSchemaRepository as any ).mockReturnValue( {
			saveSchema: mockSaveSchema,
		} );

		vi.stubGlobal( 'mw', {
			notify: vi.fn(),
		} );
	} );

	const createMockSchema = (): Schema => new Schema(
		'TestSchema',
		'Description',
		new PropertyDefinitionList( [] ),
	);

	it( 'saves schema successfully and notifies user', async () => {
		const { saveSchema } = useSchemaSaver();
		const schema = createMockSchema();
		const summary = 'Test Summary';

		await saveSchema( schema, summary );

		expect( mockSaveSchema ).toHaveBeenCalledWith( schema );
		expect( mw.notify ).toHaveBeenCalledWith(
			summary,
			expect.objectContaining( {
				title: 'Updated TestSchema schema',
				type: 'success',
			} ),
		);
	} );

	it( 'uses default summary if none provided', async () => {
		const { saveSchema } = useSchemaSaver();
		const schema = createMockSchema();

		await saveSchema( schema, '' );

		expect( mw.notify ).toHaveBeenCalledWith(
			'No edit summary provided.',
			expect.any( Object ),
		);
	} );

	it( 'handles save error and notifies user', async () => {
		const error = new Error( 'Save failed' );
		mockSaveSchema.mockRejectedValue( error );

		const { saveSchema } = useSchemaSaver();
		const schema = createMockSchema();

		await expect( saveSchema( schema, 'summary' ) ).rejects.toThrow( error );

		expect( mw.notify ).toHaveBeenCalledWith(
			'Save failed',
			expect.objectContaining( {
				title: 'Failed to update TestSchema schema.',
				type: 'error',
			} ),
		);
	} );
} );
