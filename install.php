<?php
// 配置文件路径
define('CONFIG_FILE', __DIR__ . '/database_config.php');

// 如果已安装则跳转到首页
if (file_exists(CONFIG_FILE)) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';
$db_host = 'localhost';
$db_name = 'friend_links';
$db_user = 'root';
$db_pass = '';
$pdo = null;

// 处理安装请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? 'friend_links');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = trim($_POST['db_pass'] ?? '');
    
    try {
        // 尝试连接数据库服务器（不指定数据库名）
        $temp_pdo = new PDO(
            "mysql:host=" . $db_host . ";charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // 检查数据库是否存在，不存在则创建
        $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // 连接到指定数据库
        $pdo = new PDO(
            "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // 创建数据表
        
        // 1. 设置表
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_title VARCHAR(255) NOT NULL DEFAULT '我的友链',
            logo VARCHAR(255) NULL,
            background VARCHAR(255) NULL,
            theme_color VARCHAR(20) NOT NULL DEFAULT '#3B82F6',
            glass_effect TINYINT(1) NOT NULL DEFAULT 1,
            footer_text TEXT NULL,
            footer_elements TEXT NULL,
            footer_custom TEXT NULL,
            apply_page_content TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // 2. 友链表
        $pdo->exec("CREATE TABLE IF NOT EXISTS friends (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            logo VARCHAR(255) NULL,
            description TEXT NULL,
            category VARCHAR(100) NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY url (url)
        )");
        
        // 3. 友链申请表（新增）
        $pdo->exec("CREATE TABLE IF NOT EXISTS friend_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            logo VARCHAR(255) NULL,
            description TEXT NULL,
            category VARCHAR(100) NULL,
            contact VARCHAR(255) NULL,
            status TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0-待审核,1-已通过,2-已拒绝',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY url (url)
        )");
        
        // 4. 管理员表
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY username (username)
        )");
        
        // 5. 访问统计表
        $pdo->exec("CREATE TABLE IF NOT EXISTS visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            user_agent TEXT NULL,
            page VARCHAR(255) NULL,
            visit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 添加默认管理员（用户名：admin，密码：admin888）
        $admin_username = 'admin';
        $admin_password = 'admin888';
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO admin (username, password) VALUES ('$admin_username', '$hashed_password')");
        
        // 添加默认设置
        $pdo->exec("INSERT INTO settings (site_title) VALUES ('我的友链')");
        
        // 保存数据库配置到文件
        $config_content = "<?php\n";
        $config_content .= "\$db_config = [\n";
        $config_content .= "    'host' => '" . addslashes($db_host) . "',\n";
        $config_content .= "    'name' => '" . addslashes($db_name) . "',\n";
        $config_content .= "    'user' => '" . addslashes($db_user) . "',\n";
        $config_content .= "    'pass' => '" . addslashes($db_pass) . "'\n";
        $config_content .= "];\n";
        
        if (file_put_contents(CONFIG_FILE, $config_content) === false) {
            throw new Exception("无法写入配置文件，请检查目录权限");
        }
        
        // 设置配置文件权限
        chmod(CONFIG_FILE, 0600);
        
        $message = "安装成功！<br>";
        $message .= "管理员账号：<strong>$admin_username</strong><br>";
        $message .= "管理员密码：<strong>$admin_password</strong><br>";
        $message .= "请尽快登录后台修改密码以保证安全";
        $message_type = 'success';
        
    } catch(PDOException $e) {
        $message = '数据库连接失败：' . $e->getMessage() . '<br>请检查数据库信息是否正确';
        $message_type = 'error';
    } catch(Exception $e) {
        $message = '安装失败：' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>友链系统 - 安装向导</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 md:p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa fa-link text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">QLinkr友链系统安装向导</h1>
            <p class="text-gray-600 mt-2">请填写数据库信息完成安装</p>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
            <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($message) || $message_type === 'error'): ?>
        <form method="post">
            <div class="mb-4">
                <label for="db_host" class="block text-gray-700 mb-2 font-medium">数据库主机</label>
                <input type="text" id="db_host" name="db_host" required
                    value="<?php echo htmlspecialchars($db_host); ?>"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    placeholder="通常为 localhost">
            </div>
            
            <div class="mb-4">
                <label for="db_name" class="block text-gray-700 mb-2 font-medium">数据库名</label>
                <input type="text" id="db_name" name="db_name" required
                    value="<?php echo htmlspecialchars($db_name); ?>"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    placeholder="数据库名称，系统会自动创建">
            </div>
            
            <div class="mb-4">
                <label for="db_user" class="block text-gray-700 mb-2 font-medium">数据库用户名</label>
                <input type="text" id="db_user" name="db_user" required
                    value="<?php echo htmlspecialchars($db_user); ?>"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    placeholder="数据库登录用户名">
            </div>
            
            <div class="mb-6">
                <label for="db_pass" class="block text-gray-700 mb-2 font-medium">数据库密码</label>
                <input type="password" id="db_pass" name="db_pass"
                    value="<?php echo htmlspecialchars($db_pass); ?>"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    placeholder="数据库登录密码，如无密码请留空">
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <h3 class="font-medium text-yellow-800 mb-2 flex items-center">
                    <i class="fa fa-info-circle mr-2"></i>安装前请确认
                </h3>
                <ul class="text-sm text-yellow-700 space-y-1 list-disc pl-5">
                    <li>数据库用户具有创建数据库和表的权限</li>
                    <li>当前目录和Image目录具有可写权限</li>
                </ul>
            </div>
            
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium flex items-center justify-center">
                <i class="fa fa-play-circle mr-2"></i>开始安装系统
            </button>
        </form>
        <?php else: ?>
        <div class="flex flex-col space-y-3">
            <a href="index.php" class="w-full py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors font-medium flex items-center justify-center">
                <i class="fa fa-home mr-2"></i>访问首页
            </a>
            <a href="<?php echo defined('ADMIN_DIR') ? ADMIN_DIR : 'helloadmin'; ?>/login.php" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium flex items-center justify-center">
                <i class="fa fa-sign-in mr-2"></i>登录管理后台
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
