local testframework = require 'Module:TestFramework'
local nw = require( 'mw.neowiki' )

local page = 'NeoWikiLuaTestPage'
local pageWithChildren = 'NeoWikiLuaTestPageChildren'

-- getValue tests

local function testGetValueString()
	return nw.getValue( 'City', { page = page } )
end

local function testGetValueFirstOfMultiString()
	return nw.getValue( 'Tags', { page = page } )
end

local function testGetValueNumber()
	return nw.getValue( 'Founded', { page = page } )
end

local function testGetValueMissingProperty()
	return nw.getValue( 'Nonexistent', { page = page } )
end

local function testGetValueNoSubjectOnPage()
	return nw.getValue( 'City', { page = 'NonexistentPage12345' } )
end

local function testGetValueEmptyPropertyName()
	return nw.getValue( '', { page = page } )
end

-- getAll tests

local function testGetAllSingleString()
	return nw.getAll( 'City', { page = page } )
end

local function testGetAllMultiString()
	return nw.getAll( 'Tags', { page = page } )
end

local function testGetAllNumber()
	return nw.getAll( 'Founded', { page = page } )
end

local function testGetAllMissingProperty()
	return nw.getAll( 'Nonexistent', { page = page } )
end

-- getMainSubject tests

local function testGetMainSubjectReturnsTable()
	local s = nw.getMainSubject( page )
	if not s then return 'nil' end
	return s.id, s.label, s.schema
end

local function testGetMainSubjectStatements()
	local s = nw.getMainSubject( page )
	if not s then return 'nil' end
	return s.statements['City'].type, s.statements['City'].values[1]
end

local function testGetMainSubjectNonexistentPage()
	return nw.getMainSubject( 'NonexistentPage12345' )
end

-- getChildSubjects tests

local function testGetChildSubjectsReturnsChildren()
	local children = nw.getChildSubjects( pageWithChildren )
	if not children or #children == 0 then return 0 end
	return #children
end

local function testGetChildSubjectsHasLabels()
	local children = nw.getChildSubjects( pageWithChildren )
	if not children or #children == 0 then return 'none' end
	return children[1].label, children[1].schema
end

local function testGetChildSubjectsEmptyForPageWithoutChildren()
	local children = nw.getChildSubjects( page )
	return #children
end

-- query tests

local function testQueryRejectsEmptyString()
	local ok, err = pcall( function()
		return nw.query( '' )
	end )
	if ok then
		return 'unexpected success'
	end
	return type( err ) == 'string' and string.find( err, 'must not be empty', 1, true ) ~= nil
end

local function testQueryRejectsWriteQuery()
	local ok, err = pcall( function()
		return nw.query( 'CREATE (n:Foo) RETURN n' )
	end )
	if ok then
		return 'unexpected success'
	end
	return type( err ) == 'string' and string.find( err, 'read-only Cypher queries are allowed', 1, true ) ~= nil
end

-- sparqlQuery tests

local function testSparqlQueryRejectsEmptyString()
	local ok, err = pcall( function()
		return nw.sparqlQuery( '' )
	end )
	if ok then
		return 'unexpected success'
	end
	return type( err ) == 'string' and string.find( err, 'must not be empty', 1, true ) ~= nil
end

-- getSchema tests

local function testGetSchemaReturnsNameAndPropertyCount()
	local s = nw.getSchema( 'Employee' )
	if not s then return 'nil' end
	return s.name, #s.properties
end

local function testGetSchemaPropertyShape()
	local s = nw.getSchema( 'Employee' )
	if not s then return 'nil' end
	local p = s.properties[1]
	return p.name, p.type, p.required
end

local function testGetSchemaSelectOptionsAreOneIndexed()
	local s = nw.getSchema( 'Employee' )
	if not s then return 'nil' end
	for _, p in ipairs( s.properties ) do
		if p.name == 'Status' then
			return p.options[1].label, p.options[3].label
		end
	end
	return 'not-found'
end

local function testGetSchemaReturnsNilForMissingSchema()
	return nw.getSchema( 'NopeDoesNotExist' )
end

local function testGetSchemaReturnsNilForEmptyString()
	return nw.getSchema( '' )
end

local function testGetSchemaReturnsNilForReservedName()
	return nw.getSchema( 'page' )
end

local function testGetSchemaNumberPropertyBoundsAndDefault()
	local s = nw.getSchema( 'Employee' )
	if not s then return 'nil' end
	for _, p in ipairs( s.properties ) do
		if p.name == 'EmploymentFte' then
			return p.type, p.minimum, p.maximum, p.default
		end
	end
	return 'not-found'
end

local function testGetSchemaRelationPropertyTargetSchema()
	local s = nw.getSchema( 'Employee' )
	if not s then return 'nil' end
	for _, p in ipairs( s.properties ) do
		if p.name == 'Employer' then
			return p.type, p.relation, p.targetSchema
		end
	end
	return 'not-found'
end

local tests = {
	-- getValue
	{ name = 'getValue returns string value',
	  func = testGetValueString, expect = { 'Berlin' } },
	{ name = 'getValue returns first value for multi-string',
	  func = testGetValueFirstOfMultiString, expect = { 'alpha' } },
	{ name = 'getValue returns number value',
	  func = testGetValueNumber, expect = { 2019 } },
	{ name = 'getValue returns nil for missing property',
	  func = testGetValueMissingProperty, expect = { nil } },
	{ name = 'getValue returns nil for nonexistent page',
	  func = testGetValueNoSubjectOnPage, expect = { nil } },
	{ name = 'getValue returns nil for empty property name',
	  func = testGetValueEmptyPropertyName, expect = { nil } },

	-- getAll
	{ name = 'getAll wraps single string in table',
	  func = testGetAllSingleString, expect = { { 'Berlin' } } },
	{ name = 'getAll returns all multi-string values',
	  func = testGetAllMultiString, expect = { { 'alpha', 'beta', 'gamma' } } },
	{ name = 'getAll wraps number in table',
	  func = testGetAllNumber, expect = { { 2019 } } },
	{ name = 'getAll returns nil for missing property',
	  func = testGetAllMissingProperty, expect = { nil } },

	-- getMainSubject
	{ name = 'getMainSubject returns id, label, schema',
	  func = testGetMainSubjectReturnsTable, expect = { 's1test5aaaaaaaa', 'Test Company', 'Company' } },
	{ name = 'getMainSubject includes statement type and values',
	  func = testGetMainSubjectStatements, expect = { 'text', 'Berlin' } },
	{ name = 'getMainSubject returns nil for nonexistent page',
	  func = testGetMainSubjectNonexistentPage, expect = { nil } },

	-- getChildSubjects
	{ name = 'getChildSubjects returns correct count',
	  func = testGetChildSubjectsReturnsChildren, expect = { 1 } },
	{ name = 'getChildSubjects includes label and schema',
	  func = testGetChildSubjectsHasLabels, expect = { 'Child Entry', 'Entry' } },
	{ name = 'getChildSubjects returns empty for page without children',
	  func = testGetChildSubjectsEmptyForPageWithoutChildren, expect = { 0 } },

	-- query
	{ name = 'query rejects empty string with localized message',
	  func = testQueryRejectsEmptyString, expect = { true } },
	{ name = 'query rejects write query with localized message',
	  func = testQueryRejectsWriteQuery, expect = { true } },

	-- sparqlQuery
	{ name = 'sparqlQuery rejects empty string with localized message',
	  func = testSparqlQueryRejectsEmptyString, expect = { true } },

	-- getSchema
	{ name = 'getSchema returns name and property count',
	  func = testGetSchemaReturnsNameAndPropertyCount, expect = { 'Employee', 4 } },
	{ name = 'getSchema first property shape',
	  func = testGetSchemaPropertyShape, expect = { 'LegalName', 'text', true } },
	{ name = 'getSchema select options are one-indexed',
	  func = testGetSchemaSelectOptionsAreOneIndexed, expect = { 'Active', 'On leave' } },
	{ name = 'getSchema returns nil for missing schema',
	  func = testGetSchemaReturnsNilForMissingSchema, expect = { nil } },
	{ name = 'getSchema returns nil for empty string',
	  func = testGetSchemaReturnsNilForEmptyString, expect = { nil } },
	{ name = 'getSchema returns nil for reserved name',
	  func = testGetSchemaReturnsNilForReservedName, expect = { nil } },
	{ name = 'getSchema number property exposes bounds and default',
	  func = testGetSchemaNumberPropertyBoundsAndDefault, expect = { 'number', 0, 100, 100 } },
	{ name = 'getSchema relation property exposes relation and targetSchema',
	  func = testGetSchemaRelationPropertyTargetSchema, expect = { 'relation', 'Works for', 'Company' } },

}

return testframework.getTestProvider( tests )
