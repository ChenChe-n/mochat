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
