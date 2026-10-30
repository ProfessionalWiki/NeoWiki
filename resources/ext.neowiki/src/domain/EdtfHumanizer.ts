export interface EdtfHumanizer {

	humanize( value: string, language: string ): Promise<string | null>;

}
