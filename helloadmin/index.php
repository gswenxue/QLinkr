<?php
// 检查登录状态
require '../config.php';
check_admin_access();

// 记录访问
record_visit($pdo);

// 获取统计数据
$total_friends = count(get_friends($pdo));
$visit_stats = get_visit_stats($pdo);
$categories = get_categories($pdo);
$total_categories = count($categories);
// 获取待审核申请数量
$pending_applications = count(get_friend_applications($pdo, 0));

// 获取最近友链
try {
    $stmt = $pdo->query("SELECT * FROM friends ORDER BY created_at DESC LIMIT 5");
    $recent_friends = $stmt->fetchAll();
} catch(PDOException $e) {
    $recent_friends = [];
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
    <title>后台仪表盘 - QLinkr友链管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo $theme_color; ?>',
                        secondary: '#10B981',
                        danger: '#EF4444',
                        info: '#3B82F6',
                        warning: '#F59E0B'
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
                        <a href="index.php" class="flex items-center px-4 py-2 rounded bg-gray-700 text-white">
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
                            <a href="index.php" class="block px-4 py-2 rounded bg-gray-700 text-white">
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
                    <h2 class="text-2xl font-bold text-gray-800">仪表盘</h2>
                    <p class="text-gray-600">系统概览和统计信息</p>
                </div>
                
                <!-- 统计卡片 -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-primary">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">总友链数</p>
                                <h3 class="text-2xl font-bold mt-1"><?php echo $total_friends; ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                                <i class="fa fa-link text-primary"></i>
                            </div>
                        </div>
                        <a href="friends.php" class="text-primary text-sm mt-3 inline-block">查看详情 <i class="fa fa-arrow-right ml-1"></i></a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-warning">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">待审核</p>
                                <h3 class="text-2xl font-bold mt-1"><?php echo $pending_applications; ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-warning/10 flex items-center justify-center">
                                <i class="fa fa-clock-o text-warning"></i>
                            </div>
                        </div>
                        <a href="apply_review.php" class="text-warning text-sm mt-3 inline-block">处理申请 <i class="fa fa-arrow-right ml-1"></i></a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-secondary">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">分类数</p>
                                <h3 class="text-2xl font-bold mt-1"><?php echo $total_categories; ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center">
                                <i class="fa fa-folder text-secondary"></i>
                            </div>
                        </div>
                        <a href="friends.php" class="text-secondary text-sm mt-3 inline-block">管理分类 <i class="fa fa-arrow-right ml-1"></i></a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-info">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">今日访问</p>
                                <h3 class="text-2xl font-bold mt-1"><?php echo $visit_stats['today']; ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-info/10 flex items-center justify-center">
                                <i class="fa fa-eye text-info"></i>
                            </div>
                        </div>
                        <p class="text-gray-500 text-sm mt-3">较昨日 <span class="text-green-500">+<?php echo max(0, $visit_stats['today'] - $visit_stats['yesterday']); ?></span></p>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-danger">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">总访问量</p>
                                <h3 class="text-2xl font-bold mt-1"><?php echo $visit_stats['total']; ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-danger/10 flex items-center justify-center">
                                <i class="fa fa-line-chart text-danger"></i>
                            </div>
                        </div>
                        <p class="text-gray-500 text-sm mt-3">近7天: <?php echo $visit_stats['seven_days']; ?></p>
                    </div>
                </div>
                
                <!-- 图表和最近友链 -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white rounded-lg shadow p-5">
                        <h3 class="text-lg font-bold mb-4">访问统计趋势</h3>
                        <div class="h-64">
                            <canvas id="visitChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-5">
                        <h3 class="text-lg font-bold mb-4">最近添加的友链</h3>
                        <div class="space-y-4">
                            <?php if (!empty($recent_friends)): ?>
                                <?php foreach ($recent_friends as $friend): ?>
                                <div class="flex items-center">
                                    <?php if (!empty($friend['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($friend['logo']); ?>" alt="<?php echo htmlspecialchars($friend['name']); ?>" class="w-10 h-10 rounded object-cover mr-3">
                                    <?php else: ?>
                                    <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center mr-3">
                                        <i class="fa fa-globe text-gray-400"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($friend['name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('Y-m-d', strtotime($friend['created_at'])); ?></p>
                                    </div>
                                    <a href="friends.php?action=edit&id=<?php echo $friend['id']; ?>" class="text-primary hover:text-primary/80">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">暂无友链数据</p>
                            <?php endif; ?>
                        </div>
                        <a href="friends.php" class="block text-center text-primary mt-4 text-sm">查看所有友链</a>
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
        
        // 访问统计图表
        const ctx = document.getElementById('visitChart').getContext('2d');
        
        // 生成最近7天的日期
        const labels = [];
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.getMonth() + 1 + '-' + date.getDate());
        }
        
        // 模拟最近7天的访问数据
        const data = [
            Math.floor(Math.random() * 50) + 10,
            Math.floor(Math.random() * 50) + 10,
            Math.floor(Math.random() * 50) + 10,
            Math.floor(Math.random() * 50) + 10,
            Math.floor(Math.random() * 50) + 10,
            <?php echo $visit_stats['yesterday']; ?>,
            <?php echo $visit_stats['today']; ?>
        ];
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '访问量',
                    data: data,
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: '<?php echo $theme_color; ?>',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>