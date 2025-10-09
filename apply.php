<?php
require 'config.php';

// 记录访问
record_visit($pdo);

$message = '';
$message_type = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'url' => trim($_POST['url'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'contact' => trim($_POST['contact'] ?? '')
    ];
    
    // 验证必填字段
    if (empty($data['name']) || empty($data['url'])) {
        $message = '网站名称和网址为必填项';
        $message_type = 'error';
    } elseif (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
        $message = '请输入有效的网址（需包含http://或https://）';
        $message_type = 'error';
    } else {
        // 处理logo上传
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $upload_result = upload_image($_FILES['logo']);
            if ($upload_result['success']) {
                $data['logo'] = $upload_result['url'];
            } else {
                $message = $upload_result['message'];
                $message_type = 'error';
            }
        }
        
        // 如果没有上传错误，提交申请
        if (empty($message)) {
            $result = submit_friend_application($pdo, $data);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            
            // 如果成功提交，清空表单数据
            if ($result['success']) {
                $data = [];
            }
        }
    }
} else {
    $data = [
        'name' => '',
        'url' => '',
        'description' => '',
        'category' => '',
        'contact' => ''
    ];
}

// 获取网站设置
$settings = get_settings($pdo);
$site_title = $settings['site_title'] ?? '我的友链';
$logo = $settings['logo'] ?? '';
$theme_color = $settings['theme_color'] ?? '#3B82F6';
$glass_effect = isset($settings['glass_effect']) ? $settings['glass_effect'] : 1;
$apply_page_content = $settings['apply_page_content'] ?? '欢迎申请添加友链，请填写以下信息，我们会尽快审核。';
$background = $settings['background'] ?? '';

// 获取所有分类（供用户选择）
$categories = get_categories($pdo);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>友链申请 - <?php echo htmlspecialchars($site_title); ?></title>
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
        /* 页面布局基础样式 */
        html, body {
            height: 100%;
        }
        
        body {
            display: flex;
            flex-direction: column;
        }
        
        main {
            flex: 1;
        }
        
        <?php if (!empty($background)): ?>
        body {
            background-image: url('<?php echo htmlspecialchars($background); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        /* 夜间模式背景调整 */
        body.dark-mode {
            background-blend-mode: overlay;
            background-color: rgba(30, 30, 30, 0.85);
        }
        <?php endif; ?>
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.7);
        }
        
        .glass-card {
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.85);
        }
        
        /* 夜间模式样式 */
        body.dark-mode {
            color: #e5e7eb;
            background-color: #1f2937;
        }
        
        body.dark-mode .glass-effect {
            background-color: rgba(30, 30, 40, 0.7);
        }
        
        body.dark-mode .glass-card {
            background-color: rgba(30, 30, 40, 0.85);
        }
        
        body.dark-mode .text-gray-900 {
            color: #f3f4f6;
        }
        
        body.dark-mode .text-gray-700 {
            color: #e5e7eb;
        }
        
        body.dark-mode .text-gray-600 {
            color: #d1d5db;
        }
        
        body.dark-mode .border-gray-300 {
            border-color: #4b5563;
        }
        
        body.dark-mode .bg-white {
            background-color: #374151;
        }
        
        body.dark-mode .bg-gray-50 {
            background-color: #1f2937;
        }
        
        body.dark-mode .prose-sm {
            color: #d1d5db;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- 顶部导航 -->
    <header class="py-4 px-6 <?php echo $glass_effect ? 'glass-effect' : 'bg-white'; ?> shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="flex items-center">
                <?php if (!empty($logo)): ?>
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($site_title); ?>" class="h-8 w-auto mr-2">
                <?php else: ?>
                    <i class="fa fa-link text-primary text-2xl mr-2"></i>
                <?php endif; ?>
                <span class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($site_title); ?></span>
            </a>
            
            <nav>
                <ul class="flex space-x-6">
                    <li><a href="index.php" class="text-gray-600 hover:text-primary transition-colors">首页</a></li>
                    <li><a href="apply.php" class="text-primary font-medium">申请友链</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- 主要内容 -->
    <main class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">友链申请</h1>
                <div class="prose prose-sm mx-auto text-gray-600">
                    <?php echo nl2br(htmlspecialchars($apply_page_content)); ?>
                </div>
            </div>
            
            <div class="rounded-xl shadow-lg overflow-hidden <?php echo $glass_effect ? 'glass-card' : 'bg-white'; ?>">
                <div class="p-6 md:p-8">
                    <?php if (!empty($message)): ?>
                    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                        <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">网站名称 <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" required
                                    value="<?php echo htmlspecialchars($data['name'] ?? ''); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入您的网站名称">
                            </div>
                            
                            <div>
                                <label for="url" class="block text-sm font-medium text-gray-700 mb-1">网站网址 <span class="text-red-500">*</span></label>
                                <input type="url" id="url" name="url" required
                                    value="<?php echo htmlspecialchars($data['url'] ?? ''); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入您的网站网址（需包含http://或https://）">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">网站分类</label>
                            <select id="category" name="category"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors">
                                <option value="">请选择分类（可选）</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo isset($data['category']) && $data['category'] == $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other">其他</option>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">网站描述</label>
                            <textarea id="description" name="description" rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                placeholder="请输入您的网站描述（可选）"><?php echo htmlspecialchars($data['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="contact" class="block text-sm font-medium text-gray-700 mb-1">联系方式</label>
                                <input type="text" id="contact" name="contact"
                                    value="<?php echo htmlspecialchars($data['contact'] ?? ''); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入您的联系方式（如邮箱，可选）">
                            </div>
                            
                            <div>
                                <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">网站Logo</label>
                                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-colors">
                                <p class="text-xs text-gray-500 mt-1">支持JPG、PNG、GIF格式，建议尺寸120x120px</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-center">
                            <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors font-medium flex items-center">
                                <i class="fa fa-paper-plane mr-2"></i>提交申请
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <!-- 页脚 -->
    <footer class="py-8 px-6 <?php echo $glass_effect ? 'glass-effect' : 'bg-gray-50'; ?> border-t mt-auto">
        <div class="max-w-7xl mx-auto text-center text-gray-600 text-sm">
            <?php if (!empty($settings['footer_text'])): ?>
                <p><?php echo nl2br(htmlspecialchars($settings['footer_text'])); ?></p>
            <?php else: ?>
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_title); ?> - QLinkr友链系统</p>
            <?php endif; ?>
        </div>
    </footer>

    <script>
        // 同步夜间模式设置
        document.addEventListener('DOMContentLoaded', function() {
            // 检查本地存储中的主题设置
            if (localStorage.getItem('theme') === 'dark' || 
                (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        });
    </script>
</body>
</html>