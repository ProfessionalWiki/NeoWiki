/**
 * A BCP-47-shaped language tag: hyphen-separated subtags of 1-8 characters, the primary subtag
 * alphabetic and the rest alphanumeric. The backend applies the same rule, so a tag this accepts
 * is a tag it stores.
 */
// Each repetition has to start with a hyphen, so there is nothing ambiguous to backtrack over.
// eslint-disable-next-line security/detect-unsafe-regex
const LANGUAGE_TAG_PATTERN = /^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$/;

export function isValidLanguageTag( tag: string ): boolean {
	return LANGUAGE_TAG_PATTERN.test( tag );
}
