/**
 * Import this file ONLY in specs that import from @/neowiki directly.
 * It suppresses the module-level side-effect initializers that run on page load.
 * Must be imported before any import of @/neowiki.
 */
( window as unknown as { neoWikiTestMode: boolean } ).neoWikiTestMode = true;
