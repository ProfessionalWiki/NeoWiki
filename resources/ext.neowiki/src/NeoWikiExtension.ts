import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { RightsFetcher, UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher.ts';
import { TextFormat } from '@neo/domain/valueFormats/Text.ts';
import TextValue from '@/components/AutomaticInfobox/Values/TextValue.vue';
import { UrlFormat } from '@neo/domain/valueFormats/Url.ts';
import UrlValue from '@/components/AutomaticInfobox/Values/UrlValue.vue';
import { NumberFormat } from '@neo/domain/valueFormats/Number.ts';
import NumberValue from '@/components/AutomaticInfobox/Values/NumberValue.vue';
import { RelationFormat } from '@neo/domain/valueFormats/Relation.ts';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';
import RelationValue from '@/components/AutomaticInfobox/Values/RelationValue.vue';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { RestSchemaRepository } from '@/persistence/RestSchemaRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import { CsrfSendingHttpClient } from '@/infrastructure/HttpClient/CsrfSendingHttpClient.ts';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { RightsBasedSchemaAuthorizer } from '@/persistence/RightsBasedSchemaAuthorizer.ts';
import { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';
import { SubjectRepository } from '@neo/domain/SubjectRepository.ts';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository.ts';
import TextInput from '@/components/Value/TextInput.vue';
import UrlInput from '@/components/Value/UrlInput.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';
import { SubjectDeserializer } from '@neo/persistence/SubjectDeserializer.ts';
import { Neo } from '@neo/Neo.ts';

export class NeoWikiExtension {
	private static instance: NeoWikiExtension;

	public static getInstance(): NeoWikiExtension {
		NeoWikiExtension.instance ??= new NeoWikiExtension();
		return NeoWikiExtension.instance;
	}

	private rightsFetcher: RightsFetcher|undefined;

	public getFormatSpecificComponentRegistry(): FormatSpecificComponentRegistry {
		const registry = new FormatSpecificComponentRegistry();

		registry.registerComponents( TextFormat.formatName, TextValue, TextInput );
		registry.registerComponents( UrlFormat.formatName, UrlValue, UrlInput );
		registry.registerComponents( NumberFormat.formatName, NumberValue, NumberInput );
		registry.registerComponents( RelationFormat.formatName, RelationValue, TextInput ); // TODO

		return registry;
	}

	public getMediaWiki(): typeof mw {
		return window.mw;
	}

	public newSubjectAuthorizer(): SubjectAuthorizer {
		return new RightsBasedSubjectAuthorizer(
			this.getUserObjectBasedRightsFetcher()
		);
	}

	public getUserObjectBasedRightsFetcher(): RightsFetcher {
		if ( this.rightsFetcher === undefined ) {
			this.rightsFetcher = new UserObjectBasedRightsFetcher();
		}
		return this.rightsFetcher;
	}

	public getSchemaRepository(): SchemaRepository {
		return new RestSchemaRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			new SchemaSerializer(),
			new MediaWikiPageSaver( this.getMediaWiki() )
		);
	}

	private newHttpClient(): HttpClient {
		return new CsrfSendingHttpClient(
			new ProductionHttpClient()
		);
	}

	public newSchemaAuthorizer(): SchemaAuthorizer {
		return new RightsBasedSchemaAuthorizer(
			this.getUserObjectBasedRightsFetcher()
		);
	}

	public getSubjectRepository(): SubjectRepository {
		return new RestSubjectRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			this.getSubjectDeserializer()
		);
	}

	public getSubjectDeserializer(): SubjectDeserializer {
		return this.getNeo().getSubjectDeserializer();
	}

	private getNeo(): Neo {
		return Neo.getInstance();
	}
}
