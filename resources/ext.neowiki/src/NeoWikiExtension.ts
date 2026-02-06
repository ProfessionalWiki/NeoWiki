import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { RightsFetcher, UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import TextDisplay from '@/components/Value/TextDisplay.vue';
import { UrlType } from '@/domain/propertyTypes/Url.ts';
import UrlDisplay from '@/components/Value/UrlDisplay.vue';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import NumberDisplay from '@/components/Value/NumberDisplay.vue';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry.ts';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { RestSchemaRepository } from '@/persistence/RestSchemaRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import { CsrfSendingHttpClient } from '@/infrastructure/HttpClient/CsrfSendingHttpClient.ts';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { RightsBasedSchemaAuthorizer } from '@/persistence/RightsBasedSchemaAuthorizer.ts';
import { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';
import { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository.ts';
import TextInput from '@/components/Value/TextInput.vue';
import UrlInput from '@/components/Value/UrlInput.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import RelationInput from '@/components/Value/RelationInput.vue';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';
import { SubjectDeserializer } from '@/persistence/SubjectDeserializer.ts';
import { Neo } from '@/Neo.ts';
// import { cdxIconStringInteger } from '@/assets/CustomIcons.ts';
import { cdxIconLink, cdxIconSearchCaseSensitive, cdxIconArticles, cdxIconListNumbered } from '@wikimedia/codex-icons';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import NumberAttributesEditor from '@/components/SchemaEditor/Property/NumberAttributesEditor.vue';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import { SubjectValidator } from '@/domain/SubjectValidator.ts';
import { PropertyTypeRegistry } from '@/domain/PropertyType.ts';
import { StoreStateLoader } from '@/persistence/StoreStateLoader.ts';

export class NeoWikiExtension {
	private static instance: NeoWikiExtension;

	public static getInstance(): NeoWikiExtension {
		NeoWikiExtension.instance ??= new NeoWikiExtension();
		return NeoWikiExtension.instance;
	}

	private rightsFetcher: RightsFetcher|undefined;

	public getTypeSpecificComponentRegistry(): TypeSpecificComponentRegistry {
		const registry = new TypeSpecificComponentRegistry();

		registry.registerType( TextType.typeName, {
			valueDisplayComponent: TextDisplay,
			valueEditor: TextInput,
			attributesEditor: TextAttributesEditor,
			label: 'neowiki-property-type-text',
			icon: cdxIconSearchCaseSensitive,
		} );

		registry.registerType( UrlType.typeName, {
			valueDisplayComponent: UrlDisplay,
			valueEditor: UrlInput,
			attributesEditor: TextAttributesEditor, // TODO
			label: 'neowiki-property-type-url',
			icon: cdxIconLink,
		} );

		registry.registerType( NumberType.typeName, {
			valueDisplayComponent: NumberDisplay,
			valueEditor: NumberInput,
			attributesEditor: NumberAttributesEditor,
			label: 'neowiki-property-type-number',
			icon: cdxIconListNumbered, // TOOD: Add a custom icon
		} );

		registry.registerType( RelationType.typeName, {
			valueDisplayComponent: RelationDisplay,
			valueEditor: RelationInput,
			attributesEditor: RelationAttributesEditor,
			label: 'neowiki-property-type-relation',
			icon: cdxIconArticles,
		} );

		return registry;
	}

	public getMediaWiki(): typeof mw {
		return window.mw;
	}

	public newSubjectAuthorizer(): SubjectAuthorizer {
		return new RightsBasedSubjectAuthorizer(
			this.getUserObjectBasedRightsFetcher(),
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
			new SchemaDeserializer(),
			new MediaWikiPageSaver( this.getMediaWiki() ),
		);
	}

	public newHttpClient(): HttpClient {
		return new CsrfSendingHttpClient(
			new ProductionHttpClient(),
		);
	}

	public newSchemaAuthorizer(): SchemaAuthorizer {
		return new RightsBasedSchemaAuthorizer(
			this.getUserObjectBasedRightsFetcher(),
		);
	}

	public getSubjectRepository(): SubjectRepository {
		return new RestSubjectRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			this.getSubjectDeserializer(),
		);
	}

	public getSubjectDeserializer(): SubjectDeserializer {
		return this.getNeo().getSubjectDeserializer();
	}

	private getNeo(): Neo {
		return Neo.getInstance();
	}

	public newSubjectValidator(): SubjectValidator {
		return new SubjectValidator(
			this.getPropertyTypeRegistry(),
		);
	}

	public getPropertyTypeRegistry(): PropertyTypeRegistry {
		return this.getNeo().getPropertyTypeRegistry();
	}

	public getStoreStateLoader(): StoreStateLoader {
		return new StoreStateLoader(
			this.getSubjectRepository(),
			this.getSchemaRepository(),
		);
	}

}
