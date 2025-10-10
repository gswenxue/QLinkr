<?php
// 检查登录状态
require '../config.php';
check_admin_access();

// 处理审核操作
$message = '';
$message_type = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    switch ($action) {
        case 'approve':
            $result = approve_friend_application($pdo, $id);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            break;
            
        case 'reject':
            $result = reject_friend_application($pdo, $id);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            break;
            
        case 'delete':
            $result = delete_friend_application($pdo, $id);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            break;
    }
}

// 获取筛选状态，默认显示待审核
$status_filter = isset($_GET['status']) ? intval($_GET['status']) : 0;

// 获取所有申请
$applications = get_friend_applications($pdo, $status_filter);

// 获取主题颜色
$settings = get_settings($pdo);
$theme_color = $settings['theme_color'] ?? '#3B82F6';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>友链申请审核 - QLinkr友链管理系统</title>
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
    <style>
        /* 添加响应式表格样式 */
        @media (max-width: 768px) {
            .responsive-table {
                display: block;
                width: 100%;
                overflow-x: auto;
            }
        }
        
        /* 描述文本截断样式 */
        .desc-truncate {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
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
                        <a href="apply_review.php" class="flex items-center px-4 py-2 rounded bg-gray-700 text-white">
                            <i class="fa fa-list-alt mr-3 w-5 text-center"></i>友链申请审核
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
                            <a href="friends.php" class="block px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                                <i class="fa fa-link mr-3"></i>友链管理
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="apply_review.php" class="block px-4 py-2 rounded bg-gray-700 text-white">
                                <i class="fa fa-list-alt mr-3"></i>友链申请审核
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
                    <h2 class="text-2xl font-bold text-gray-800">友链申请审核</h2>
                    <p class="text-gray-600">管理用户提交的友链申请</p>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <!-- 筛选器（已删除全部按钮） -->
                <div class="bg-white rounded-lg shadow p-4 mb-6 overflow-x-auto">
                    <div class="flex flex-wrap gap-2 min-w-max">
                        <a href="apply_review.php?status=0" class="px-4 py-2 rounded <?php echo $status_filter == 0 ? 'bg-primary text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?> transition-colors">
                            待审核 <span class="ml-1 bg-white text-primary px-2 py-0.5 rounded-full text-xs"><?php echo count(get_friend_applications($pdo, 0)); ?></span>
                        </a>
                        <a href="apply_review.php?status=1" class="px-4 py-2 rounded <?php echo $status_filter == 1 ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?> transition-colors">
                            已通过 <span class="ml-1 bg-white text-green-500 px-2 py-0.5 rounded-full text-xs"><?php echo count(get_friend_applications($pdo, 1)); ?></span>
                        </a>
                        <a href="apply_review.php?status=2" class="px-4 py-2 rounded <?php echo $status_filter == 2 ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?> transition-colors">
                            已拒绝 <span class="ml-1 bg-white text-red-500 px-2 py-0.5 rounded-full text-xs"><?php echo count(get_friend_applications($pdo, 2)); ?></span>
                        </a>
                    </div>
                </div>
                
                <!-- 申请列表 -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <?php if (empty($applications)): ?>
                        <div class="p-8 text-center">
                            <i class="fa fa-inbox text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-500">没有找到相关的友链申请</p>
                        </div>
                    <?php else: ?>
                        <!-- 添加响应式表格容器 -->
                        <div class="responsive-table">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">网站名称</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">网址</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">分类</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">提交时间</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($app['logo'])): ?>
                                                <img src="<?php echo htmlspecialchars($app['logo']); ?>" alt="<?php echo htmlspecialchars($app['name']); ?>" class="h-10 w-10 rounded object-cover">
                                            <?php else: ?>
                                                <div class="h-10 w-10 bg-gray-200 rounded flex items-center justify-center">
                                                    <i class="fa fa-globe text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($app['name']); ?></div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?php echo htmlspecialchars($app['contact'] ?? '无联系方式'); ?>
                                            </div>
                                            <!-- 在小屏幕上显示网址 -->
                                            <div class="text-xs text-gray-600 mt-1 sm:hidden break-all">
                                                <a href="<?php echo htmlspecialchars($app['url']); ?>" target="_blank" class="hover:text-primary">
                                                    <?php echo htmlspecialchars($app['url']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 hidden sm:table-cell">
                                            <div class="text-sm text-gray-900 break-all max-w-xs">
                                                <a href="<?php echo htmlspecialchars($app['url']); ?>" target="_blank" class="hover:text-primary">
                                                    <?php echo htmlspecialchars($app['url']); ?>
                                                </a>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1 desc-truncate">
                                                <?php 
                                                $description = $app['description'] ?? '无描述';
                                                // 大屏幕显示前20字加省略号，小屏幕不受此限制但受line-clamp控制
                                                if (mb_strlen($description) > 20) {
                                                    echo '<span class="hidden md:inline">'.htmlspecialchars(mb_substr($description, 0, 20)).'...</span>';
                                                    echo '<span class="md:hidden">'.htmlspecialchars($description).'</span>';
                                                } else {
                                                    echo htmlspecialchars($description);
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <?php echo htmlspecialchars($app['category'] ?? '未分类'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden sm:table-cell">
                                            <?php echo date('Y-m-d H:i', strtotime($app['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($app['status'] == 0): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    待审核
                                                </span>
                                            <?php elseif ($app['status'] == 1): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    已通过
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    已拒绝
                                                </span>
                                            <?php endif; ?>
                                            <!-- 在小屏幕上显示提交时间 -->
                                            <div class="text-xs text-gray-500 mt-1 sm:hidden">
                                                <?php echo date('Y-m-d H:i', strtotime($app['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                                                <?php if ($app['status'] == 0): ?>
                                                    <a href="apply_review.php?action=approve&id=<?php echo $app['id']; ?>" class="text-green-600 hover:text-green-900" onclick="return confirm('确定要批准这个友链申请吗？')">
                                                        批准
                                                    </a>
                                                    <a href="apply_review.php?action=reject&id=<?php echo $app['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('确定要拒绝这个友链申请吗？')">
                                                        拒绝
                                                    </a>
                                                <?php endif; ?>
                                                <a href="apply_review.php?action=delete&id=<?php echo $app['id']; ?>" class="text-gray-600 hover:text-gray-900" onclick="return confirm('确定要删除这个申请记录吗？')">
                                                    删除
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
    </script>
</body>
</html>