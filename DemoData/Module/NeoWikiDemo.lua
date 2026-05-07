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

return p
