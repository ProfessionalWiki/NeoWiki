import { NeoWikiServices, Service } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { InMemoryLayoutLookup } from '@/application/LayoutLookup.ts';
import { StubSubjectRepository } from '@/domain/SubjectRepository.ts';
import type { SubjectLabelSearch } from '@/domain/SubjectLabelSearch.ts';
import type { SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';
import type { SubjectCountLookup } from '@/application/SubjectCountLookup.ts';
import type { PageTitleSearch } from '@/domain/PageTitleSearch.ts';
import { InMemoryDataModel } from './application/modelBundle/InMemoryDataModel.ts';

export class NeoWikiTestServices extends NeoWikiServices {

	public static getServices(): Record<string, unknown> {
		const neoWiki = NeoWikiExtension.getInstance();
		const dataModel = new InMemoryDataModel();

		return {
			[ Service.ComponentRegistry ]: neoWiki.getTypeSpecificComponentRegistry(),
			[ Service.SchemaPermissionHints ]: neoWiki.newSchemaPermissionHints(),
			[ Service.SubjectPermissionHints ]: neoWiki.newSubjectPermissionHints(),
			[ Service.PropertyTypeRegistry ]: neoWiki.getPropertyTypeRegistry(),
			[ Service.SchemaRepository ]: new InMemorySchemaRepository( [] ),
			[ Service.SubjectRepository ]: new StubSubjectRepository( [] ),
			[ Service.SubjectLabelSearch ]: { searchSubjectLabels: () => Promise.resolve( [] ) } as SubjectLabelSearch,
			[ Service.SubjectSummaryLookup ]: {
				getSubjectSummaries: () => Promise.resolve( { subjects: [], nextCursor: null } ),
				getRecentlyEditedSubjects: () => Promise.resolve( [] ),
			} as SubjectSummaryLookup,
			[ Service.SubjectCountLookup ]: {
				getSubjectCounts: () => Promise.resolve( new Map() ),
			} as SubjectCountLookup,
			[ Service.PageTitleSearch ]: { searchPageTitles: () => Promise.resolve( [] ) } as PageTitleSearch,
			[ Service.ViewTypeRegistry ]: neoWiki.getViewTypeRegistry(),
			[ Service.LayoutPermissionHints ]: neoWiki.newLayoutPermissionHints(),
			[ Service.LayoutRepository ]: new InMemoryLayoutLookup( [] ),
			[ Service.MappingPermissionHints ]: neoWiki.newMappingPermissionHints(),
			[ Service.ModelBundlePermissionHints ]: { canInstallModelBundles: () => Promise.resolve( false ) },
			[ Service.DataModelRepository ]: dataModel,
			[ Service.InstallRecordRepository ]: dataModel,
		};
	}

}
