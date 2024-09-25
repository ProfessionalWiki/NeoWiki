import { ValueFormatComponentRegistry } from '@/presentation/ValueFormatComponentRegistry';
import { TextFormatComponent } from '@/presentation/valueFormats/TextFormatComponent';
import { NumberFormatComponent } from '@/presentation/valueFormats/NumberFormatComponent';
import { RelationFormatComponent } from '@/presentation/valueFormats/RelationFormatComponent';
import { UrlFormatComponent } from '@/presentation/valueFormats/UrlFormatComponent';
import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { RightsFetcher, UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher.ts';

export class NeoWikiExtension {
	private static instance: NeoWikiExtension;

	public static getInstance(): NeoWikiExtension {
		NeoWikiExtension.instance ??= new NeoWikiExtension();
		return NeoWikiExtension.instance;
	}

	private rightsFetcher: RightsFetcher|undefined;

	public getValueFormatComponentRegistry(): ValueFormatComponentRegistry {
		const registry = new ValueFormatComponentRegistry();

		registry.registerComponent( new TextFormatComponent() );
		registry.registerComponent( new NumberFormatComponent() );
		registry.registerComponent( new RelationFormatComponent() );
		registry.registerComponent( new UrlFormatComponent() );

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
}
