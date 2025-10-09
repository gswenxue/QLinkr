<?php
// 检查登录状态
require '../config.php';
check_admin_access();

// 获取待审核申请数量
$pending_applications = count(get_friend_applications($pdo, 0));

// 处理表单提交
$message = '';
$message_type = '';

// 获取当前管理员信息
try {
    $stmt = $pdo->query("SELECT * FROM admin LIMIT 1");
    $admin = $stmt->fetch();
} catch(PDOException $e) {
    $message = '获取管理员信息失败: ' . $e->getMessage();
    $message_type = 'error';
    $admin = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
    $username = trim($_POST['username'] ?? '');
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // 验证用户名
    if (empty($username)) {
        $message = '用户名为必填项';
        $message_type = 'error';
    } elseif ($username !== $admin['username']) {
        // 用户名变更
        try {
            // 检查新用户名是否已存在
            $stmt = $pdo->prepare("SELECT id FROM admin WHERE username = :username AND id != :id LIMIT 1");
            $stmt->execute([':username' => $username, ':id' => $admin['id']]);
            
            if ($stmt->rowCount() > 0) {
                $message = '用户名已存在，请更换其他用户名';
                $message_type = 'error';
            } else {
                // 更新用户名
                $stmt = $pdo->prepare("UPDATE admin SET username = :username WHERE id = :id");
                $stmt->execute([':username' => $username, ':id' => $admin['id']]);
                
                // 更新会话中的用户名
                $_SESSION['admin_username'] = $username;
                $message = '用户名更新成功';
                $message_type = 'success';
            }
        } catch(PDOException $e) {
            $message = '更新用户名失败: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // 处理密码修改（如果填写了新密码）
    if (!empty($new_password) && empty($message)) {
        // 验证当前密码
        if (empty($current_password)) {
            $message = '请输入当前密码';
            $message_type = 'error';
        } elseif (!password_verify($current_password, $admin['password'])) {
            $message = '当前密码不正确';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = '新密码长度不能少于6位';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = '两次输入的新密码不一致';
            $message_type = 'error';
        } else {
            // 验证通过，更新密码
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin SET password = :password WHERE id = :id");
                $stmt->execute([':password' => $hashed_password, ':id' => $admin['id']]);
                
                $message = '密码更新成功，请重新登录';
                $message_type = 'success';
                
                // 密码修改后强制退出登录
                echo '<script>setTimeout(function(){ window.location.href = "logout.php"; }, 2000);</script>';
            } catch(PDOException $e) {
                $message = '更新密码失败: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// 获取主题颜色
$settings = get_settings($pdo);
$theme_color = $settings['theme_color'] ?? '#3B82F6';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员设置 - QLinkr友链管理系统</title>
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
                        <a href="apply_edit.php" class="flex items-center px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                            <i class="fa fa-file-text mr-3 w-5 text-center"></i>申请页设置
                        </a>
                    </li>
                    <li class="mb-1">
                        <a href="admin.php" class="flex items-center px-4 py-2 rounded bg-gray-700 text-white">
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
                            <a href="apply_edit.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                                <i class="fa fa-file-text mr-3"></i>申请页设置
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="admin.php" class="block px-4 py-2 rounded bg-gray-700 text-white">
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
                    <h2 class="text-2xl font-bold text-gray-800">管理员设置</h2>
                    <p class="text-gray-600">修改管理员账号信息</p>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($admin): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <form method="post">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-bold mb-4">基本信息</h3>
                            
                            <div class="mb-6">
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">管理员用户名 <span class="text-red-500">*</span></label>
                                <input type="text" id="username" name="username" required
                                    value="<?php echo htmlspecialchars($admin['username']); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors">
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <h3 class="text-lg font-bold mb-4">修改密码</h3>
                            <p class="text-sm text-gray-500 mb-4">如需修改密码，请填写以下信息；不修改密码则无需填写</p>
                            
                            <div class="mb-6">
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">当前密码</label>
                                <input type="password" id="current_password" name="current_password"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors">
                            </div>
                            
                            <div class="mb-6">
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">新密码</label>
                                <input type="password" id="new_password" name="new_password"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="至少6位字符">
                            </div>
                            
                            <div class="mb-6">
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">确认新密码</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors">
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium flex items-center">
                                    <i class="fa fa-save mr-2"></i>保存设置
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-center py-8">
                        <i class="fa fa-exclamation-triangle text-yellow-500 text-5xl mb-4"></i>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">无法获取管理员信息</h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($message ?: '请检查数据库连接或管理员表是否存在'); ?></p>
                    </div>
                </div>
                <?php endif; ?>
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