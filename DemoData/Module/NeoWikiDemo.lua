local p = {}
local nw = require( 'mw.neowiki' )

function p.value( frame )
	local property = frame.args[1]
	local page = frame.args['page']

	local options = nil
	if page then
		options = { page = page }
	end

	local v = nw.getValue( property, options )

	return tostring( v or '' )
end

function p.values( frame )
	local property = frame.args[1]
	local page = frame.args['page']
	local separator = frame.args['separator'] or ', '

	local options = nil
	if page then
		options = { page = page }
	end

	local all = nw.getAll( property, options )

	if not all then
		return ''
	end

	local parts = {}
	for _, item in ipairs( all ) do
		parts[#parts + 1] = tostring( item )
	end

	return table.concat( parts, separator )
end

function p.subject( frame )
	local page = frame.args[1]
	local s = nw.getMainSubject( page )

	if not s then
		return 'No subject found'
	end

	local rows = {}
	rows[#rows + 1] = '{| class="wikitable"'
	rows[#rows + 1] = '! Property !! Type !! Value(s)'

	for name, stmt in pairs( s.statements ) do
		local vals = {}
		for _, v in ipairs( stmt.values ) do
			if type( v ) == 'table' then
				vals[#vals + 1] = v.label or v.target or tostring( v )
			else
				vals[#vals + 1] = tostring( v )
			end
		end
		rows[#rows + 1] = '|-'
		rows[#rows + 1] = '| ' .. name .. ' || ' .. stmt.propertyType .. ' || ' .. table.concat( vals, ', ' )
	end

	rows[#rows + 1] = '|}'
	return table.concat( rows, '\n' )
end

function p.subjects( frame )
	local subjects = nw.getSubjects( frame.args[1] )

	if #subjects == 0 then
		return 'No Subjects'
	end

	local parts = {}
	for _, subject in ipairs( subjects ) do
		local name = "'''" .. subject.label .. "''' (" .. subject.schema .. ")"
		if subject.isMainSubject then
			name = name .. ' — main'
		end
		parts[#parts + 1] = name
	end

	return table.concat( parts, ', ' )
end

local function renderRowsAsTable( rows, columns, linkColumns )
	if #rows == 0 then
		return 'No results'
	end

	if not columns then
		columns = {}
		for k in pairs( rows[1] ) do
			columns[#columns + 1] = k
		end
		table.sort( columns )
	end

	local linkSet = {}
	if linkColumns then
		for _, col in ipairs( linkColumns ) do
			linkSet[col] = true
		end
	end

	local out = { '{| class="wikitable"', '! ' .. table.concat( columns, ' !! ' ) }

	for _, row in ipairs( rows ) do
		local cells = {}
		for _, col in ipairs( columns ) do
			local v = row[col]
			local cell = v == nil and '' or tostring( v )
			if linkSet[col] and cell ~= '' then
				cell = '[[' .. cell .. ']]'
			end
			cells[#cells + 1] = cell
		end
		out[#out + 1] = '|-'
		out[#out + 1] = '| ' .. table.concat( cells, ' || ' )
	end

	out[#out + 1] = '|}'
	return table.concat( out, '\n' )
end

-- The prefixes of NeoWiki's native RDF vocabulary, under this wiki's IRIs.
local function sparqlPrefixes()
	local base = mw.site.server
	return 'PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#> ' ..
		'PREFIX neo-subj: <' .. base .. '/entity/> ' ..
		'PREFIX neo-prop: <' .. base .. '/prop/> ' ..
		'PREFIX neo-schema: <' .. base .. '/schema/> '
end

-- SPARQL variables and NeoWiki's property IRIs write a space as an underscore.
local function withSpaces( name )
	return ( name:gsub( '_', ' ' ) )
end

-- Renders a SPARQL query's results as a wikitable, one column per selected variable, in the order
-- the query selects them, with an underscore in a variable's name shown as a space. The query may use
-- the rdfs:, neo-subj:, neo-prop: and neo-schema: prefixes without declaring them.
-- Args: 1 = the query
--       linkColumns=Col1, Col2 (optional, wraps those columns' cells in [[...]])
function p.query( frame )
	if not nw.sparqlQuery then
		return "''This table needs a SPARQL store.''"
	end

	local linkColumns = nil
	if frame.args.linkColumns then
		linkColumns = mw.text.split( frame.args.linkColumns, ',%s*' )
	end

	local result = nw.sparqlQuery( sparqlPrefixes() .. frame.args[1] )

	local columns = {}
	for _, variable in ipairs( result.head.vars ) do
		columns[#columns + 1] = withSpaces( variable )
	end

	local rows = {}
	for _, binding in ipairs( result.results.bindings ) do
		local row = {}
		for variable, term in pairs( binding ) do
			row[withSpaces( variable )] = term.value
		end
		rows[#rows + 1] = row
	end

	return renderRowsAsTable( rows, columns, linkColumns )
end

local function statementValue( stmt )
	if not stmt or not stmt.values or stmt.values[1] == nil then
		return nil
	end
	local v = stmt.values[1]
	if type( v ) == 'table' then
		return v.label or v.target
	end
	return v
end

-- Renders a wikitable from the current page's Subjects.
-- Args: columns=Col1, Col2 (required, in order)
--       schema=SchemaName (optional, filters the Subjects to one schema)
--       sortBy=ColName (optional)
--       sortDir=asc|desc (optional, default desc)
--       numberColumns=Col1, Col2 (optional, formatted with thousand separators)
function p.subjectTable( frame )
	local columns = mw.text.split( frame.args.columns or '', ',%s*' )
	local schemaFilter = frame.args.schema
	local sortBy = frame.args.sortBy
	local sortDir = frame.args.sortDir or 'desc'

	local numberSet = {}
	if frame.args.numberColumns then
		for _, col in ipairs( mw.text.split( frame.args.numberColumns, ',%s*' ) ) do
			numberSet[col] = true
		end
	end

	local lang = mw.getContentLanguage()
	local rows = {}

	for _, subject in ipairs( nw.getSubjects() ) do
		if not schemaFilter or subject.schema == schemaFilter then
			local row = {}
			for _, col in ipairs( columns ) do
				local v = statementValue( subject.statements[col] )
				if v == nil then
					row[col] = ''
				elseif numberSet[col] and tonumber( v ) then
					row[col] = lang:formatNum( tonumber( v ) )
				else
					row[col] = tostring( v )
				end
			end
			-- Stash the raw sort value so number columns sort numerically
			-- even after thousand-separator formatting has stringified them.
			if sortBy then
				row.__sortValue = statementValue( subject.statements[sortBy] )
			end
			rows[#rows + 1] = row
		end
	end

	if sortBy then
		table.sort( rows, function( a, b )
			local av, bv = a.__sortValue, b.__sortValue
			local an, bn = tonumber( av ), tonumber( bv )
			if an and bn then
				if sortDir == 'asc' then return an < bn end
				return an > bn
			end
			if sortDir == 'asc' then return tostring( av ) < tostring( bv ) end
			return tostring( av ) > tostring( bv )
		end )
	end

	return renderRowsAsTable( rows, columns, nil )
end

-- A Schema of this wiki arrives as its name, one from another Source as a { source, name } table.
local function schemaReferenceText( reference )
	if type( reference ) == 'table' then
		return reference.source .. ':' .. reference.name
	end
	return reference
end

local function propertyDetails( prop )
	local details = {}

	if prop.type == 'select' then
		local labels = {}
		for _, option in ipairs( prop.options ) do
			labels[#labels + 1] = option.label
		end
		details[#details + 1] = 'options: ' .. table.concat( labels, ', ' )
	elseif prop.type == 'number' then
		if prop.minimum ~= nil then
			details[#details + 1] = 'min: ' .. tostring( prop.minimum )
		end
		if prop.maximum ~= nil then
			details[#details + 1] = 'max: ' .. tostring( prop.maximum )
		end
		if prop.precision ~= nil then
			details[#details + 1] = 'precision: ' .. tostring( prop.precision )
		end
	elseif prop.type == 'relation' then
		local targetSchemas = {}
		for _, reference in ipairs( prop.targetSchemas ) do
			targetSchemas[#targetSchemas + 1] = schemaReferenceText( reference )
		end
		details[#details + 1] = 'targetSchemas: ' .. table.concat( targetSchemas, ', ' )
	elseif prop.type == 'text' or prop.type == 'url' then
		if prop.multiple then
			details[#details + 1] = 'multiple: true'
		end
		if prop.uniqueItems then
			details[#details + 1] = 'uniqueItems: true'
		end
	end

	return table.concat( details, ', ' )
end

function p.schema( frame )
	local schema = nw.getSchema( frame.args[1] )

	if not schema then
		return 'Schema not found'
	end

	local rows = {}
	for _, prop in ipairs( schema.properties ) do
		rows[#rows + 1] = {
			Name = prop.name,
			Type = prop.type,
			Required = prop.required and 'Yes' or 'No',
			Details = propertyDetails( prop ),
		}
	end

	return renderRowsAsTable( rows, { 'Name', 'Type', 'Required', 'Details' } )
end

local PERSON_EVENTS_MONTHS = {
	'January', 'February', 'March', 'April', 'May', 'June',
	'July', 'August', 'September', 'October', 'November', 'December'
}

-- Formats an ISO yyyy-mm-dd string as "21 March 1685" without relying on
-- MediaWiki's date parsing, which is unreliable for pre-modern years.
local function personEventsFormatDate( iso )
	local y, m, d = string.match( iso or '', '^(%d+)-(%d+)-(%d+)$' )
	if not y then
		return iso or ''
	end
	return tonumber( d ) .. ' ' .. ( PERSON_EVENTS_MONTHS[tonumber( m )] or m ) .. ' ' .. y
end

local PERSON_EVENTS_ROLES = {
	['Brought into life'] = 'Born',
	['By mother'] = 'Mother',
	['From father'] = 'Father',
}

-- Renders the current Person page's life events as a table by reverse-querying
-- the graph: the canonical relations run Birth -> Person, so a person's role in
-- each event ("was born" / CIDOC P98i, mother, father) is derived here rather
-- than stored as an inverse relation. Dates come back as xsd:date, Y-m-d.
function p.personEvents( frame )
	local person = nw.getMainSubject()

	if not person or not nw.sparqlQuery then
		return ''
	end

	local result = nw.sparqlQuery( sparqlPrefixes() ..
		'SELECT DISTINCT ?role ?event ?date ?place WHERE { ' ..
		'?b a neo-schema:Birth ; ?role neo-subj:' .. person.id .. ' ; rdfs:label ?event . ' ..
		'OPTIONAL { ?b neo-prop:Date ?date } ' ..
		'OPTIONAL { ?b neo-prop:Took_place_at ?pl . ?pl rdfs:label ?place } ' ..
		'} ORDER BY ?date'
	)

	local events = result.results.bindings

	if #events == 0 then
		return ''
	end

	local rows = {}
	for _, e in ipairs( events ) do
		local property = withSpaces( e.role.value:match( '/prop/(.+)$' ) )
		rows[#rows + 1] = {
			Role = PERSON_EVENTS_ROLES[property] or property,
			Event = e.event.value,
			Date = e.date and personEventsFormatDate( e.date.value ) or '',
			Place = e.place and e.place.value or '',
		}
	end

	return "'''Life events'''\n" .. renderRowsAsTable( rows, { 'Role', 'Event', 'Date', 'Place' }, { 'Event', 'Place' } )
end

return p
