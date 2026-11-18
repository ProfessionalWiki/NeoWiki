import { isUrlSource, type InstallRecord } from '../application/InstallRecord';
import { sameBundleName } from '../application/bundleName';
import { parseBundleFile } from './bundleFile';
import { fetchBundleFile } from './fetchBundleFile';

/**
 * The version of the bundle at the URL it was installed from, when that is another than the one installed. Null
 * for a source that is no URL, cannot be reached, holds another bundle, or gives no version.
 */
export async function otherVersionAtSource( record: InstallRecord ): Promise<string | null> {
	if ( !isUrlSource( record.source ) ) {
		return null;
	}

	try {
		const metadata = parseBundleFile( await fetchBundleFile( record.source ) ).metadata;

		return metadata !== null && sameBundleName( metadata.name, record.name ) && metadata.version !== '' &&
			metadata.version !== record.version ? metadata.version : null;
	} catch ( _error ) {
		return null;
	}
}
