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
		rows[#rows + 1] = '| ' .. name .. ' || ' .. stmt.type .. ' || ' .. table.concat( vals, ', ' )
	end

	rows[#rows + 1] = '|}'
	return table.concat( rows, '\n' )
end

function p.children( frame )
	local page = frame.args[1]
	local children = nw.getChildSubjects( page )

	if not children or #children == 0 then
		return 'No child subjects'
	end

	local parts = {}
	for _, child in ipairs( children ) do
		parts[#parts + 1] = "'''" .. child.label .. "''' (" .. child.schema .. ")"
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

function p.query( frame )
	local columns = nil
	if frame.args.columns then
		columns = mw.text.split( frame.args.columns, ',%s*' )
	end

	local linkColumns = nil
	if frame.args.linkColumns then
		linkColumns = mw.text.split( frame.args.linkColumns, ',%s*' )
	end

	return renderRowsAsTable( nw.query( frame.args[1] ), columns, linkColumns )
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

-- Renders a wikitable from the current page's child Subjects.
-- Args: columns=Col1, Col2 (required, in order)
--       schema=SchemaName (optional, filters children to one schema)
--       sortBy=ColName (optional)
--       sortDir=asc|desc (optional, default desc)
--       numberColumns=Col1, Col2 (optional, formatted with thousand separators)
function p.childTable( frame )
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

	local children = nw.getChildSubjects()
	if not children then
		return ''
	end

	local lang = mw.getContentLanguage()
	local rows = {}

	for _, child in ipairs( children ) do
		if not schemaFilter or child.schema == schemaFilter then
			local row = {}
			for _, col in ipairs( columns ) do
				local v = statementValue( child.statements[col] )
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
				row.__sortValue = statementValue( child.statements[sortBy] )
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

function p.productsFoundedSince( frame )
	local year = tonumber( frame.args[1] ) or 2000

	return renderRowsAsTable( nw.query(
		'MATCH (n:Product) WHERE n.`Available since` >= $year ' ..
			'RETURN n.name AS name, n.`Available since` AS year ORDER BY year',
		{ year = year }
	) )
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
		details[#details + 1] = 'targetSchema: ' .. prop.targetSchema
		details[#details + 1] = 'relation: ' .. prop.relation
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
-- the graph: the canonical edges run Birth -> Person, so a person's role in each
-- event ("was born" / CIDOC P98i, mother, father) is derived here rather than
-- stored as an inverse edge. The `date` property is stored as a native Neo4j
-- date list; the result normalizer renders b.Date[0] as a Y-m-d string.
function p.personEvents( frame )
	local name = mw.title.getCurrentTitle().text

	local events = nw.query(
		'MATCH (b:Birth)-[r]->(p:Person {name: $name}) ' ..
		'OPTIONAL MATCH (b)-[:`Took place at`]->(pl:Place) ' ..
		'RETURN type(r) AS role, b.name AS event, b.Date[0] AS date, pl.name AS place ORDER BY date',
		{ name = name }
	)

	if not events or #events == 0 then
		return ''
	end

	local rows = {}
	for _, e in ipairs( events ) do
		rows[#rows + 1] = {
			Role = PERSON_EVENTS_ROLES[e.role] or e.role,
			Event = e.event,
			Date = e.date and personEventsFormatDate( e.date ) or '',
			Place = e.place or '',
		}
	end

	return "'''Life events'''\n" .. renderRowsAsTable( rows, { 'Role', 'Event', 'Date', 'Place' }, { 'Event', 'Place' } )
end

return p
