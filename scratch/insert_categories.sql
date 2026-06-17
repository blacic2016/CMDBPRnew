INSERT INTO ci_categories (name, schema_json) VALUES ('Hardware CIs', '{}');
SET @hard_id = LAST_INSERT_ID();
INSERT INTO ci_categories (parent_id, name, schema_json) VALUES (@hard_id, 'Servers', '{}'), (@hard_id, 'Routers', '{}');

INSERT INTO ci_categories (name, schema_json) VALUES ('Documentation CIs', '{}');
SET @doc_id = LAST_INSERT_ID();
INSERT INTO ci_categories (parent_id, name, schema_json) VALUES (@doc_id, 'Manuals', '{}'), (@doc_id, 'Policies', '{}');

INSERT INTO ci_categories (name, schema_json) VALUES ('Facility CIs', '{}');
SET @fac_id = LAST_INSERT_ID();
INSERT INTO ci_categories (parent_id, name, schema_json) VALUES (@fac_id, 'Data Centers', '{}'), (@fac_id, 'Server Rooms', '{}');

INSERT INTO ci_categories (name, schema_json) VALUES ('Software CIs', '{}');
SET @soft_id = LAST_INSERT_ID();
INSERT INTO ci_categories (parent_id, name, schema_json) VALUES (@soft_id, 'Operating Systems', '{}'), (@soft_id, 'Databases', '{}');

INSERT INTO ci_categories (name, schema_json) VALUES ('People CIs', '{}');
SET @peo_id = LAST_INSERT_ID();
INSERT INTO ci_categories (parent_id, name, schema_json) VALUES (@peo_id, 'System Administrators', '{}'), (@peo_id, 'Support Teams', '{}');
