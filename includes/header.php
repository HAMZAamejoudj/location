<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAS Gestion Automobile</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom styles -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-gray-100 antialiased">

<!-- Toast notifications container -->
<div id="notifications" class="fixed top-4 right-4 z-50 flex flex-col items-end space-y-4"></div>

<script>
// Notification system
function showNotification(message, type = 'success') {
    const notifications = document.getElementById('notifications');
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `px-4 py-3 rounded-lg shadow-lg flex items-center ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'} text-white max-w-md transform transition-all duration-300 translate-x-0 opacity-100`;
    
    // Icon based on type
    let icon = '';
    if (type === 'success') {
        icon = '<svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    } else if (type === 'error') {
        icon = '<svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    } else {
        icon = '<svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    }
    
    notification.innerHTML = `
        ${icon}
        <span>${message}</span>
        <button class="ml-4 text-white focus:outline-none" onclick="this.parentElement.remove();">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    
    notifications.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.classList.replace('translate-x-0', 'translate-x-full');
        notification.classList.replace('opacity-100', 'opacity-0');
        
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Check for flash message from PHP session
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_type'])): ?>
        showNotification('<?php echo addslashes($_SESSION['flash_message']); ?>', '<?php echo $_SESSION['flash_type']; ?>');
        <?php 
        // Clear the flash message
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']); 
        ?>
    <?php endif; ?>
});
</script>