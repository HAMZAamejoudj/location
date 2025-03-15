<!-- JavaScript for interactive components -->
<script>
    // Toggle mobile menu
    const toggleMobileMenu = () => {
        const sidebar = document.querySelector('.sidebar-mobile');
        sidebar.classList.toggle('translate-x-0');
        sidebar.classList.toggle('-translate-x-full');
    }

    // Dropdown toggle functionality
    document.querySelectorAll('.dropdown-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const dropdown = button.nextElementSibling;
            dropdown.classList.toggle('hidden');
        });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (event) => {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            if (!dropdown.previousElementSibling.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    });

    // Form validation
    const validateForm = (formId) => {
        const form = document.getElementById(formId);
        if (!form) return true;
        
        let isValid = true;
        
        // Check required fields
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('border-red-500');
                
                // Add error message if not exists
                if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('text-red-500')) {
                    const errorMsg = document.createElement('p');
                    errorMsg.className = 'text-red-500 text-xs mt-1';
                    errorMsg.innerText = 'Ce champ est requis';
                    field.insertAdjacentElement('afterend', errorMsg);
                }
                
                isValid = false;
            } else {
                field.classList.remove('border-red-500');
                
                // Remove error message if exists
                if (field.nextElementSibling && field.nextElementSibling.classList.contains('text-red-500')) {
                    field.nextElementSibling.remove();
                }
            }
        });
        
        return isValid;
    }

    // Initialize form validation for forms with data-validate attribute
    document.querySelectorAll('form[data-validate="true"]').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!validateForm(form.id)) {
                e.preventDefault();
                showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
            }
        });
    });

    // Initialize any datepickers
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            dateFormat: 'd/m/Y',
            locale: 'fr'
        });
    }
</script>

</body>
</html>