# 仓库说明

这是 Sakurairo WordPress 主题，当前版本信息及 PHP / WordPress 要求见 `style.css`。

## 相关代码位置

- `inc/theme-plus.php`：`header_user_menu()` 渲染桌面导航栏头像及登录悬浮菜单。
- `layouts/sakura_header.php`：导航栏布局，读取 `nav_user_menu` 并调用头像菜单。
- `layouts/mo_toc_menu.php`：独立的移动端用户菜单；桌面菜单修改不会自动同步到这里。
- `opt/options/theme-options.php`：主题设置字段，使用内置 `Sakurairo_CSF` 框架，存储在 WordPress 的 `iro_options` 选项中。
- `functions.php`：`iro_opt()` 读取设置；`register_shuoshuo_post_type()` 注册 `shuoshuo` 文章类型。
- `opt/fields/switcher/switcher.php`：主题设置开关控件。

## 修改约定

- 小修改只定位和调整相关代码，避免无关重构及反复全量复核。
- 设置字段的 `id` 与前端 `iro_opt()` 的键必须一致，默认值也要保持一致。
- 管理后台链接使用 `admin_url()`，输出 URL 时使用 `esc_url()`；沿用相关菜单的权限条件。
- 前端翻译域为 `sakurairo`，主题设置翻译域为 `sakurairo_csf`。
- `nav_user_menu_shuoshuo` 默认关闭，开启后为桌面头像菜单中的管理员显示“撰写说说”。
- 按改动范围做必要验证：有 PHP 环境时对修改文件执行 `php -l`；设置修改检查字段注册、保存值和前端读取的对应关系。线上界面由用户上传服务器验证。
