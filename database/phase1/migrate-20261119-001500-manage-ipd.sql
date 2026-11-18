-- hospital: new permission "Wards, admissions, emergency and theatre" for vendors that already saved role grants (idempotent)
INSERT INTO permissions (name, guard_name, created_at, updated_at)
  SELECT 'manage_ipd', 'web', NOW(), NOW() FROM (SELECT 1) x WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.name = 'manage_ipd');
INSERT INTO tenant_role_has_permissions (tenant_id, role_id, permission_id)
  SELECT g.tenant_id, g.role_id, p.id FROM (SELECT DISTINCT t.tenant_id, t.role_id, d.name rname FROM tenant_role_has_permissions t JOIN departments d ON d.id = t.role_id JOIN sc_vendors v ON v.tenant_id = t.tenant_id AND v.type_slug = 'hospital') g
  JOIN permissions p ON p.name = 'manage_ipd'
  WHERE g.rname IN ('Medical Director', 'Branch Manager', 'Consultant', 'Doctor', 'Resident', 'Nurse', 'Head Nurse', 'Midwife', 'Clinical Assistant', 'Case Manager', 'Ward Attendant', 'Receptionist', 'Paramedic')
  AND NOT EXISTS (SELECT 1 FROM tenant_role_has_permissions x WHERE x.tenant_id = g.tenant_id AND x.role_id = g.role_id AND x.permission_id = p.id);
