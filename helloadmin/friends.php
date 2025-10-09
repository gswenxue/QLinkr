<?php
// 检查登录状态
require '../config.php';
check_admin_access();

// 处理友链操作
$message = '';
$message_type = '';
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// 获取待审核申请数量
$pending_applications = count(get_friend_applications($pdo, 0));

// 处理添加友链
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    
    if (empty($name) || empty($url)) {
        $message = '网站名称和网址为必填项';
        $message_type = 'error';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $message = '请输入有效的网址（需包含http://或https://）';
        $message_type = 'error';
    } else {
        try {
            // 检查URL是否已存在
            $stmt = $pdo->prepare("SELECT id FROM friends WHERE url = :url LIMIT 1");
            $stmt->execute([':url' => $url]);
            if ($stmt->rowCount() > 0) {
                $message = '该网址已在友链列表中';
                $message_type = 'error';
            } else {
                // 处理logo上传
                $logo = '';
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
                    $upload_result = upload_image($_FILES['logo']);
                    if ($upload_result['success']) {
                        $logo = $upload_result['url'];
                    } else {
                        $message = $upload_result['message'];
                        $message_type = 'error';
                    }
                }
                
                if (empty($message)) {
                    $stmt = $pdo->prepare("INSERT INTO friends 
                                        (name, url, logo, description, category, sort_order, status) 
                                        VALUES (:name, :url, :logo, :description, :category, :sort_order, 1)");
                    
                    $stmt->execute([
                        ':name' => $name,
                        ':url' => $url,
                        ':logo' => $logo,
                        ':description' => $description,
                        ':category' => $category,
                        ':sort_order' => $sort_order
                    ]);
                    
                    $message = '友链添加成功';
                    $message_type = 'success';
                }
            }
        } catch(PDOException $e) {
            $message = '添加失败: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 处理编辑友链
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (empty($name) || empty($url)) {
            $message = '网站名称和网址为必填项';
            $message_type = 'error';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $message = '请输入有效的网址（需包含http://或https://）';
            $message_type = 'error';
        } else {
            try {
                // 检查URL是否已存在（排除当前ID）
                $stmt = $pdo->prepare("SELECT id FROM friends WHERE url = :url AND id != :id LIMIT 1");
                $stmt->execute([':url' => $url, ':id' => $id]);
                if ($stmt->rowCount() > 0) {
                    $message = '该网址已在友链列表中';
                    $message_type = 'error';
                } else {
                    // 获取当前友链信息
                    $stmt = $pdo->prepare("SELECT logo FROM friends WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $id]);
                    $friend = $stmt->fetch();
                    $logo = $friend['logo'] ?? '';
                    
                    // 处理logo上传（如果有新上传）
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
                        $upload_result = upload_image($_FILES['logo']);
                        if ($upload_result['success']) {
                            // 删除旧图片
                            if (!empty($logo)) {
                                $old_filename = basename($logo);
                                delete_image($old_filename);
                            }
                            $logo = $upload_result['url'];
                        } else {
                            $message = $upload_result['message'];
                            $message_type = 'error';
                        }
                    }
                    
                    if (empty($message)) {
                        $stmt = $pdo->prepare("UPDATE friends SET 
                                            name = :name, 
                                            url = :url, 
                                            logo = :logo, 
                                            description = :description, 
                                            category = :category, 
                                            sort_order = :sort_order,
                                            status = :status,
                                            updated_at = CURRENT_TIMESTAMP
                                            WHERE id = :id");
                        
                        $stmt->execute([
                            ':name' => $name,
                            ':url' => $url,
                            ':logo' => $logo,
                            ':description' => $description,
                            ':category' => $category,
                            ':sort_order' => $sort_order,
                            ':status' => $status,
                            ':id' => $id
                        ]);
                        
                        $message = '友链更新成功';
                        $message_type = 'success';
                    }
                }
            } catch(PDOException $e) {
                $message = '更新失败: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // 获取友链信息
    try {
        $stmt = $pdo->prepare("SELECT * FROM friends WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $edit_friend = $stmt->fetch();
        
        if (!$edit_friend) {
            $message = '友链不存在';
            $message_type = 'error';
            $edit_friend = null;
        }
    } catch(PDOException $e) {
        $message = '获取友链信息失败: ' . $e->getMessage();
        $message_type = 'error';
        $edit_friend = null;
    }
}

// 处理删除友链
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        // 获取友链logo
        $stmt = $pdo->prepare("SELECT logo FROM friends WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $friend = $stmt->fetch();
        
        if ($friend) {
            // 删除图片
            if (!empty($friend['logo'])) {
                $filename = basename($friend['logo']);
                delete_image($filename);
            }
            
            // 删除记录
            $stmt = $pdo->prepare("DELETE FROM friends WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            $message = '友链已删除';
            $message_type = 'success';
        } else {
            $message = '友链不存在';
            $message_type = 'error';
        }
    } catch(PDOException $e) {
        $message = '删除失败: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    $action = $_POST['bulk_action'];
    
    if (empty($ids)) {
        $message = '请选择要操作的友链';
        $message_type = 'error';
    } else {
        try {
            $ids_placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            if ($action === 'delete') {
                // 获取要删除的logo
                $stmt = $pdo->prepare("SELECT logo FROM friends WHERE id IN ($ids_placeholders)");
                $stmt->execute($ids);
                $friends = $stmt->fetchAll();
                
                // 删除图片
                foreach ($friends as $friend) {
                    if (!empty($friend['logo'])) {
                        $filename = basename($friend['logo']);
                        delete_image($filename);
                    }
                }
                
                // 删除记录
                $stmt = $pdo->prepare("DELETE FROM friends WHERE id IN ($ids_placeholders)");
                $stmt->execute($ids);
                
                $message = '已删除 ' . count($ids) . ' 条友链';
                $message_type = 'success';
            } elseif ($action === 'enable') {
                $stmt = $pdo->prepare("UPDATE friends SET status = 1 WHERE id IN ($ids_placeholders)");
                $stmt->execute($ids);
                
                $message = '已启用 ' . count($ids) . ' 条友链';
                $message_type = 'success';
            } elseif ($action === 'disable') {
                $stmt = $pdo->prepare("UPDATE friends SET status = 0 WHERE id IN ($ids_placeholders)");
                $stmt->execute($ids);
                
                $message = '已禁用 ' . count($ids) . ' 条友链';
                $message_type = 'success';
            }
        } catch(PDOException $e) {
            $message = '批量操作失败: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 获取友链列表
$filter_status = isset($_GET['status']) ? intval($_GET['status']) : 1;
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';

try {
    // 构建查询条件
    $where_clauses = [];
    $params = [];
    
    if ($filter_status != -1) {
        $where_clauses[] = "status = :status";
        $params[':status'] = $filter_status;
    }
    
    if (!empty($filter_category)) {
        $where_clauses[] = "category = :category";
        $params[':category'] = $filter_category;
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = "WHERE " . implode(' AND ', $where_clauses);
    }
    
    // 获取总数
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM friends $where_sql");
    $stmt->execute($params);
    $total_items = $stmt->fetch()['total'] ?? 0;
    $total_pages = max(1, ceil($total_items / $items_per_page));
    
    // 获取当前页数据
    $stmt = $pdo->prepare("SELECT * FROM friends $where_sql ORDER BY sort_order ASC, id DESC LIMIT :offset, :limit");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    // 绑定其他参数
    foreach ($params as $key => $value) {
        if ($key != ':offset' && $key != ':limit') {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $friends = $stmt->fetchAll();
    
    // 获取所有分类
    $stmt = $pdo->query("SELECT DISTINCT category FROM friends WHERE category != '' ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $message = '获取友链列表失败: ' . $e->getMessage();
    $message_type = 'error';
    $friends = [];
    $categories = [];
    $total_items = 0;
    $total_pages = 1;
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
    <title>友链管理 - QLinkr友链管理系统</title>
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
                        <a href="friends.php" class="flex items-center px-4 py-2 rounded bg-gray-700 text-white">
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
                            <a href="friends.php" class="block px-4 py-2 rounded bg-gray-700 text-white">
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
                    <h2 class="text-2xl font-bold text-gray-800">友链管理</h2>
                    <p class="text-gray-600">添加、编辑和管理您的友链</p>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <!-- 添加友链按钮 -->
                <div class="mb-6 flex justify-between items-center flex-wrap gap-4">
                    <button id="add-friend-button" class="px-5 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium flex items-center">
                        <i class="fa fa-plus mr-2"></i>添加友链
                    </button>
                    
                    <!-- 筛选器 -->
                    <div class="flex flex-wrap gap-2">
                        <form method="get" class="flex flex-wrap gap-2">
                            <select name="status" class="px-3 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary" onchange="this.form.submit()">
                                <option value="1" <?php echo $filter_status == 1 ? 'selected' : ''; ?>>已启用</option>
                                <option value="0" <?php echo $filter_status == 0 ? 'selected' : ''; ?>>已禁用</option>
                                <option value="-1" <?php echo $filter_status == -1 ? 'selected' : ''; ?>>全部</option>
                            </select>
                            
                            <select name="category" class="px-3 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary" onchange="this.form.submit()">
                                <option value="">所有分类</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filter_category == $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <?php if ($filter_status != 1 || !empty($filter_category)): ?>
                                <a href="friends.php" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded-md transition-colors">
                                    <i class="fa fa-refresh"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- 添加友链表单 -->
                <div id="add-friend-form" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
                    <h3 class="text-lg font-bold mb-4 flex items-center">
                        <i class="fa fa-plus-circle text-primary mr-2"></i>添加新友链
                    </h3>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">网站名称 <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入网站名称">
                            </div>
                            
                            <div>
                                <label for="url" class="block text-sm font-medium text-gray-700 mb-1">网站网址 <span class="text-red-500">*</span></label>
                                <input type="url" id="url" name="url" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入网站网址（需包含http://或https://）">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">网站分类</label>
                                <input type="text" id="category" name="category"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入网站分类（可选）">
                            </div>
                            
                            <div>
                                <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">排序权重</label>
                                <input type="number" id="sort_order" name="sort_order" value="0" min="0"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="数字越大越靠前">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">网站描述</label>
                            <textarea id="description" name="description" rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                placeholder="请输入网站描述（可选）"></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">网站Logo</label>
                            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors">
                            <p class="text-xs text-gray-500 mt-1">支持JPG、PNG、GIF格式，建议尺寸120x120px</p>
                        </div>
                        
                        <div class="flex justify-end gap-3">
                            <button type="button" id="cancel-add" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors font-medium">
                                取消
                            </button>
                            <button type="submit" class="px-5 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium">
                                <i class="fa fa-save mr-1"></i>保存友链
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 编辑友链表单 -->
                <?php if (isset($edit_friend) && $edit_friend): ?>
                <div id="edit-friend-form" class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-bold mb-4 flex items-center">
                        <i class="fa fa-pencil text-primary mr-2"></i>编辑友链
                    </h3>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">网站名称 <span class="text-red-500">*</span></label>
                                <input type="text" id="edit_name" name="name" required
                                    value="<?php echo htmlspecialchars($edit_friend['name']); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入网站名称">
                            </div>
                            
                            <div>
                                <label for="edit_url" class="block text-sm font-medium text-gray-700 mb-1">网站网址 <span class="text-red-500">*</span></label>
                                <input type="url" id="edit_url" name="url" required
                                    value="<?php echo htmlspecialchars($edit_friend['url']); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入网站网址（需包含http://或https://）">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="edit_category" class="block text-sm font-medium text-gray-700 mb-1">网站分类</label>
                                <input type="text" id="edit_category" name="category"
                                    value="<?php echo htmlspecialchars($edit_friend['category']); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入网站分类（可选）">
                            </div>
                            
                            <div>
                                <label for="edit_sort_order" class="block text-sm font-medium text-gray-700 mb-1">排序权重</label>
                                <input type="number" id="edit_sort_order" name="sort_order" min="0"
                                    value="<?php echo $edit_friend['sort_order']; ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="数字越大越靠前">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-1">网站描述</label>
                            <textarea id="edit_description" name="description" rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                placeholder="请输入网站描述（可选）"><?php echo htmlspecialchars($edit_friend['description']); ?></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">当前Logo</label>
                            <?php if (!empty($edit_friend['logo'])): ?>
                                <div class="mb-3">
                                    <img src="<?php echo htmlspecialchars($edit_friend['logo']); ?>" alt="<?php echo htmlspecialchars($edit_friend['name']); ?>" class="h-20 w-auto rounded">
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 mb-3">未设置Logo</p>
                            <?php endif; ?>
                            
                            <label for="edit_logo" class="block text-sm font-medium text-gray-700 mb-1">更换Logo</label>
                            <input type="file" id="edit_logo" name="logo" accept="image/jpeg,image/png,image/gif"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors">
                            <p class="text-xs text-gray-500 mt-1">支持JPG、PNG、GIF格式，建议尺寸120x120px，不选择则保持原Logo</p>
                        </div>
                        
                        <div class="mb-6">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="status" value="1" <?php echo $edit_friend['status'] == 1 ? 'checked' : ''; ?>
                                    class="form-checkbox h-5 w-5 text-primary rounded">
                                <span class="ml-2 text-gray-700">启用该友链</span>
                            </label>
                        </div>
                        
                        <div class="flex justify-end gap-3">
                            <a href="friends.php" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors font-medium">
                                取消
                            </a>
                            <button type="submit" class="px-5 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium">
                                <i class="fa fa-save mr-1"></i>更新友链
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- 友链列表 -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <?php if (empty($friends)): ?>
                        <div class="p-8 text-center">
                            <i class="fa fa-link text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-500">没有找到友链数据</p>
                            <button id="add-first-friend" class="mt-4 px-5 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium">
                                <i class="fa fa-plus mr-2"></i>添加第一条友链
                            </button>
                        </div>
                    <?php else: ?>
                        <form id="bulk-action-form" method="post">
                            <div class="p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3">
                                <div class="flex items-center">
                                    <input type="checkbox" id="select-all" class="form-checkbox h-5 w-5 text-primary rounded">
                                    <label for="select-all" class="ml-2 text-gray-700">全选</label>
                                    
                                    <div class="ml-4">
                                        <select name="bulk_action" class="px-3 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary">
                                            <option value="">批量操作</option>
                                            <option value="enable">启用所选</option>
                                            <option value="disable">禁用所选</option>
                                            <option value="delete">删除所选</option>
                                        </select>
                                        <button type="submit" class="ml-2 px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded-md transition-colors" onclick="return confirm('确定要执行此批量操作吗？')">
                                            应用
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                                选择
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Logo
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                网站名称
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                网址
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                                描述
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                分类
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                状态
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                操作
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($friends as $friend): ?>
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="ids[]" value="<?php echo $friend['id']; ?>" class="form-checkbox h-4 w-4 text-primary rounded">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <?php if (!empty($friend['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($friend['logo']); ?>" alt="<?php echo htmlspecialchars($friend['name']); ?>" class="h-10 w-auto rounded">
                                                <?php else: ?>
                                                    <div class="h-10 w-10 bg-gray-200 rounded flex items-center justify-center text-gray-500">
                                                        <i class="fa fa-globe"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($friend['name']); ?></div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm text-gray-500 truncate max-w-xs md:max-w-md"><?php echo htmlspecialchars($friend['url']); ?></div>
                                            </td>
                                            <td class="px-4 py-4 hidden md:table-cell">
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($friend['description']); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo htmlspecialchars($friend['category'] ?: '未分类'); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <?php if ($friend['status'] == 1): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        已启用
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        已禁用
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="friends.php?action=edit&id=<?php echo $friend['id']; ?>" class="text-primary hover:text-primary/80 mr-3">
                                                    编辑
                                                </a>
                                                <a href="friends.php?action=delete&id=<?php echo $friend['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('确定要删除该友链吗？')">
                                                    删除
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        
                        <!-- 分页 -->
                        <?php if ($total_pages > 1): ?>
                        <div class="px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        显示第 <span class="font-medium"><?php echo min($current_page * $items_per_page, $total_items); ?></span> 条，共 <span class="font-medium"><?php echo $total_items; ?></span> 条记录
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($current_page > 1): ?>
                                        <a href="friends.php?page=<?php echo $current_page - 1; ?>&status=<?php echo $filter_status; ?>&category=<?php echo urlencode($filter_category); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">上一页</span>
                                            <i class="fa fa-chevron-left h-5 w-5"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i == $current_page): ?>
                                            <a href="friends.php?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&category=<?php echo urlencode($filter_category); ?>" aria-current="page" class="z-10 bg-primary text-white relative inline-flex items-center px-4 py-2 border border-primary text-sm font-medium">
                                                <?php echo $i; ?>
                                            </a>
                                            <?php else: ?>
                                            <a href="friends.php?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&category=<?php echo urlencode($filter_category); ?>" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                                <?php echo $i; ?>
                                            </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($current_page < $total_pages): ?>
                                        <a href="friends.php?page=<?php echo $current_page + 1; ?>&status=<?php echo $filter_status; ?>&category=<?php echo urlencode($filter_category); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">下一页</span>
                                            <i class="fa fa-chevron-right h-5 w-5"></i>
                                        </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                            
                            <!-- 移动端分页 -->
                            <div class="flex items-center justify-between w-full sm:hidden">
                                <a href="<?php echo $current_page > 1 ? 'friends.php?page=' . ($current_page - 1) . '&status=' . $filter_status . '&category=' . urlencode($filter_category) : '#'; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?php echo $current_page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    <i class="fa fa-chevron-left mr-2 h-4 w-4"></i> 上一页
                                </a>
                                <span class="text-sm text-gray-700">
                                    第 <?php echo $current_page; ?> 页，共 <?php echo $total_pages; ?> 页
                                </span>
                                <a href="<?php echo $current_page < $total_pages ? 'friends.php?page=' . ($current_page + 1) . '&status=' . $filter_status . '&category=' . urlencode($filter_category) : '#'; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?php echo $current_page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    下一页 <i class="fa fa-chevron-right ml-2 h-4 w-4"></i>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
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
        
        // 添加友链表单切换
        document.getElementById('add-friend-button').addEventListener('click', function() {
            document.getElementById('add-friend-form').classList.remove('hidden');
        });
        
        document.getElementById('cancel-add').addEventListener('click', function() {
            document.getElementById('add-friend-form').classList.add('hidden');
        });
        
        // 添加第一条友链
        if (document.getElementById('add-first-friend')) {
            document.getElementById('add-first-friend').addEventListener('click', function() {
                document.getElementById('add-friend-form').classList.remove('hidden');
            });
        }
        
        // 全选功能
        document.getElementById('select-all').addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        });
    </script>
</body>
</html>