<?php
// 初始化会话
session_start();

// 如果已登录，跳转到后台首页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

require '../config.php';

$message = '';
$message_type = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $message = '用户名和密码不能为空';
        $message_type = 'error';
    } else {
        // 验证管理员账号密码
        if (validate_admin($pdo, $username, $password)) {
            // 登录成功，设置会话
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            
            // 跳转到后台首页
            header('Location: index.php');
            exit;
        } else {
            $message = '用户名或密码不正确';
            $message_type = 'error';
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
    <title>管理员登录 - QLinkr友链管理系统</title>
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
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 md:p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa fa-lock text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">QLinkr友链管理系统</h1>
            <p class="text-gray-600 mt-2">请登录管理员账号</p>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
            <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 mb-2 font-medium">用户名</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class="fa fa-user"></i>
                    </span>
                    <input type="text" id="username" name="username" required
                        class="block w-full pl-10 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                        placeholder="请输入用户名">
                </div>
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 mb-2 font-medium">密码</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                        <i class="fa fa-lock"></i>
                    </span>
                    <input type="password" id="password" name="password" required
                        class="block w-full pl-10 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                        placeholder="请输入密码">
                </div>
            </div>
            
            <button type="submit" class="w-full py-3 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium">
                登录
            </button>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-600">
            <p>© 2025 QLinkr友链系统 版权所有</p>
        </div>
    </div>
</body>
</html>