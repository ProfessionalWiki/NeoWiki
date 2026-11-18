import { inject, type App } from 'vue';
import { MediaWikiPageSaver, NeoWikiExtension } from 'ext.neowiki';
import type { ModelBundlePermissionHints } from './application/ModelBundlePermissionHints.ts';
import type { DataModelRepository, InstallRecordRepository } from './application/DataModelRepository.ts';
import { RightsBasedModelBundlePermissionHints } from './persistence/RightsBasedModelBundlePermissionHints.ts';
import { RestDataModelRepository } from './persistence/RestDataModelRepository.ts';
import { MediaWikiInstallRecordRepository } from './persistence/MediaWikiInstallRecordRepository.ts';
import type { ActionApi } from './persistence/ActionApi.ts';

export enum ModelBundleService {
	ModelBundlePermissionHints = 'ModelBundlePermissionHints',
	DataModelRepository = 'DataModelRepository',
	InstallRecordRepository = 'InstallRecordRepository'
}

export class ModelBundleServices {

	public static registerServices( app: App ): void {
		Object.entries( ModelBundleServices.getServices() ).forEach( ( [ key, service ] ) => {
			app.provide( key, service );
		} );
	}

	public static getServices(): Record<string, unknown> {
		const neoWiki = NeoWikiExtension.getInstance();
		const mediaWiki = neoWiki.getMediaWiki();
		const actionApi = newActionApi( mediaWiki );

		return {
			[ ModelBundleService.ModelBundlePermissionHints ]: new RightsBasedModelBundlePermissionHints(
				neoWiki.getUserObjectBasedRightsFetcher(),
			),
			[ ModelBundleService.DataModelRepository ]: new RestDataModelRepository(
				mediaWiki.util.wikiScript( 'rest' ),
				neoWiki.newHttpClient(),
				neoWiki.getSchemaRepository(),
				new MediaWikiPageSaver( mediaWiki ),
				actionApi,
			),
			[ ModelBundleService.InstallRecordRepository ]: new MediaWikiInstallRecordRepository( actionApi ),
		};
	}

	public static getModelBundlePermissionHints(): ModelBundlePermissionHints {
		return inject( ModelBundleService.ModelBundlePermissionHints ) as ModelBundlePermissionHints;
	}

	public static getDataModelRepository(): DataModelRepository {
		return inject( ModelBundleService.DataModelRepository ) as DataModelRepository;
	}

	public static getInstallRecordRepository(): InstallRecordRepository {
		return inject( ModelBundleService.InstallRecordRepository ) as InstallRecordRepository;
	}

}

function newActionApi( mediaWiki: typeof mw ): ActionApi {
	const api = new mediaWiki.Api();

	return {
		get: ( parameters ) => api.get( parameters ),
		post: ( parameters ) => api.post( parameters ),
		postWithEditToken: ( parameters ) => api.postWithEditToken( parameters ),
	};
}
