INSERT INTO `mc_rbac_menu` (`id`, `parent_id`, `name`, `level`, `path`, `icon`, `status`, `link_type`, `is_page_menu`, `link_url`, `data_permission`, `operate_id`, `operate_name`, `sort`, `created_at`, `updated_at`, `deleted_at`)
VALUES (584, 268, '离职继承分配群聊接口', 4, '#1#-#27#-#268#-#584#', '', 1, 1, 2, '/dashboard/contactTransfer/room#post', 1, 0, '系统', 99, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `level` = VALUES(`level`),
    `path` = VALUES(`path`),
    `status` = VALUES(`status`),
    `link_type` = VALUES(`link_type`),
    `is_page_menu` = VALUES(`is_page_menu`),
    `link_url` = VALUES(`link_url`),
    `data_permission` = VALUES(`data_permission`),
    `updated_at` = NOW(),
    `deleted_at` = NULL;

INSERT INTO `mc_rbac_menu` (`id`, `parent_id`, `name`, `level`, `path`, `icon`, `status`, `link_type`, `is_page_menu`, `link_url`, `data_permission`, `operate_id`, `operate_name`, `sort`, `created_at`, `updated_at`, `deleted_at`)
VALUES (585, 99, '用户状态修改', 4, '#94#-#95#-#99#-#585#', '', 1, 1, 2, '/dashboard/user/statusUpdate#put', 1, 0, '系统', 99, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `level` = VALUES(`level`),
    `path` = VALUES(`path`),
    `status` = VALUES(`status`),
    `link_type` = VALUES(`link_type`),
    `is_page_menu` = VALUES(`is_page_menu`),
    `link_url` = VALUES(`link_url`),
    `data_permission` = VALUES(`data_permission`),
    `updated_at` = NOW(),
    `deleted_at` = NULL;

INSERT INTO `mc_rbac_menu` (`id`, `parent_id`, `name`, `level`, `path`, `icon`, `status`, `link_type`, `is_page_menu`, `link_url`, `data_permission`, `operate_id`, `operate_name`, `sort`, `created_at`, `updated_at`, `deleted_at`)
VALUES (586, 29, '批量打标签操作', 5, '#1#-#27#-#28#-#29#-#586#', '', 1, 1, 2, '/dashboard/workContact/batchLabeling#post', 1, 0, '系统', 99, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `level` = VALUES(`level`),
    `path` = VALUES(`path`),
    `status` = VALUES(`status`),
    `link_type` = VALUES(`link_type`),
    `is_page_menu` = VALUES(`is_page_menu`),
    `link_url` = VALUES(`link_url`),
    `data_permission` = VALUES(`data_permission`),
    `updated_at` = NOW(),
    `deleted_at` = NULL;

INSERT INTO `mc_rbac_menu` (`id`, `parent_id`, `name`, `level`, `path`, `icon`, `status`, `link_type`, `is_page_menu`, `link_url`, `data_permission`, `operate_id`, `operate_name`, `sort`, `created_at`, `updated_at`, `deleted_at`)
VALUES
    (587, 45, '新建标签操作', 4, '#1#-#27#-#45#-#587#', '', 1, 1, 2, '/dashboard/workContactTag/store#post', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (588, 45, '标签详情操作', 4, '#1#-#27#-#45#-#588#', '', 1, 1, 2, '/dashboard/workContactTag/detail#get', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (589, 45, '移动标签操作', 4, '#1#-#27#-#45#-#589#', '', 1, 1, 2, '/dashboard/workContactTag/move#put', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (590, 45, '编辑标签操作', 4, '#1#-#27#-#45#-#590#', '', 1, 1, 2, '/dashboard/workContactTag/update#put', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (591, 45, '新建标签分组操作', 4, '#1#-#27#-#45#-#591#', '', 1, 1, 2, '/dashboard/workContactTagGroup/store#post', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (592, 45, '编辑标签分组操作', 4, '#1#-#27#-#45#-#592#', '', 1, 1, 2, '/dashboard/workContactTagGroup/update#put', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (593, 45, '删除标签分组操作', 4, '#1#-#27#-#45#-#593#', '', 1, 1, 2, '/dashboard/workContactTagGroup/destroy#delete', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (594, 45, '标签分组详情操作', 4, '#1#-#27#-#45#-#594#', '', 1, 1, 2, '/dashboard/workContactTagGroup/detail#get', 2, 0, '系统', 99, NOW(), NOW(), NULL),
    (595, 124, '部门成员列表操作', 4, '#94#-#95#-#124#-#595#', '', 1, 1, 2, '/dashboard/workEmployeeDepartment/memberIndex#get', 1, 0, '系统', 99, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `level` = VALUES(`level`),
    `path` = VALUES(`path`),
    `status` = VALUES(`status`),
    `link_type` = VALUES(`link_type`),
    `is_page_menu` = VALUES(`is_page_menu`),
    `link_url` = VALUES(`link_url`),
    `data_permission` = VALUES(`data_permission`),
    `updated_at` = NOW(),
    `deleted_at` = NULL;

UPDATE `mc_rbac_menu`
SET `data_permission` = 1, `updated_at` = NOW()
WHERE `id` IN (170, 171, 172, 187)
  AND `link_url` IN (
      '/dashboard/workContact/show#get',
      '/dashboard/workContact/update#put',
      '/dashboard/workContact/track#get',
      '/dashboard/channelCode/contact#get'
  );

INSERT INTO `mc_rbac_role_menu` (`role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT DISTINCT `role_id`, 584, NOW(), NOW()
FROM `mc_rbac_role_menu` AS source
WHERE source.`menu_id` = 557
  AND NOT EXISTS (
      SELECT 1
      FROM `mc_rbac_role_menu` AS existing
      WHERE existing.`role_id` = source.`role_id`
        AND existing.`menu_id` = 584
  );

INSERT INTO `mc_rbac_role_menu` (`role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT DISTINCT `role_id`, 585, NOW(), NOW()
FROM `mc_rbac_role_menu` AS source
WHERE source.`menu_id` IN (149, 150, 576)
  AND NOT EXISTS (
      SELECT 1
      FROM `mc_rbac_role_menu` AS existing
      WHERE existing.`role_id` = source.`role_id`
        AND existing.`menu_id` = 585
  );

INSERT INTO `mc_rbac_role_menu` (`role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT DISTINCT `role_id`, 586, NOW(), NOW()
FROM `mc_rbac_role_menu` AS source
WHERE source.`menu_id` IN (170, 171, 172)
  AND NOT EXISTS (
      SELECT 1
      FROM `mc_rbac_role_menu` AS existing
      WHERE existing.`role_id` = source.`role_id`
        AND existing.`menu_id` = 586
  );

INSERT INTO `mc_rbac_role_menu` (`role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT DISTINCT source.`role_id`, grant_map.`menu_id`, NOW(), NOW()
FROM `mc_rbac_role_menu` AS source
JOIN (
    SELECT 50 AS `source_menu_id`, 587 AS `menu_id`
    UNION ALL SELECT 51, 588
    UNION ALL SELECT 51, 590
    UNION ALL SELECT 53, 589
    UNION ALL SELECT 48, 591
    UNION ALL SELECT 47, 592
    UNION ALL SELECT 47, 594
    UNION ALL SELECT 52, 593
    UNION ALL SELECT 126, 595
) AS grant_map ON grant_map.`source_menu_id` = source.`menu_id`
WHERE NOT EXISTS (
    SELECT 1
    FROM `mc_rbac_role_menu` AS existing
    WHERE existing.`role_id` = source.`role_id`
      AND existing.`menu_id` = grant_map.`menu_id`
);
