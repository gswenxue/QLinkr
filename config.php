<?php
session_start();

// 配置文件路径
define('CONFIG_FILE', __DIR__ . '/database_config.php');

// 后台目录名称（可修改，增强安全性）
define('ADMIN_DIR', 'helloadmin');

// 图片上传目录
define('UPLOAD_DIR', __DIR__ . '/Image/');
define('UPLOAD_URL', 'Image/');

// 确保上传目录存在
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// 检查是否已安装
function is_installed() {
    return file_exists(CONFIG_FILE);
}

// 如果未安装且不在安装页面，则跳转到安装页面
if (!is_installed() && !str_contains($_SERVER['PHP_SELF'], 'install.php')) {
    header('Location: install.php');
    exit;
}

// 加载数据库配置
$db_config = [];
if (is_installed()) {
    require CONFIG_FILE;
}

// 数据库连接
try {
    $pdo = new PDO(
        "mysql:host=" . $db_config['host'] . ";dbname=" . $db_config['name'] . ";charset=utf8mb4",
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    if (str_contains($_SERVER['PHP_SELF'], 'install.php')) {
        // 在安装页面发生错误，交给安装页面处理
        throw $e;
    } else {
        die("数据库连接失败: " . $e->getMessage() . "<br>请检查数据库配置或重新 <a href='install.php'>安装</a> 系统");
    }
}

// 验证管理员账号密码
function validate_admin($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            return true;
        }
        return false;
    } catch(PDOException $e) {
        error_log("管理员验证失败: " . $e->getMessage());
        return false;
    }
}

// 获取访问统计数据
function get_visit_stats($pdo) {
    try {
        // 总访问量
        $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM visits");
        $total = $total_stmt->fetch()['total'] ?? 0;
        
        // 今日访问量
        $today = date('Y-m-d');
        $today_stmt = $pdo->prepare("SELECT COUNT(*) as today FROM visits WHERE DATE(visit_time) = :today");
        $today_stmt->execute([':today' => $today]);
        $today_visits = $today_stmt->fetch()['today'] ?? 0;
        
        // 昨日访问量
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterday_stmt = $pdo->prepare("SELECT COUNT(*) as yesterday FROM visits WHERE DATE(visit_time) = :yesterday");
        $yesterday_stmt->execute([':yesterday' => $yesterday]);
        $yesterday_visits = $yesterday_stmt->fetch()['yesterday'] ?? 0;
        
        // 最近7天访问量
        $seven_days_ago = date('Y-m-d', strtotime('-7 days'));
        $seven_stmt = $pdo->prepare("SELECT COUNT(*) as seven_days FROM visits WHERE DATE(visit_time) >= :seven_days_ago");
        $seven_stmt->execute([':seven_days_ago' => $seven_days_ago]);
        $seven_days_visits = $seven_stmt->fetch()['seven_days'] ?? 0;
        
        return [
            'total' => $total,
            'today' => $today_visits,
            'yesterday' => $yesterday_visits,
            'seven_days' => $seven_days_visits
        ];
    } catch(PDOException $e) {
        error_log("获取访问统计失败: " . $e->getMessage());
        return [
            'total' => 0,
            'today' => 0,
            'yesterday' => 0,
            'seven_days' => 0
        ];
    }
}

// 获取友链申请
function get_friend_applications($pdo, $status = 0) {
    try {
        if ($status === null) {
            $stmt = $pdo->query("SELECT * FROM friend_applications ORDER BY created_at DESC");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM friend_applications WHERE status = :status ORDER BY created_at DESC");
            $stmt->execute([':status' => $status]);
        }
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("获取友链申请失败: " . $e->getMessage());
        return [];
    }
}

// 获取单个友链申请
function get_friend_application($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM friend_applications WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("获取单个友链申请失败: " . $e->getMessage());
        return null;
    }
}

// 提交友链申请
function submit_friend_application($pdo, $data) {
    try {
        // 检查URL是否已存在于友链表或申请表中
        $stmt = $pdo->prepare("SELECT id FROM friends WHERE url = :url LIMIT 1");
        $stmt->execute([':url' => $data['url']]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => '该链接已在友链列表中'];
        }
        
        $stmt = $pdo->prepare("SELECT id FROM friend_applications WHERE url = :url LIMIT 1");
        $stmt->execute([':url' => $data['url']]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => '该链接已提交申请，请等待审核'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO friend_applications 
                            (name, url, logo, description, category, contact) 
                            VALUES (:name, :url, :logo, :description, :category, :contact)");
        
        $stmt->execute([
            ':name' => $data['name'],
            ':url' => $data['url'],
            ':logo' => $data['logo'] ?? '',
            ':description' => $data['description'] ?? '',
            ':category' => $data['category'] ?? '',
            ':contact' => $data['contact'] ?? ''
        ]);
        
        return ['success' => true, 'message' => '申请提交成功，请等待审核'];
    } catch(PDOException $e) {
        error_log("提交友链申请失败: " . $e->getMessage());
        return ['success' => false, 'message' => '提交失败，请稍后重试'];
    }
}

// 批准友链申请
function approve_friend_application($pdo, $id) {
    try {
        // 获取申请信息
        $application = get_friend_application($pdo, $id);
        if (!$application) {
            return ['success' => false, 'message' => '申请不存在'];
        }
        
        // 将申请添加到友链表
        $stmt = $pdo->prepare("INSERT INTO friends 
                            (name, url, logo, description, category, status) 
                            VALUES (:name, :url, :logo, :description, :category, 1)");
        
        $stmt->execute([
            ':name' => $application['name'],
            ':url' => $application['url'],
            ':logo' => $application['logo'],
            ':description' => $application['description'],
            ':category' => $application['category']
        ]);
        
        // 更新申请状态为已通过
        $stmt = $pdo->prepare("UPDATE friend_applications SET status = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        return ['success' => true, 'message' => '申请已批准并添加为友链'];
    } catch(PDOException $e) {
        error_log("批准友链申请失败: " . $e->getMessage());
        return ['success' => false, 'message' => '操作失败，请稍后重试'];
    }
}

// 拒绝友链申请
function reject_friend_application($pdo, $id) {
    try {
        $stmt = $pdo->prepare("UPDATE friend_applications SET status = 2 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => '申请已拒绝'];
        }
        
        return ['success' => false, 'message' => '申请不存在'];
    } catch(PDOException $e) {
        error_log("拒绝友链申请失败: " . $e->getMessage());
        return ['success' => false, 'message' => '操作失败，请稍后重试'];
    }
}

// 删除友链申请
function delete_friend_application($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM friend_applications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => '申请已删除'];
        }
        
        return ['success' => false, 'message' => '申请不存在'];
    } catch(PDOException $e) {
        error_log("删除友链申请失败: " . $e->getMessage());
        return ['success' => false, 'message' => '操作失败，请稍后重试'];
    }
}

// 检查登录状态
function is_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// 验证管理员权限（用于后台页面）
function check_admin_access() {
    if (!is_logged_in()) {
        header('Location: ' . ADMIN_DIR . '/login.php');
        exit;
    }
}

// 获取网站设置
function get_settings($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
        return $stmt->fetch();
    } catch(PDOException $e) {
        return [];
    }
}

// 获取所有友链
function get_friends($pdo, $status = 1) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM friends WHERE status = :status ORDER BY sort_order ASC, id DESC");
        $stmt->execute([':status' => $status]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// 获取所有分类
function get_categories($pdo) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM friends WHERE status = 1 AND category != '' ORDER BY category ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_filter($categories);
    } catch(PDOException $e) {
        return [];
    }
}

// 记录访问量
function record_visit($pdo) {
    if (isset($_SESSION['visit_recorded']) && $_SESSION['visit_recorded'] === true) {
        return; // 同一会话只记录一次
    }
    
    try {
        // 检查访问统计表格是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE 'visits'");
        if ($stmt->rowCount() == 0) {
            return; // 表格不存在，不记录
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $page = $_SERVER['REQUEST_URI'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO visits (ip, user_agent, page, visit_time) 
                             VALUES (:ip, :user_agent, :page, CURRENT_TIMESTAMP)");
        $stmt->execute([
            ':ip' => $ip,
            ':user_agent' => substr($user_agent, 0, 255),
            ':page' => $page
        ]);
        
        $_SESSION['visit_recorded'] = true;
    } catch(PDOException $e) {
        // 记录错误但不中断程序
        error_log("访问统计记录失败: " . $e->getMessage());
    }
}

// 上传图片处理函数
function upload_image($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif']) {
    if ($file['error'] != UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '上传失败，请重试'];
    }
    
    // 检查文件类型
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => '不支持的文件类型，仅允许JPG、PNG、GIF'];
    }
    
    // 生成唯一文件名
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $destination = UPLOAD_DIR . $filename;
    
    // 移动上传文件
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // 设置适当的文件权限
        chmod($destination, 0644);
        return [
            'success' => true,
            'filename' => $filename,
            'url' => UPLOAD_URL . $filename
        ];
    } else {
        return ['success' => false, 'message' => '文件保存失败，请检查目录权限'];
    }
}

// 删除图片文件
function delete_image($filename) {
    $path = UPLOAD_DIR . $filename;
    if (file_exists($path) && is_file($path)) {
        return unlink($path);
    }
    return false;
}
?>