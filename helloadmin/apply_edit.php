<?php
// 检查登录状态
require '../config.php';
check_admin_access();

// 获取待审核申请数量
$pending_applications = count(get_friend_applications($pdo, 0));

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apply_page_content = trim($_POST['apply_page_content'] ?? '');
    
    try {
        $current_settings = get_settings($pdo);
        
        if ($current_settings) {
            // 更新设置
            $stmt = $pdo->prepare("UPDATE settings SET 
                                apply_page_content = :apply_page_content,
                                updated_at = CURRENT_TIMESTAMP
                                WHERE id = :id");
            
            $stmt->execute([
                ':apply_page_content' => $apply_page_content,
                ':id' => $current_settings['id']
            ]);
        } else {
            // 插入新设置
            $stmt = $pdo->prepare("INSERT INTO settings 
                                (site_title, apply_page_content) 
                                VALUES ('我的友链', :apply_page_content)");
            
            $stmt->execute([
                ':apply_page_content' => $apply_page_content
            ]);
        }
        
        $message = '申请页设置更新成功';
        $message_type = 'success';
        
        // 重新获取设置
        $settings = get_settings($pdo);
    } catch(PDOException $e) {
        $message = '保存设置失败: ' . $e->getMessage();
        $message_type = 'error';
    }
} else {
    // 获取当前设置
    $settings = get_settings($pdo);
}

// 获取主题颜色
$theme_color = $settings['theme_color'] ?? '#3B82F6';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申请页设置 - QLinkr友链管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo $theme_color; ?>',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- 侧边栏 -->
        <aside class="w-64 bg-gray-800 text-white hidden md:block">
            <div class="p-4 border-b border-gray-700">
                <h1 class="text-xl font-bold">QLinkr友链管理系统</h1>
            </div>
            
            <nav class="p-4">
                <ul>
                    <li class="mb-1">
                        <a href="index.php" class="flex items-center px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                            <i class="fa fa-tachometer mr-3 w-5 text-center"></i>仪表盘
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="friends.php" class="flex items-center px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                            <i class="fa fa-link mr-3 w-5 text-center"></i>友链管理
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="apply_review.php" class="flex items-center px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                            <i class="fa fa-list-alt mr-3 w-5 text-center"></i>友链申请审核
                            <?php if ($pending_applications > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                    <?php echo $pending_applications; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="settings.php" class="flex items-center px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                            <i class="fa fa-cog mr-3 w-5 text-center"></i>网站设置
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="apply_edit.php" class="flex items-center px-4 py-2 rounded bg-gray-700 text-white">
                            <i class="fa fa-file-text mr-3 w-5 text-center"></i>申请页设置
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="admin.php" class="flex items-center px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                            <i class="fa fa-user mr-3 w-5 text-center"></i>管理员设置
                        </a>
                    </li>
                    <li class="mt-6 pt-6 border-t border-gray-700">
                        <a href="logout.php" class="flex items-center px-4 py-2 rounded hover:bg-gray-700 transition-colors text-red-300 hover:text-red-200">
                            <i class="fa fa-sign-out mr-3 w-5 text-center"></i>退出登录
                        </a>
                        <p class="text-xs text-gray-400 mt-2 px-4">本系统来自iuarn<br>联系邮箱co-x@163.com</p>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- 主内容区 -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- 顶部导航 -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center">
                <button id="mobile-menu-button" class="md:hidden text-gray-600 focus:outline-none">
                    <i class="fa fa-bars text-xl"></i>
                </button>
                
                <div class="flex items-center">
                    <span class="text-gray-600 mr-2">欢迎回来，</span>
                    <span class="font-medium"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>
            
            <!-- 移动端菜单 -->
            <div id="mobile-menu" class="md:hidden bg-gray-800 text-white hidden">
                <nav class="p-4">
                    <ul>
                        <li class="mb-1">
                            <a href="index.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                                <i class="fa fa-tachometer mr-3"></i>仪表盘
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="friends.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                                <i class="fa fa-link mr-3"></i>友链管理
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="apply_review.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                                <i class="fa fa-list-alt mr-3"></i>友链申请审核
                                <?php if ($pending_applications > 0): ?>
                                    <span class="ml-2 bg-red-500 text-white text-xs rounded-full px-2">
                                        <?php echo $pending_applications; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="settings.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                                <i class="fa fa-cog mr-3"></i>网站设置
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="apply_edit.php" class="block px-4 py-2 rounded bg-gray-700 text-white">
                                <i class="fa fa-file-text mr-3"></i>申请页设置
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="admin.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                                <i class="fa fa-user mr-3"></i>管理员设置
                            </a>
                        </li>
                        <li class="mt-4">
                            <a href="logout.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors text-red-300 hover:text-red-200">
                                <i class="fa fa-sign-out mr-3"></i>退出登录
                            </a>
                            <p class="text-xs text-gray-400 mt-2 px-4">本系统来自iuarn<br>联系邮箱co-x@163.com</p>
                        </li>
                    </ul>
                </nav>
            </div>
            
            <!-- 页面内容 -->
            <main class="flex-1 overflow-y-auto p-4 md:p-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">申请页设置</h2>
                    <p class="text-gray-600">配置友链申请页面的说明文字</p>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6">
                        <form method="post">
                            <div class="mb-6">
                                <label for="apply_page_content" class="block text-sm font-medium text-gray-700 mb-1">申请页说明文字</label>
                                <textarea id="apply_page_content" name="apply_page_content" rows="8"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入申请页的说明文字，将显示在申请表单上方"><?php echo htmlspecialchars($settings['apply_page_content'] ?? '欢迎申请添加友链，请填写以下信息，我们会尽快审核。'); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">支持换行，可填写友链交换的要求、规则等信息</p>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <a href="../apply.php" target="_blank" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors font-medium flex items-center">
                                    <i class="fa fa-eye mr-2"></i>预览申请页
                                </a>
                                
                                <button type="submit" class="px-6 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium flex items-center">
                                    <i class="fa fa-save mr-2"></i>保存设置
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-bold mb-4">申请页使用说明</h3>
                        
                        <div class="space-y-4 text-gray-700">
                            <p>1. 访客可以通过网站导航的"申请友链"按钮进入申请页面</p>
                            <p>2. 提交的友链申请会显示在"友链申请审核"页面中</p>
                            <p>3. 您可以在申请审核页面对申请进行批准、拒绝或删除操作</p>
                            <p>4. 批准的申请会自动添加到友链列表中</p>
                            <p>5. 相同URL的网站只能提交一次申请，已在友链列表中的网站无法提交申请</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // 移动端菜单切换
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>
</html>