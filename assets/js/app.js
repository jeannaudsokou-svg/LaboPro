/**
 * JavaScript principal - LaboPro
 * Gestion Laboratoire Médical
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // SIDEBAR TOGGLE
    // ============================================
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    
    // Toggle sidebar on desktop
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
        
        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }
    
    // Toggle sidebar on mobile
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992 && 
                !sidebar.contains(e.target) && 
                !mobileMenuBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }
    
    // ============================================
    // RECHERCHE GLOBALE
    // ============================================
    const searchInput = document.querySelector('.header-search input');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(function() {
                    // Effectuer la recherche
                    globalSearch(query);
                }, 300);
            }
        });
    }
    
    function globalSearch(query) {
        // Implémenter la recherche AJAX ici
        console.log('Recherche:', query);
    }
    
    // ============================================
    // CONFIRMATION DE SUPPRESSION
    // ============================================
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Êtes-vous sûr de vouloir effectuer cette action ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // ============================================
    // FERMETURE AUTOMATIQUE DES ALERTES
    // ============================================
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // ============================================
    // VALIDATION DES FORMULAIRES
    // ============================================
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // ============================================
    // DATEPICKER CONFIGURATION
    // ============================================
    document.querySelectorAll('input[type="date"]').forEach(function(input) {
        // Définir la date minimum à aujourd'hui pour les rendez-vous
        if (input.dataset.minToday) {
            input.min = new Date().toISOString().split('T')[0];
        }
    });
    
    // ============================================
    // TOOLTIPS BOOTSTRAP
    // ============================================
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // ============================================
    // FORMATAGE TÉLÉPHONE
    // ============================================
    document.querySelectorAll('input[data-phone]').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            
            // Format: 06 12 34 56 78
            if (value.length > 0) {
                value = value.match(/.{1,2}/g).join(' ');
            }
            e.target.value = value;
        });
    });
    
    // ============================================
    // IMPRESSION
    // ============================================
    document.querySelectorAll('[data-print]').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.dataset.print;
            const printContent = document.getElementById(targetId);
            
            if (printContent) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write('<html><head><title>Impression</title>');
                printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">');
                printWindow.document.write('<style>body { padding: 20px; }</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write(printContent.innerHTML);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                
                setTimeout(function() {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            }
        });
    });
    
    // ============================================
    // LOADING STATES
    // ============================================
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Chargement...';
                
                // Réactiver après 10 secondes en cas d'erreur
                setTimeout(function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 10000);
            }
        });
    });
    
    // ============================================
    // CALCUL AUTOMATIQUE DU TOTAL
    // ============================================
    const priceInputs = document.querySelectorAll('[data-price]');
    if (priceInputs.length > 0) {
        function calculateTotal() {
            let total = 0;
            priceInputs.forEach(function(input) {
                if (input.checked || input.type !== 'checkbox') {
                    total += parseFloat(input.dataset.price) || 0;
                }
            });
            
            const totalDisplay = document.getElementById('totalAmount');
            if (totalDisplay) {
                totalDisplay.textContent = total.toFixed(2) + ' €';
            }
        }
        
        priceInputs.forEach(function(input) {
            input.addEventListener('change', calculateTotal);
        });
        
        calculateTotal();
    }
    
});

// ============================================
// FONCTIONS UTILITAIRES GLOBALES
// ============================================

/**
 * Afficher une notification toast
 */
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '1100';
    document.body.appendChild(container);
    return container;
}

/**
 * Requête AJAX simplifiée
 */
async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('Une erreur est survenue', 'danger');
        throw error;
    }
}
