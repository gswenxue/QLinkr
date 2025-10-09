<?php
require 'config.php';
$settings = get_settings($pdo);
$friends = get_friends($pdo);
$categories = get_categories($pdo);

// 记录访问
record_visit($pdo);

// 获取主题颜色
$theme_color = $settings['theme_color'] ?? '#3B82F6';
?>
<!DOCTYPE html>
<html lang="zh-CN" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['site_title'] ?? '我的友链'); ?></title>
    <?php if (!empty($settings['logo'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($settings['logo']); ?>" type="image/x-icon">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- Tailwind 配置 -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo $theme_color; ?>',
                        secondary: '#10B981',
                        dark: {
                            100: '#1F2937',
                            200: '#111827',
                            300: '#030712'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .card-hover {
                @apply transition-all duration-300 hover:shadow-lg hover:-translate-y-1;
            }
            .bg-blur {
                @apply backdrop-blur-md;
            }
            .modal-backdrop {
                @apply fixed inset-0 bg-black/50 z-40 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300;
            }
            .modal-backdrop.active {
                @apply opacity-100 pointer-events-auto;
            }
            .modal-content {
                @apply bg-white dark:bg-dark-100 rounded-xl shadow-xl max-w-md w-full transform scale-95 transition-transform duration-300;
            }
            .modal-backdrop.active .modal-content {
                @apply scale-100;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 dark:bg-dark-200 dark:text-gray-100 transition-colors duration-300 min-h-screen flex flex-col">
    <!-- 背景图 -->
    <?php if (!empty($settings['background'])): ?>
    <div class="fixed inset-0 z-0 opacity-20 dark:opacity-10">
        <img src="<?php echo htmlspecialchars($settings['background']); ?>" alt="背景图" class="w-full h-full object-cover">
    </div>
    <?php endif; ?>

    <!-- 顶部导航栏 -->
    <header class="sticky top-0 z-50 bg-white/80 dark:bg-dark-100/80 border-b border-gray-200 dark:border-gray-700 transition-all duration-300 <?php echo (!empty($settings) && $settings['glass_effect']) ? 'bg-blur' : ''; ?>">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <!-- Logo 和名称 -->
            <div class="flex items-center space-x-3">
                <?php if (!empty($settings['logo'])): ?>
                <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="网站Logo" class="h-10 w-10 rounded-full object-cover">
                <?php else: ?>
                <div class="h-10 w-10 rounded-full bg-primary flex items-center justify-center text-white">
                    <i class="fa fa-link text-xl"></i>
                </div>
                <?php endif; ?>
                <h1 class="text-xl font-bold">
                    <?php echo htmlspecialchars($settings['site_title'] ?? '我的友链'); ?>
                </h1>
            </div>
            
            <!-- 右侧菜单 -->
            <div class="flex items-center space-x-4">
                <a href="apply.php" class="px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors duration-200 flex items-center">
                    <i class="fa fa-paper-plane mr-2"></i>申请收录
                </a>
                
                <!-- 夜间模式切换 -->
                <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200">
                    <i class="fa fa-moon-o dark:hidden text-xl"></i>
                    <i class="fa fa-sun-o hidden dark:inline text-xl"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- 主内容区 -->
    <main class="flex-grow container mx-auto px-4 py-8 z-10 relative">
        <!-- 分类筛选 -->
        <?php if (!empty($categories)): ?>
        <div class="mb-8 overflow-x-auto pb-2">
            <div class="flex space-x-2 min-w-max">
                <button class="category-filter px-4 py-2 bg-primary text-white rounded-full text-sm font-medium" data-category="">
                    全部
                </button>
                <?php foreach ($categories as $category): ?>
                <button class="category-filter px-4 py-2 bg-white dark:bg-dark-100 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full text-sm font-medium transition-colors" data-category="<?php echo htmlspecialchars($category); ?>">
                    <?php echo htmlspecialchars($category); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 友链统计 -->
        <div class="mb-8 text-center">
            <p class="text-gray-600 dark:text-gray-400">
                目前共收录 <span class="font-bold text-primary"><?php echo count($friends); ?></span> 个友链
                <?php if (!empty($categories)): ?>
                ，分为 <span class="font-bold text-primary"><?php echo count($categories); ?></span> 个分类
                <?php endif; ?>
            </p>
        </div>
        
        <!-- 友链列表 -->
        <?php if (!empty($friends)): ?>
            <?php foreach ($categories as $category): ?>
            <div class="category-section mb-10" data-category="<?php echo htmlspecialchars($category); ?>">
                <h2 class="text-xl font-bold mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                    <?php echo htmlspecialchars($category); ?>
                </h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($friends as $friend): ?>
                        <?php if ($friend['category'] == $category): ?>
                        <div class="friend-card bg-white dark:bg-dark-100 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700 card-hover" 
                             data-id="<?php echo $friend['id']; ?>"
                             data-name="<?php echo htmlspecialchars($friend['name']); ?>"
                             data-url="<?php echo htmlspecialchars($friend['url']); ?>"
                             data-description="<?php echo htmlspecialchars($friend['description'] ?? '暂无描述'); ?>"
                             data-logo="<?php echo htmlspecialchars($friend['logo'] ?? ''); ?>">
                            <div class="flex items-start">
                                <?php if (!empty($friend['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($friend['logo']); ?>" alt="<?php echo htmlspecialchars($friend['name']); ?>" class="h-14 w-14 rounded object-cover mr-4 flex-shrink-0">
                                <?php else: ?>
                                <div class="h-14 w-14 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center mr-4 flex-shrink-0">
                                    <i class="fa fa-globe text-gray-400"></i>
                                </div>
                                <?php endif; ?>
                                
                                <div class="flex-grow min-w-0">
                                    <h3 class="font-bold text-gray-900 dark:text-white truncate">
                                        <?php echo htmlspecialchars($friend['name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                        <?php echo htmlspecialchars($friend['description'] ?? '暂无描述'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- 未分类友链 -->
            <?php 
            $uncategorized = array_filter($friends, function($friend) use ($categories) {
                return !in_array($friend['category'], $categories);
            });
            
            if (!empty($uncategorized)): ?>
            <div class="category-section mb-10" data-category="未分类">
                <h2 class="text-xl font-bold mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                    未分类
                </h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($uncategorized as $friend): ?>
                    <div class="friend-card bg-white dark:bg-dark-100 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700 card-hover" 
                         data-id="<?php echo $friend['id']; ?>"
                         data-name="<?php echo htmlspecialchars($friend['name']); ?>"
                         data-url="<?php echo htmlspecialchars($friend['url']); ?>"
                         data-description="<?php echo htmlspecialchars($friend['description'] ?? '暂无描述'); ?>"
                         data-logo="<?php echo htmlspecialchars($friend['logo'] ?? ''); ?>">
                        <div class="flex items-start">
                            <?php if (!empty($friend['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($friend['logo']); ?>" alt="<?php echo htmlspecialchars($friend['name']); ?>" class="h-14 w-14 rounded object-cover mr-4 flex-shrink-0">
                            <?php else: ?>
                            <div class="h-14 w-14 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fa fa-globe text-gray-400"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex-grow min-w-0">
                                <h3 class="font-bold text-gray-900 dark:text-white truncate">
                                    <?php echo htmlspecialchars($friend['name']); ?>
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                    <?php echo htmlspecialchars($friend['description'] ?? '暂无描述'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="text-center py-16 bg-white dark:bg-dark-100 rounded-xl shadow">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa fa-link text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">暂无友链数据</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">当前还没有添加任何友链</p>
            <a href="apply.php" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors duration-200">
                <i class="fa fa-paper-plane mr-2"></i>申请成为第一个友链
            </a>
        </div>
        <?php endif; ?>
    </main>

    <!-- 页脚 -->
    <footer class="bg-white/80 dark:bg-dark-100/80 border-t border-gray-200 dark:border-gray-700 py-4 transition-all duration-300 <?php echo (!empty($settings) && $settings['glass_effect']) ? 'bg-blur' : ''; ?> z-10 relative">
        <div class="container mx-auto px-4">
            <!-- 页脚链接 -->
            <?php if (!empty($settings['footer_elements'])): ?>
            <?php $footer_elements = json_decode($settings['footer_elements'], true); ?>
            <?php if (is_array($footer_elements) && !empty($footer_elements)): ?>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-2 text-sm">
                <?php foreach ($footer_elements as $element): ?>
                    <?php if (!empty($element['text']) && !empty($element['url'])): ?>
                    <a href="<?php echo htmlspecialchars($element['url']); ?>" class="text-gray-600 dark:text-gray-400 hover:text-primary dark:hover:text-primary transition-colors" target="_blank" rel="noopener noreferrer">
                        <?php echo htmlspecialchars($element['text']); ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- 版权信息 -->
            <div class="text-center text-sm text-gray-600 dark:text-gray-400 mb-2">
                <?php echo !empty($settings['footer_text']) ? nl2br(htmlspecialchars($settings['footer_text'])) : '© ' . date('Y') . ' QLinkr友链平台 版权所有'; ?>
            </div>
            
            <!-- 自定义内容 -->
            <?php if (!empty($settings['footer_custom'])): ?>
            <div class="text-center text-xs text-gray-500 dark:text-gray-500">
                <?php echo $settings['footer_custom']; ?>
            </div>
            <?php endif; ?>
        </div>
    </footer>

    <!-- 友链详情弹窗 -->
    <div id="friend-modal" class="modal-backdrop">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <h3 id="modal-title" class="text-xl font-bold"></h3>
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>
                
                <div id="modal-body" class="mb-6">
                    <div class="flex items-start mb-4">
                        <div id="modal-logo-container" class="h-16 w-16 rounded mr-4 flex-shrink-0">
                            <!-- 动态填充logo -->
                        </div>
                        <div id="modal-description" class="text-gray-600 dark:text-gray-400">
                            <!-- 动态填充描述 -->
                        </div>
                    </div>
                    
                    <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-3 text-sm text-amber-800 dark:text-amber-300 mb-4">
                        <i class="fa fa-exclamation-circle mr-2"></i>
                        <span>本站收集的信息来源于网络，请谨慎访问。访问前请确认该网站内容的安全性。</span>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <a id="visit-button" href="#" target="_blank" rel="noopener noreferrer" class="px-6 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-colors duration-200 flex items-center">
                        <i class="fa fa-external-link mr-2"></i>我已知晓，立即访问
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 夜间模式切换
        const themeToggle = document.getElementById('theme-toggle');
        const htmlElement = document.documentElement;
        
        // 检查本地存储中的主题偏好
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            htmlElement.classList.add('dark');
        } else {
            htmlElement.classList.remove('dark');
        }
        
        themeToggle.addEventListener('click', () => {
            if (htmlElement.classList.contains('dark')) {
                htmlElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                htmlElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        });
        
        // 分类筛选功能
        const categoryFilters = document.querySelectorAll('.category-filter');
        const categorySections = document.querySelectorAll('.category-section');
        
        categoryFilters.forEach(filter => {
            filter.addEventListener('click', () => {
                // 更新筛选按钮样式
                categoryFilters.forEach(f => {
                    f.classList.remove('bg-primary', 'text-white');
                    f.classList.add('bg-white', 'dark:bg-dark-100', 'hover:bg-gray-100', 'dark:hover:bg-gray-800');
                });
                filter.classList.add('bg-primary', 'text-white');
                filter.classList.remove('bg-white', 'dark:bg-dark-100', 'hover:bg-gray-100', 'dark:hover:bg-gray-800');
                
                const category = filter.getAttribute('data-category');
                
                // 显示或隐藏对应分类
                categorySections.forEach(section => {
                    if (category === '' || section.getAttribute('data-category') === category) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                });
            });
        });
        
        // 友链弹窗功能
        const friendCards = document.querySelectorAll('.friend-card');
        const friendModal = document.getElementById('friend-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalLogoContainer = document.getElementById('modal-logo-container');
        const modalDescription = document.getElementById('modal-description');
        const visitButton = document.getElementById('visit-button');
        const closeModal = document.getElementById('close-modal');
        
        // 打开弹窗
        friendCards.forEach(card => {
            card.addEventListener('click', () => {
                const name = card.getAttribute('data-name');
                const url = card.getAttribute('data-url');
                const description = card.getAttribute('data-description');
                const logo = card.getAttribute('data-logo');
                
                // 填充弹窗内容
                modalTitle.textContent = name;
                visitButton.setAttribute('href', url);
                
                // 设置logo
                if (logo) {
                    modalLogoContainer.innerHTML = `<img src="${logo}" alt="${name}" class="h-16 w-16 rounded object-cover">`;
                } else {
                    modalLogoContainer.innerHTML = `<div class="h-16 w-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                    <i class="fa fa-globe text-gray-400"></i>
                                                  </div>`;
                }
                
                // 设置描述
                modalDescription.textContent = description || '暂无描述';
                
                // 显示弹窗
                friendModal.classList.add('active');
                document.body.style.overflow = 'hidden'; // 防止背景滚动
            });
        });
        
        // 关闭弹窗
        function hideModal() {
            friendModal.classList.remove('active');
            document.body.style.overflow = ''; // 恢复滚动
        }
        
        closeModal.addEventListener('click', hideModal);
        
        // 点击弹窗外部关闭
        friendModal.addEventListener('click', (e) => {
            if (e.target === friendModal) {
                hideModal();
            }
        });
        
        // 按ESC键关闭弹窗
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && friendModal.classList.contains('active')) {
                hideModal();
            }
        });
    </script>
</body>
</html>
