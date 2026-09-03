/**
 * The Schema name to show beside a Subject, or null when it would repeat the name already
 * displayed — a Subject with no label of its own is shown under its Schema name (ADR 31).
 *
 * Callers apply this rather than leaving it to the badge, so a surface can drop its own
 * wrapper along with the name; an emptied wrapper carrying a role is worse than a repeat.
 */
export function schemaNameToShow( schemaName: string, displayName: string | null ): string | null {
	return schemaName === displayName ? null : schemaName;
}
