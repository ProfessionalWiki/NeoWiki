/**
 * A message shown to the user before they edit a Subject.
 *
 * The html is rendered by whoever supplied the notice: parsed wikitext for the notices wiki admins
 * write, or an extension's own markup.
 */
export interface EditNotice {
	key: string;
	html: string;
}
