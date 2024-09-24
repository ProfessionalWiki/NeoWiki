import { ValueFormatComponentRegistry } from '@/presentation/ValueFormatComponentRegistry';
import { TextFormatComponent } from '@/presentation/valueFormats/TextFormatComponent';
import { NumberFormatComponent } from '@/presentation/valueFormats/NumberFormatComponent';
import { RelationFormatComponent } from '@/presentation/valueFormats/RelationFormatComponent';
import { UrlFormatComponent } from '@/presentation/valueFormats/UrlFormatComponent';

export class NeoWikiExtension {
	private static instance: NeoWikiExtension;

	public static getInstance(): NeoWikiExtension {
		NeoWikiExtension.instance ??= new NeoWikiExtension();
		return NeoWikiExtension.instance;
	}

	public getValueFormatComponentRegistry(): ValueFormatComponentRegistry {
		const registry = new ValueFormatComponentRegistry();

		registry.registerComponent( new TextFormatComponent() );
		registry.registerComponent( new NumberFormatComponent() );
		registry.registerComponent( new RelationFormatComponent() );
		registry.registerComponent( new UrlFormatComponent() );

		return registry;
	}
}
