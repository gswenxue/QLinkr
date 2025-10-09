<?php
// 检查登录状态
require '../config.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// 记录访问
record_visit($pdo);

$message = '';
$message_type = '';
$settings = get_settings($pdo);

// 创建图片目录（如果不存在）
$logo_dir = '../Image/logo/';
$photo_dir = '../Image/photo/';
foreach ([$logo_dir, $photo_dir] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_title = trim($_POST['site_title'] ?? '');
    $footer_text = trim($_POST['footer_text'] ?? '');
    $footer_custom = $_POST['footer_custom'] ?? '';
    $glass_effect = isset($_POST['glass_effect']) ? 1 : 0;
    $theme_color = trim($_POST['theme_color'] ?? '#3B82F6');
    
    // 处理Logo上传
    $logo = $settings['logo'] ?? '';
    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file_info = @getimagesize($_FILES['logo']['tmp_name']);
        if ($file_info === false) {
            $message = '上传的Logo不是有效的图片';
            $message_type = 'error';
        } else {
            // 生成唯一文件名
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'site_logo.' . $ext;
            $target_path = $logo_dir . $filename;
            
            // 删除旧Logo
            if (!empty($logo) && file_exists('../' . $logo)) {
                unlink('../' . $logo);
            }
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_path)) {
                $logo = 'Image/logo/' . $filename;
            } else {
                $message = 'Logo上传失败';
                $message_type = 'error';
            }
        }
    } elseif (!isset($_POST['keep_logo'])) {
        // 如果不保留旧Logo且没有上传新Logo，则清除Logo
        if (!empty($logo) && file_exists('../' . $logo)) {
            unlink('../' . $logo);
            $logo = '';
        }
    }
    
    // 处理背景图上传
    $background = $settings['background'] ?? '';
    if ($_FILES['background']['error'] === UPLOAD_ERR_OK) {
        $file_info = @getimagesize($_FILES['background']['tmp_name']);
        if ($file_info === false) {
            $message = '上传的背景图不是有效的图片';
            $message_type = 'error';
        } else {
            // 生成唯一文件名
            $ext = pathinfo($_FILES['background']['name'], PATHINFO_EXTENSION);
            $filename = 'background.' . $ext;
            $target_path = $photo_dir . $filename;
            
            // 删除旧背景图
            if (!empty($background) && file_exists('../' . $background)) {
                unlink('../' . $background);
            }
            
            if (move_uploaded_file($_FILES['background']['tmp_name'], $target_path)) {
                $background = 'Image/photo/' . $filename;
            } else {
                $message = '背景图上传失败';
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['clear_background'])) {
        // 清除背景图
        if (!empty($background) && file_exists('../' . $background)) {
            unlink('../' . $background);
            $background = '';
        }
    }
    
    // 处理页脚元素
    $footer_elements = [];
    if (!empty($_POST['footer_element_text']) && is_array($_POST['footer_element_text'])) {
        foreach ($_POST['footer_element_text'] as $index => $text) {
            $url = $_POST['footer_element_url'][$index] ?? '';
            if (!empty(trim($text)) && !empty(trim($url))) {
                $footer_elements[] = [
                    'text' => trim($text),
                    'url' => trim($url)
                ];
            }
        }
    }
    $footer_elements_json = json_encode($footer_elements);
    
    if (empty($message) && empty($site_title)) {
        $message = '网站标题不能为空';
        $message_type = 'error';
    } elseif (empty($message)) {
        try {
            if ($settings) {
                // 更新设置
                $stmt = $pdo->prepare("UPDATE settings SET 
                    site_title = :site_title,
                    logo = :logo,
                    background = :background,
                    footer_text = :footer_text,
                    footer_custom = :footer_custom,
                    footer_elements = :footer_elements,
                    glass_effect = :glass_effect,
                    theme_color = :theme_color,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id");
                $stmt->execute([
                    ':site_title' => $site_title,
                    ':logo' => $logo,
                    ':background' => $background,
                    ':footer_text' => $footer_text,
                    ':footer_custom' => $footer_custom,
                    ':footer_elements' => $footer_elements_json,
                    ':glass_effect' => $glass_effect,
                    ':theme_color' => $theme_color,
                    ':id' => $settings['id']
                ]);
            } else {
                // 添加新设置
                $stmt = $pdo->prepare("INSERT INTO settings (
                    site_title, logo, background, footer_text, footer_custom, footer_elements, glass_effect, theme_color
                ) VALUES (
                    :site_title, :logo, :background, :footer_text, :footer_custom, :footer_elements, :glass_effect, :theme_color
                )");
                $stmt->execute([
                    ':site_title' => $site_title,
                    ':logo' => $logo,
                    ':background' => $background,
                    ':footer_text' => $footer_text,
                    ':footer_custom' => $footer_custom,
                    ':footer_elements' => $footer_elements_json,
                    ':glass_effect' => $glass_effect,
                    ':theme_color' => $theme_color
                ]);
            }
            
            // 重新获取设置
            $settings = get_settings($pdo);
            $message = '网站设置已成功更新';
            $message_type = 'success';
        } catch(PDOException $e) {
            $message = '更新设置失败: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 获取页脚元素
$footer_elements = [];
if (!empty($settings['footer_elements'])) {
    $footer_elements = json_decode($settings['footer_elements'], true);
    if (!is_array($footer_elements)) {
        $footer_elements = [];
    }
}

// 如果没有页脚元素，添加一个空的
if (empty($footer_elements)) {
    $footer_elements[] = ['text' => '', 'url' => ''];
}

// 获取主题颜色
$theme_color = $settings['theme_color'] ?? '#3B82F6';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站设置 - QLinkr友链管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo $theme_color; ?>',
                        secondary: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444',
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
                        <a href="settings.php" class="flex items-center px-4 py-2 rounded bg-gray-700 text-white">
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
                            <a href="settings.php" class="block px-4 py-2 rounded bg-gray-700 text-white">
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
                    <h2 class="text-2xl font-bold text-gray-800">网站设置</h2>
                    <p class="text-gray-600">配置网站的基本信息和外观</p>
                </div>
                
                <!-- 消息提示 -->
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <form method="post" enctype="multipart/form-data">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-xl font-bold mb-4">基本设置</h3>
                            
                            <div class="mb-6">
                                <label for="site_title" class="block text-gray-700 mb-2 font-medium">网站标题 <span class="text-red-500">*</span></label>
                                <input type="text" id="site_title" name="site_title" required
                                    value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>"
                                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入网站标题">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">网站Logo</label>
                                    <input type="file" id="logo" name="logo" accept="image/*"
                                        class="block w-full text-sm text-gray-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-lg file:border-0
                                            file:text-sm file:font-medium
                                            file:bg-primary file:text-white
                                            hover:file:bg-primary/90">
                                    <p class="text-xs text-gray-500 mt-1">支持JPG、PNG等图片格式，建议尺寸100x100px，将同时作为网站图标</p>
                                    
                                    <?php if (!empty($settings['logo'])): ?>
                                    <div class="mt-3 flex items-center">
                                        <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="当前Logo" class="h-20 rounded mr-3">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="keep_logo" checked class="form-checkbox h-5 w-5 text-primary rounded">
                                            <span class="ml-2 text-gray-700">保留当前Logo</span>
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">背景图</label>
                                    <input type="file" id="background" name="background" accept="image/*"
                                        class="block w-full text-sm text-gray-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-lg file:border-0
                                            file:text-sm file:font-medium
                                            file:bg-primary file:text-white
                                            hover:file:bg-primary/90">
                                    <p class="text-xs text-gray-500 mt-1">背景图会在网站首页和申请页显示</p>
                                    
                                    <?php if (!empty($settings['background'])): ?>
                                    <div class="mt-3 flex items-center">
                                        <img src="../<?php echo htmlspecialchars($settings['background']); ?>" alt="当前背景图" class="h-20 rounded object-cover w-32 mr-3">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="clear_background" class="form-checkbox h-5 w-5 text-danger rounded">
                                            <span class="ml-2 text-gray-700">清除背景图</span>
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-xl font-bold mb-4">外观设置</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="theme_color" class="block text-gray-700 mb-2 font-medium">主题颜色</label>
                                    <div class="flex items-center">
                                        <input type="color" id="theme_color" name="theme_color"
                                            value="<?php echo htmlspecialchars($theme_color); ?>"
                                            class="w-10 h-10 border-0 rounded">
                                        <input type="text" id="theme_color_text" 
                                            value="<?php echo htmlspecialchars($theme_color); ?>"
                                            class="ml-3 flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                            placeholder="例如: #3B82F6">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">设置网站的主题色调，影响按钮、链接等元素的颜色</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">毛玻璃效果</label>
                                    <div class="flex items-center mt-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="glass_effect"
                                                <?php echo (!empty($settings) && $settings['glass_effect']) ? 'checked' : ''; ?>
                                                class="form-checkbox h-5 w-5 text-primary rounded">
                                            <span class="ml-2 text-gray-700">在导航栏和页脚启用毛玻璃效果</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-xl font-bold mb-4">页脚设置</h3>
                            
                            <div class="mb-6">
                                <label for="footer_text" class="block text-gray-700 mb-2 font-medium">版权信息</label>
                                <textarea id="footer_text" name="footer_text" rows="2"
                                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="请输入页脚版权信息"><?php echo htmlspecialchars($settings['footer_text'] ?? ''); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">支持换行，会显示在页脚的主要位置</p>
                            </div>
                            
                            <div class="mb-6">
                                <label for="footer_custom" class="block text-gray-700 mb-2 font-medium">自定义页脚内容</label>
                                <textarea id="footer_custom" name="footer_custom" rows="3"
                                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                    placeholder="可以添加自定义HTML内容"><?php echo htmlspecialchars($settings['footer_custom'] ?? ''); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">支持HTML格式，会显示在版权信息下方</p>
                            </div>
                            
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-gray-700 font-medium">页脚链接</label>
                                    <button type="button" id="add-footer-element" class="text-sm text-primary hover:text-primary/80">
                                        <i class="fa fa-plus-circle mr-1"></i>添加链接
                                    </button>
                                </div>
                                
                                <div id="footer-elements-container">
                                    <?php foreach ($footer_elements as $index => $element): ?>
                                    <div class="footer-element flex flex-col md:flex-row gap-4 p-4 border border-gray-200 rounded-lg mb-4">
                                        <div class="flex-1">
                                            <label class="block text-gray-600 text-sm mb-1">链接文本</label>
                                            <input type="text" name="footer_element_text[]"
                                                value="<?php echo htmlspecialchars($element['text'] ?? ''); ?>"
                                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                                placeholder="如：关于我们">
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-gray-600 text-sm mb-1">链接URL</label>
                                            <input type="url" name="footer_element_url[]"
                                                value="<?php echo htmlspecialchars($element['url'] ?? ''); ?>"
                                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                                placeholder="如：https://example.com/about">
                                        </div>
                                        <div class="flex items-end">
                                            <button type="button" class="remove-footer-element text-danger hover:text-danger/80 p-2">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <p class="text-xs text-gray-500">这些链接会显示在版权信息下方，留空的链接将不会被显示</p>
                            </div>
                        </div>
                        
                        <div class="p-6 flex justify-end space-x-4">
                            <button type="submit" class="px-6 py-2 bg-primary hover:bg-primary/90 text-white rounded transition-colors">
                                保存设置
                            </button>
                            <a href="index.php" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded transition-colors">
                                取消
                            </a>
                        </div>
                    </form>
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
        
        // 主题颜色输入同步
        const colorInput = document.getElementById('theme_color');
        const colorTextInput = document.getElementById('theme_color_text');
        
        colorInput.addEventListener('input', function() {
            colorTextInput.value = this.value;
        });
        
        colorTextInput.addEventListener('input', function() {
            // 简单验证颜色格式
            if (/^#([0-9A-F]{3}){1,2}$/i.test(this.value)) {
                colorInput.value = this.value;
            }
        });
        
        // 添加页脚元素
        document.getElementById('add-footer-element').addEventListener('click', function() {
            const container = document.getElementById('footer-elements-container');
            const newElement = document.createElement('div');
            newElement.className = 'footer-element flex flex-col md:flex-row gap-4 p-4 border border-gray-200 rounded-lg mb-4';
            newElement.innerHTML = `
                <div class="flex-1">
                    <label class="block text-gray-600 text-sm mb-1">链接文本</label>
                    <input type="text" name="footer_element_text[]"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                        placeholder="如：关于我们">
                </div>
                <div class="flex-1">
                    <label class="block text-gray-600 text-sm mb-1">链接URL</label>
                    <input type="url" name="footer_element_url[]"
                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                        placeholder="如：https://example.com/about">
                </div>
                <div class="flex items-end">
                    <button type="button" class="remove-footer-element text-danger hover:text-danger/80 p-2">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newElement);
            
            // 为新添加的元素绑定删除事件
            bindRemoveEvents();
        });
        
        // 绑定删除页脚元素事件
        function bindRemoveEvents() {
            document.querySelectorAll('.remove-footer-element').forEach(button => {
                button.addEventListener('click', function() {
                    const element = this.closest('.footer-element');
                    // 确保至少保留一个元素
                    if (document.querySelectorAll('.footer-element').length > 1) {
                        element.remove();
                    } else {
                        // 清空输入而不是删除
                        element.querySelector('input[name="footer_element_text[]"]').value = '';
                        element.querySelector('input[name="footer_element_url[]"]').value = '';
                    }
                });
            });
        }
        
        // 初始绑定删除事件
        bindRemoveEvents();
    </script>
</body>
</html>
