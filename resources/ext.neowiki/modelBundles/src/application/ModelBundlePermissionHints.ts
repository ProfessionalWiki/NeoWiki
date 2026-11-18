/**
 * Hints for showing or hiding affordances. They are advisory, not a security control: the wiki authorizes
 * every write, the install record included.
 */
export interface ModelBundlePermissionHints {

	canInstallModelBundles(): Promise<boolean>;

}
