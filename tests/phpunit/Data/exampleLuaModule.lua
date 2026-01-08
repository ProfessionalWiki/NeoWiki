local p = {}
local neo = require('NeoWiki')

p.neo = neo

function p.forSubject(frame)
	local subject = neo.getSubject(frame.args[1])

	if subject == nil then
		return 'Subject not found'
	end

	local name = subject.label
	local companyType = table.concat(subject.types, ',')
	local founded = table.concat(subject.properties['Founded at'], '<br>')
	local website = table.concat(subject.properties['Website'], '<br>')

	-- Build the infobox
	local infobox = mw.html.create('table')
	infobox:addClass('wikitable infobox')
		   :css('width', '22em')

	-- Add the company name
	local titleRow = infobox:tag('tr')
	titleRow:tag('th')
			:attr('colspan', 2)
			:css('font-size', '115%')
			:wikitext(name)

	-- Add the company type
	local typeRow = infobox:tag('tr')
	typeRow:tag('th')
		   :css('width', '30%')
		   :wikitext('Type')
	typeRow:tag('td'):wikitext(companyType)

	-- Add the year founded
	local foundedRow = infobox:tag('tr')
	foundedRow:tag('th')
			  :wikitext('Founded')
	foundedRow:tag('td'):wikitext(founded)

	-- Add the company website
	local websiteRow = infobox:tag('tr')
	websiteRow:tag('th')
			  :wikitext('Website')
	websiteRow:tag('td'):wikitext(website)

	return tostring(infobox)
end

return p
