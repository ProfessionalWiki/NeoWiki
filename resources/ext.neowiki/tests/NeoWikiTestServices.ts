import { NeoWikiServices, Service } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';

export class NeoWikiTestServices extends NeoWikiServices {

	public static getServices(): Record<string, unknown> {
		const neoWiki = NeoWikiExtension.getInstance();

		return {
			[ Service.ComponentRegistry ]: neoWiki.getTypeSpecificComponentRegistry(),
			[ Service.SchemaAuthorizer ]: neoWiki.newSchemaAuthorizer(),
			[ Service.SubjectAuthorizer ]: neoWiki.newSubjectAuthorizer(),
			[ Service.SubjectValidator ]: neoWiki.newSubjectValidator(),
			[ Service.PropertyTypeRegistry ]: neoWiki.getPropertyTypeRegistry(),
			[ Service.SchemaRepository ]: new InMemorySchemaRepository( [] ),
		};
	}

}
