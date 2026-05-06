-- ============================================================
-- 域名管理表 v1.0
-- 用于存储用户绑定的短链域名
-- ============================================================

-- ----------------------------
-- 域名列表表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `domains` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL COMMENT '完整域名',
  `name` varchar(64) DEFAULT '' COMMENT '域名备注名称',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=默认域名',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=禁用 1=启用',
  `parse_type` varchar(16) NOT NULL DEFAULT 'cname' COMMENT '解析类型: cname/a',
  `parse_value` varchar(512) DEFAULT '' COMMENT '解析值(CNAME记录值或A记录IP)',
  `parse_status` varchar(32) DEFAULT 'pending' COMMENT 'pending=待检测 ok=正常 failed=检测失败',
  `parse_msg` varchar(255) DEFAULT '' COMMENT '检测提示信息',
  `link_count` int(11) NOT NULL DEFAULT 0 COMMENT '使用该域名的链接数',
  `created_by` int(11) UNSIGNED NOT NULL DEFAULT 1 COMMENT '创建人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain` (`domain`),
  KEY `idx_status` (`status`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='域名列表';

-- ----------------------------
-- 初始数据（可选）
-- ----------------------------
-- INSERT INTO `domains` (`domain`, `name`, `is_default`, `status`, `解析_type`, `解析_value`, `解析_status`) VALUES
-- ('dls.cn', '短链主域名', 1, 1, 'cname', 'your-server.example.com', 'ok');
